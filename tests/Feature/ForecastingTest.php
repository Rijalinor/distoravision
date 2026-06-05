<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ForecastingTest extends TestCase
{
    use RefreshDatabase;

    public function test_multi_period_forecast_loads_successfully(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/inventory/forecast/multi-period');
        $response->assertStatus(200);
    }
}
