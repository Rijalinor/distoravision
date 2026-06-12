<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiChatTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_ai_chat_page_loads_successfully(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/ai-chat');

        $response->assertStatus(200);
        $response->assertSee('Distora AI Assistant');
    }

    public function test_ai_chat_ask_requires_valid_history(): void
    {
        $user = User::factory()->create();

        // Missing history
        $response = $this->actingAs($user)->postJson('/ai-chat/ask', []);
        $response->assertStatus(422);
        $response->assertJsonValidationErrors('history');
    }

    public function test_ai_chat_ask_rejects_invalid_role(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/ai-chat/ask', [
            'history' => [
                ['role' => 'system', 'content' => 'hacked!'],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('history.0.role');
    }

    public function test_ai_chat_ask_rejects_content_exceeding_max_length(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/ai-chat/ask', [
            'history' => [
                ['role' => 'user', 'content' => str_repeat('a', 2001)],
            ],
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('history.0.content');
    }

    public function test_ai_chat_ask_returns_error_when_api_key_missing(): void
    {
        $user = User::factory()->create();

        config(['services.groq.api_key' => null]);

        $response = $this->actingAs($user)->postJson('/ai-chat/ask', [
            'history' => [
                ['role' => 'user', 'content' => 'Halo'],
            ],
        ]);

        $response->assertStatus(400);
        $response->assertJson(['error' => 'Groq API Key belum dikonfigurasi di file .env (GROQ_API_KEY).']);
    }

    public function test_ai_chat_ask_returns_reply_on_success(): void
    {
        $user = User::factory()->create();

        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Jawaban dari AI']],
                ],
            ], 200, ['x-ratelimit-remaining-tokens' => '5000']),
        ]);

        $response = $this->actingAs($user)->postJson('/ai-chat/ask', [
            'history' => [
                ['role' => 'user', 'content' => 'Apa produk terlaris?'],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJsonStructure(['reply', 'remaining_tokens']);
        $response->assertJson(['reply' => 'Jawaban dari AI']);
    }

    public function test_ai_chat_executes_tool_call_and_returns_final_reply(): void
    {
        $user = User::factory()->create(['role' => 'admin']);

        $branchId = DB::table('branches')->insertGetId([
            'code' => 'JKT',
            'name' => 'Jakarta',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $principalId = DB::table('principals')->insertGetId([
            'code' => 'FOOD',
            'name' => 'Food Principal',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $productId = DB::table('products')->insertGetId([
            'principal_id' => $principalId,
            'item_no' => 'IND-001',
            'name' => 'INDOMIE GORENG SPECIAL',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $outletId = DB::table('outlets')->insertGetId([
            'code' => 'SJ-01',
            'name' => 'SUMBER JAYA',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $salesmanId = DB::table('salesmen')->insertGetId([
            'branch_id' => $branchId,
            'sales_code' => 'S001',
            'name' => 'BUDI',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('transactions')->insert([
            'branch_id' => $branchId,
            'salesman_id' => $salesmanId,
            'outlet_id' => $outletId,
            'product_id' => $productId,
            'type' => 'I',
            'so_no' => 'SO-001',
            'so_date' => '2026-03-10',
            'qty_base' => 10,
            'price_base' => 1250000,
            'gross' => 12500000,
            'disc_total' => 0,
            'taxed_amt' => 12500000,
            'vat' => 0,
            'ar_amt' => 12500000,
            'cogs' => 8000000,
            'period' => '2026-03',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Http::fake([
            'api.groq.com/*' => Http::sequence()
                ->push([
                    'choices' => [[
                        'finish_reason' => 'tool_calls',
                        'message' => [
                            'role' => 'assistant',
                            'tool_calls' => [[
                                'id' => 'call_1',
                                'type' => 'function',
                                'function' => [
                                    'name' => 'get_filtered_sales',
                                    'arguments' => json_encode([
                                        'product' => 'Indomie',
                                        'outlet' => 'Sumber Jaya',
                                        'period' => '2026-03',
                                    ]),
                                ],
                            ]],
                        ],
                    ]],
                ], 200)
                ->push([
                    'choices' => [[
                        'finish_reason' => 'stop',
                        'message' => ['content' => 'Penjualan Indomie di Sumber Jaya Maret 2026 adalah Rp 12.500.000.'],
                    ]],
                ], 200, ['x-ratelimit-remaining-tokens' => '4000']),
        ]);

        $response = $this->actingAs($user)->postJson('/ai-chat/ask', [
            'history' => [
                ['role' => 'user', 'content' => 'Berapa penjualan Indomie di toko Sumber Jaya bulan Maret 2026?'],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['reply' => 'Penjualan Indomie di Sumber Jaya Maret 2026 adalah Rp 12.500.000.']);
        Http::assertSentCount(2);

        $secondRequestPayload = Http::recorded()[1][0]->data();
        $toolMessage = collect($secondRequestPayload['messages'])->firstWhere('role', 'tool');

        $this->assertNotNull($toolMessage);
        $this->assertStringContainsString('"net_sales":12500000', $toolMessage['content']);
    }

    public function test_ai_chat_ask_handles_rate_limit_error(): void
    {
        $user = User::factory()->create();

        Http::fake([
            'api.groq.com/*' => Http::response([
                'error' => ['message' => 'Rate limit exceeded'],
            ], 429, ['x-ratelimit-reset-tokens' => '30s']),
        ]);

        $response = $this->actingAs($user)->postJson('/ai-chat/ask', [
            'history' => [
                ['role' => 'user', 'content' => 'Apa produk terlaris?'],
            ],
        ]);

        $response->assertStatus(429);
        $response->assertJsonFragment(['error' => 'Kuota AI sedang penuh. Silakan tunggu 30s lalu coba lagi.']);
    }

    public function test_ai_chat_ask_retries_on_short_rate_limit_error(): void
    {
        $user = User::factory()->create();

        Http::fake([
            'api.groq.com/*' => Http::sequence()
                ->push([
                    'error' => ['message' => 'Rate limit exceeded'],
                ], 429, ['x-ratelimit-reset-tokens' => '1ms'])
                ->push([
                    'choices' => [
                        ['message' => ['content' => 'Jawaban setelah retry']],
                    ],
                ], 200, ['x-ratelimit-remaining-tokens' => '5000']),
        ]);

        $response = $this->actingAs($user)->postJson('/ai-chat/ask', [
            'history' => [
                ['role' => 'user', 'content' => 'Apa produk terlaris?'],
            ],
        ]);

        $response->assertStatus(200);
        $response->assertJson(['reply' => 'Jawaban setelah retry']);
    }

    public function test_ai_chat_ask_handles_server_error(): void
    {
        $user = User::factory()->create();

        Http::fake([
            'api.groq.com/*' => Http::response('Server Error', 500),
        ]);

        $response = $this->actingAs($user)->postJson('/ai-chat/ask', [
            'history' => [
                ['role' => 'user', 'content' => 'Apa produk terlaris?'],
            ],
        ]);

        $response->assertStatus(502);
    }

    public function test_ai_chat_requires_authentication(): void
    {
        $response = $this->postJson('/ai-chat/ask', [
            'history' => [
                ['role' => 'user', 'content' => 'Halo'],
            ],
        ]);

        $response->assertStatus(401);
    }

    public function test_ai_chat_ask_limits_history_size(): void
    {
        $user = User::factory()->create();

        // 21 messages exceeds max:20
        $history = [];
        for ($i = 0; $i < 21; $i++) {
            $history[] = ['role' => 'user', 'content' => 'Message '.$i];
        }

        $response = $this->actingAs($user)->postJson('/ai-chat/ask', [
            'history' => $history,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('history');
    }

    public function test_ai_chat_ask_accepts_custom_model_parameter(): void
    {
        $user = User::factory()->create();

        Http::fake([
            'api.groq.com/*' => Http::response([
                'choices' => [
                    ['message' => ['content' => 'Jawaban dari AI']],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->postJson('/ai-chat/ask', [
            'history' => [
                ['role' => 'user', 'content' => 'Halo'],
            ],
            'model' => 'llama-3.1-8b-instant',
        ]);

        $response->assertStatus(200);

        $requestPayload = Http::recorded()[0][0]->data();
        $this->assertEquals('llama-3.1-8b-instant', $requestPayload['model']);
    }
}
