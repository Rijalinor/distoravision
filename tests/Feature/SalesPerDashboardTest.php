<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SalesPerDashboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed basic sales_per transactions
        $logId = DB::table('sales_per_import_logs')->insertGetId([
            'filename' => 'sales.xlsx',
            'period' => '2026-03',
            'status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Invoice row (30,000,000)
        DB::table('sales_per_transactions')->insert([
            'sales_per_import_log_id' => $logId,
            'type' => 'I',
            'branch_code' => 'JKT',
            'sales_code' => 'S01',
            'sales_name' => 'SALES ONE',
            'outlet_code' => 'O01',
            'outlet_name' => 'OUTLET ONE',
            'principal_code' => 'P01',
            'principal_name' => 'PT. INDOFOOD',
            'item_no' => 'I01',
            'item_name' => 'Indomie Goreng',
            'qty' => 10,
            'subtotal' => 30000000,
            'vat' => 3300000,
            'period' => '2026-03',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Return row (6,000,000)
        DB::table('sales_per_transactions')->insert([
            'sales_per_import_log_id' => $logId,
            'type' => 'R',
            'branch_code' => 'JKT',
            'sales_code' => 'S01',
            'sales_name' => 'SALES ONE',
            'outlet_code' => 'O01',
            'outlet_name' => 'OUTLET ONE',
            'principal_code' => 'P01',
            'principal_name' => 'PT. INDOFOOD',
            'item_no' => 'I01',
            'item_name' => 'Indomie Goreng',
            'qty' => 2,
            'subtotal' => 6000000,
            'vat' => 660000,
            'period' => '2026-03',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_salesman_is_blocked_from_sales_per_dashboard(): void
    {
        $salesman = User::factory()->create(['role' => 'salesman']);

        $response = $this->actingAs($salesman)->get('/sales-per/dashboard');
        $response->assertStatus(403);
    }

    public function test_admin_can_view_sales_per_dashboard_with_correct_kpis(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/sales-per/dashboard?period=2026-03');

        $response->assertStatus(200);
        $response->assertSee('PT. INDOFOOD');
        $response->assertSee('SALES ONE');
        // Overall Sales: 30,000,000 -> 30,0Jt
        $response->assertSee('30,0Jt');
        // Overall Returns: 6,000,000 -> 6,0Jt
        $response->assertSee('6,0Jt');
        // Net: 24,000,000 -> 24,0Jt
        $response->assertSee('24,0Jt');
    }

    public function test_dashboard_filters_by_principal(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Filter by existing principal
        $response = $this->actingAs($admin)->get('/sales-per/dashboard?period=2026-03&principal=PT.+INDOFOOD');
        $response->assertStatus(200);
        $response->assertSee('SALES ONE');

        // Filter by non-existent principal
        $response = $this->actingAs($admin)->get('/sales-per/dashboard?period=2026-03&principal=PT.+MAYORA');
        $response->assertStatus(200);
        // Should not see Indofood transaction sums (should show 0,0Jt)
        $response->assertSee('0,0Jt');
    }

    public function test_dashboard_salesman_detail_drill_down(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/sales-per/dashboard?period=2026-03&salesman=S01');

        $response->assertStatus(200);
        $response->assertSee('SALES ONE');
        $response->assertSee('Indomie Goreng');
        $response->assertSee('OUTLET ONE');
    }
}
