<?php

namespace App\Services;

use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GroqChatService
{
    private const MAX_TOOL_ROUNDS = 2;

    public function __construct(
        private AiToolsService $toolsService,
    ) {}

    /**
     * Build the lightweight system prompt (no data dump — tools will provide data).
     */
    public function buildSystemPrompt(string $currentPeriod, string $userContext = ''): string
    {
        return <<<PROMPT
Kamu adalah DistoraVision AI, seorang Data Scientist & Analis Bisnis Eksekutif senior di perusahaan distribusi.

═══ IDENTITAS ═══
- Nama: Distora AI
- Peran: Menganalisis data penjualan, stok, dan piutang perusahaan distribusi
- Gaya: Profesional, ringkas, dan actionable
- Periode data terbaru: {$currentPeriod}
{$userContext}

═══ INSTRUKSI TOOL USE ═══
1. Anda memiliki akses ke tools untuk query database perusahaan secara langsung.
2. SELALU gunakan tool untuk menjawab pertanyaan tentang data. JANGAN PERNAH mengarang angka.
3. Jika user tidak menyebut periode, gunakan periode terbaru: {$currentPeriod}.
4. Untuk pertanyaan perbandingan "bulan ini vs bulan lalu", gunakan tool compare_periods.
5. Untuk pertanyaan umum seperti "ringkasan bulan ini", gunakan get_sales_summary lalu panggil tool tambahan hanya jika user meminta detail.
6. Untuk pertanyaan dengan kombinasi filter produk/outlet/salesman/principal, gunakan get_filtered_sales.
7. Untuk pertanyaan tren historis, gunakan get_sales_trend.
8. Untuk pertanyaan "kenapa naik/turun" atau penyebab perubahan, gunakan explain_sales_change.
9. Jika user menanyakan performa/data produk, toko, atau salesman tertentu (meskipun nama kurang lengkap, contoh: "sales teddy"), gunakan get_entity_detail secara langsung. Hindari search_entities jika get_entity_detail sudah cukup untuk mencari data entitas tersebut via LIKE search.
10. Panggil tool secara paralel dalam satu turn jika memerlukan informasi dari beberapa entitas/kategori sekaligus demi menghemat kuota API.

═══ ATURAN WAJIB ═══
1. ANTI-HALUSINASI: HANYA gunakan angka dari hasil tool. DILARANG mengarang data.
2. Jika tool mengembalikan "tidak ditemukan", sampaikan itu dengan jujur.
3. GAYA BAHASA:
   - JANGAN PERNAH menyebut "tool", "function call", "JSON", atau terminologi teknis.
   - Bicara seolah Anda membaca langsung dari sistem database perusahaan.
   - Gunakan bahasa Indonesia yang natural dan profesional.
4. FORMAT:
   - Gunakan format Rupiah: Rp 1.234.567 (titik sebagai pemisah ribuan).
   - Gunakan bullet points (•) atau tabel markdown untuk data tabular.
   - Untuk perbandingan, tampilkan angka kedua periode + selisih/persentase.
   - Akhiri jawaban data dengan bagian "Pendapat Distora AI:" berisi 1 saran/pendapat bisnis yang actionable.
   - Pendapat/saran harus berdasarkan angka dari hasil tool, bukan asumsi baru.
   - Jika data tidak cukup untuk memberi saran, tulis "Pendapat Distora AI: Data belum cukup untuk rekomendasi tegas."
   - Jawab RINGKAS, maksimum 300 kata kecuali diminta detail.
5. KALKULASI:
   - MoM (%) = ((Periode A - Periode B) / Periode B) × 100
   - Margin (%) = ((Net Sales - COGS) / Net Sales) × 100

═══ PARAMETER VALIDATION ═══
- Saat memanggil tool yang membutuhkan parameter bertipe INTEGER (seperti 'limit'), Anda WAJIB mengirimkannya sebagai angka literal/raw number, bukan string.
  - BENAR: {"limit": 5}
  - SALAH: {"limit": "5"}
PROMPT;
    }

    /**
     * Execute a full chat conversation with tool-use support.
     * Implements the tool call loop: LLM → tool exec → LLM → response.
     *
     * @param  array<int, array{role: string, content: string}>  $history
     * @return array{reply: string, remaining_tokens: string|null}
     *
     * @throws \RuntimeException
     */
    public function chat(array $history, string $currentPeriod, ?string $modelOverride = null): array
    {
        @set_time_limit(180);

        $apiKey = config('services.groq.api_key');
        if (empty($apiKey)) {
            throw new \RuntimeException('Groq API Key belum dikonfigurasi di file .env (GROQ_API_KEY).');
        }

        $model = $modelOverride ?: config('services.groq.model', 'llama-3.1-8b-instant');
        $temperature = config('services.groq.temperature', 0.3);
        $maxTokens = config('services.groq.max_tokens', 1500);
        $timeout = config('services.groq.timeout', 30);

        // Build initial messages
        $messages = [
            ['role' => 'system', 'content' => $this->buildSystemPrompt($currentPeriod, $this->buildUserContext())],
        ];

        // Append sanitized conversation history (last 4 messages to save tokens and avoid quota limits)
        $recentHistory = array_slice($history, -4);
        foreach ($recentHistory as $msg) {
            $content = $msg['content'];
            if ($msg['role'] === 'user') {
                $content = $this->sanitizeUserMessage($content);
            }
            $messages[] = ['role' => $msg['role'], 'content' => $content];
        }

        $toolDefs = $this->toolsService->getToolDefinitions();
        $remainingTokens = null;

        // Tool call loop (max N rounds to prevent infinite loops)
        for ($round = 0; $round < self::MAX_TOOL_ROUNDS; $round++) {
            $currentTools = ($round === 0) ? $toolDefs : [];
            $response = $this->callGroqApiWithRetry($apiKey, $model, $messages, $currentTools, $temperature, $maxTokens, $timeout);

            $remainingTokens = $response->header('x-ratelimit-remaining-tokens');

            if (! $response->successful()) {
                $this->handleApiError($response);
            }

            $result = $response->json();
            $choice = $result['choices'][0] ?? null;

            if (! $choice) {
                throw new \RuntimeException('Gagal terhubung ke layanan AI. Silakan coba lagi.');
            }

            $message = $choice['message'];
            $finishReason = $choice['finish_reason'] ?? 'stop';

            // If the model wants to call tools
            if ($finishReason === 'tool_calls' && ! empty($message['tool_calls'])) {
                // Append the assistant's tool call message
                $messages[] = $message;

                // Execute each tool call and append results
                foreach ($message['tool_calls'] as $toolCall) {
                    $functionName = $toolCall['function']['name'];
                    $arguments = $this->decodeToolArguments($toolCall['function']['arguments'] ?? '{}', $functionName);

                    Log::debug('AI Tool Call', ['tool' => $functionName, 'args' => $arguments]);

                    $toolResult = $this->toolsService->executeTool($functionName, $arguments);

                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $toolCall['id'],
                        'content' => $toolResult,
                    ];
                }

                // Continue the loop — send results back to LLM
                continue;
            }

            // Model returned a final text response
            $aiText = $message['content'] ?? 'Maaf, saya tidak dapat menghasilkan jawaban saat ini.';

            return [
                'reply' => $aiText,
                'remaining_tokens' => $remainingTokens,
            ];
        }

        // If we exhausted all rounds, return whatever we have
        return [
            'reply' => 'Maaf, saya memerlukan terlalu banyak langkah untuk menjawab pertanyaan ini. Silakan coba pertanyaan yang lebih spesifik.',
            'remaining_tokens' => $remainingTokens,
        ];
    }

    /**
     * Decode model-provided tool arguments without letting malformed JSON break the chat flow.
     *
     * @return array<string, mixed>
     */
    private function decodeToolArguments(string $rawArguments, string $functionName): array
    {
        $decoded = json_decode($rawArguments, true);

        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            Log::warning('AI tool arguments could not be decoded', [
                'tool' => $functionName,
                'arguments' => $rawArguments,
                'json_error' => json_last_error_msg(),
            ]);

            return [];
        }

        foreach (['limit', 'months'] as $integerKey) {
            if (array_key_exists($integerKey, $decoded)) {
                $decoded[$integerKey] = max(1, (int) $decoded[$integerKey]);
            }
        }

        return $decoded;
    }

    private function buildUserContext(): string
    {
        $user = auth()->user();
        if (! $user) {
            return '';
        }

        $role = $user->role ?? 'user';
        $scope = match (true) {
            method_exists($user, 'isSalesman') && $user->isSalesman() => 'Data dibatasi ke salesman yang terhubung dengan akun ini.',
            method_exists($user, 'isSupervisor') && $user->isSupervisor() => 'Data dibatasi ke principal yang ditugaskan ke akun ini.',
            default => 'Data mengikuti hak akses aplikasi untuk akun ini.',
        };

        return "\n- User aktif: {$user->name} ({$role})\n- Batas akses: {$scope}";
    }

    /**
     * Make the actual API call to Groq.
     *
     * @param  array<int, array<string, mixed>>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     */
    private function callGroqApi(
        string $apiKey,
        string $model,
        array $messages,
        array $tools,
        float $temperature,
        int $maxTokens,
        int $timeout,
    ): Response {
        $request = Http::timeout($timeout)
            ->connectTimeout(10)
            ->retry(2, 500, function ($exception, $request) {
                if ($exception instanceof RequestException) {
                    return $exception->response?->status() !== 429;
                }

                return true;
            }, throw: false)
            ->withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer '.$apiKey,
            ]);

        $payload = [
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
        ];

        if (! empty($tools)) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        return $request->post('https://api.groq.com/openai/v1/chat/completions', $payload);
    }

    /**
     * Call Groq API with rate-limit retry support for short reset windows.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<int, array<string, mixed>>  $tools
     */
    private function callGroqApiWithRetry(
        string $apiKey,
        string $model,
        array $messages,
        array $tools,
        float $temperature,
        int $maxTokens,
        int $timeout,
    ): Response {
        $maxRetries = 2;
        $response = null;

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            $response = $this->callGroqApi($apiKey, $model, $messages, $tools, $temperature, $maxTokens, $timeout);

            if ($response->status() === 429) {
                $waitTokens = $response->header('x-ratelimit-reset-tokens');
                $waitReqs = $response->header('x-ratelimit-reset-requests');
                $waitTime = $waitTokens ?: ($waitReqs ?: '1s');

                $seconds = $this->parseWaitTimeToSeconds($waitTime);
                // Enforce a minimum sleep buffer of 2.0 seconds to prevent exhausting retries too quickly
                $seconds = max(2.0, $seconds);

                // Only sleep and retry if the wait time is 65 seconds or less and we have attempts remaining
                if ($seconds <= 65.0 && $attempt < $maxRetries) {
                    Log::info("Groq Rate Limit (429) hit. Waiting {$seconds}s before retrying (Attempt ".($attempt + 1)." of {$maxRetries}).");

                    if (! app()->environment('testing')) {
                        $wholeSeconds = (int) $seconds;
                        $fractionalMicroseconds = (int) (($seconds - $wholeSeconds) * 1000000);
                        if ($wholeSeconds > 0) {
                            sleep($wholeSeconds);
                        }
                        if ($fractionalMicroseconds > 0) {
                            usleep($fractionalMicroseconds);
                        }
                    }

                    continue;
                }
            }

            return $response;
        }

        return $response;
    }

    /**
     * Parse wait time formats like: 1ms, 25s, 25.76s, 1m20s into seconds.
     */
    private function parseWaitTimeToSeconds(string $waitTime): float
    {
        $seconds = 0.0;

        if (preg_match('/(\d+)m(?!s)/', $waitTime, $matches)) {
            $seconds += intval($matches[1]) * 60;
            $waitTime = preg_replace('/^\d+m(?!s)/', '', $waitTime);
        }

        if (preg_match('/([\d\.]+)ms/', $waitTime, $matches)) {
            $seconds += floatval($matches[1]) / 1000;
        } elseif (preg_match('/([\d\.]+)s/', $waitTime, $matches)) {
            $seconds += floatval($matches[1]);
        }

        return $seconds > 0.0 ? $seconds : 1.0;
    }

    /**
     * Handle non-successful API responses.
     *
     * @throws \RuntimeException
     */
    private function handleApiError(Response $response): void
    {
        if ($response->status() === 429) {
            $waitTokens = $response->header('x-ratelimit-reset-tokens');
            $waitReqs = $response->header('x-ratelimit-reset-requests');
            $waitTime = $waitTokens ?: ($waitReqs ?: '60s');

            throw new \RuntimeException("Kuota AI sedang penuh. Silakan tunggu {$waitTime} lalu coba lagi.");
        }

        Log::error('Groq API Error', [
            'status' => $response->status(),
            'body' => $response->body(),
        ]);

        throw new \RuntimeException('Gagal terhubung ke layanan AI. Silakan coba lagi.');
    }

    /**
     * Sanitize user message to strip potential prompt injection markers.
     */
    public function sanitizeUserMessage(string $message): string
    {
        $patterns = [
            '/\bignore\s+(all\s+)?(previous|above|prior)\s+(instructions?|prompts?|rules?)\b/i',
            '/\byou\s+are\s+now\b/i',
            '/\bsystem\s*:\s*/i',
            '/\b(SYSTEM|ASSISTANT)\s*PROMPT\b/i',
            '/```[\s\S]*?```/',
        ];

        $sanitized = $message;
        foreach ($patterns as $pattern) {
            $sanitized = preg_replace($pattern, '[FILTERED]', $sanitized);
        }

        return mb_substr(trim($sanitized), 0, 2000);
    }
}
