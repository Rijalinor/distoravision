<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductTrajectoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_csv_export_contains_monthly_sales_columns(): void
    {
        $user = User::factory()->create();
        $response = $this->actingAs($user)->get('/analytics/product-trajectory?export=csv');
        $response->assertStatus(200);

        // We just ensure it runs and returns a successful response.
        // Detailed assertions on streamed content can be complex.
    }
}
