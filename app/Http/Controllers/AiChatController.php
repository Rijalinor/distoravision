<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\GroqChatService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Log;

class AiChatController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware(function ($request, $next) {
                abort_if(auth()->check() && auth()->user()->isSalesman(), 403, 'Akses ditolak. Salesman tidak dapat mengakses Asisten AI.');

                return $next($request);
            }),
        ];
    }

    public function __construct(
        private GroqChatService $chatService,
    ) {}

    public function index()
    {
        return view('ai-chat.index');
    }

    public function ask(Request $request)
    {
        $validated = $request->validate([
            'history' => ['required', 'array', 'max:20'],
            'history.*.role' => ['required', 'in:user,assistant'],
            'history.*.content' => ['required', 'string', 'max:2000'],
            'model' => ['nullable', 'string', 'in:llama-3.1-8b-instant'],
        ]);

        try {
            // Get current period for context
            $currentPeriod = Transaction::max('period') ?? date('Y-m');

            // Send to LLM (which will handle the tool call loop internally)
            $result = $this->chatService->chat(
                $validated['history'],
                $currentPeriod,
                $validated['model'] ?? null
            );

            return response()->json($result);
        } catch (\RuntimeException $e) {
            $message = $e->getMessage();
            $statusCode = match (true) {
                str_contains($message, 'Kuota AI') => 429,
                str_contains($message, 'Gagal terhubung') => 502,
                default => 400,
            };

            return response()->json(['error' => $e->getMessage()], $statusCode);
        } catch (\Exception $e) {
            Log::error('AI Chat Error', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);

            return response()->json(['error' => 'Terjadi kesalahan sistem saat menghubungi AI.'], 500);
        }
    }
}
