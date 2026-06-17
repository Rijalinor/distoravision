<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrincipalAclTest extends TestCase
{
    use RefreshDatabase;

    public function test_salesman_cannot_access_principals_index(): void
    {
        $salesman = User::factory()->create([
            'role' => 'salesman',
        ]);

        $response = $this->actingAs($salesman)->get('/principals');

        $response->assertStatus(403);
    }
}
