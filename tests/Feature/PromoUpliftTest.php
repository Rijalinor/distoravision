<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PromoUpliftTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that authenticated users can access the promo uplift page.
     */
    public function test_promo_uplift_page_loads_successfully(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('analytics.promo-uplift'));

        $response->assertStatus(200);
        $response->assertViewIs('analytics.promo-uplift');
        $response->assertViewHas(['results', 'chartData', 'successCount', 'failCount', 'aiNarrative']);
    }

    /**
     * Test that unauthenticated users are redirected to login.
     */
    public function test_promo_uplift_requires_authentication(): void
    {
        $response = $this->get(route('analytics.promo-uplift'));

        $response->assertRedirect(route('login'));
    }
}
