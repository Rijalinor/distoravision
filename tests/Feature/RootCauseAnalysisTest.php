<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RootCauseAnalysisTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_cause_page_loads_without_data(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/analytics/root-cause');

        $response->assertOk();
        $response->assertSee('Root Cause Analysis');
    }
}
