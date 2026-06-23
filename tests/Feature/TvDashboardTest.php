<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TvDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_tv_dashboard_renders_successfully_for_authenticated_user(): void
    {
        $this->seed(DemoDataSeeder::class);

        $user = User::where('email', 'demo@admin.com')->first();

        $response = $this->actingAs($user)->get('/tv-dashboard');

        $response->assertStatus(200);
        $response->assertSee('DV | COMMAND CENTER');
        $response->assertSee('Global Performance');
        $response->assertSee('Top 5 Principal');
    }
}
