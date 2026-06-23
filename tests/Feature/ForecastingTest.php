<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ForecastingTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear the cache to prevent test pollution
        cache()->flush();

        $branchId = DB::table('branches')->insertGetId([
            'code' => 'JKT',
            'name' => 'Jakarta',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $salesmanId = DB::table('salesmen')->insertGetId([
            'branch_id' => $branchId,
            'sales_code' => 'S01',
            'name' => 'Sales One',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $outletId = DB::table('outlets')->insertGetId([
            'code' => 'O01',
            'name' => 'Outlet One',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $principalId = DB::table('principals')->insertGetId([
            'code' => 'P01',
            'name' => 'PT. INDOFOOD',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productId = DB::table('products')->insertGetId([
            'principal_id' => $principalId,
            'item_no' => 'PR01',
            'name' => 'Product One',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert historical data across multiple periods for accurate forecasting
        $periods = ['2026-01', '2026-02', '2026-03'];
        $qtys = [100, 120, 140]; // Upward trend

        foreach ($periods as $idx => $period) {
            DB::table('transactions')->insert([
                'branch_id' => $branchId,
                'salesman_id' => $salesmanId,
                'outlet_id' => $outletId,
                'product_id' => $productId,
                'type' => 'I',
                'so_no' => "SO-{$idx}",
                'so_date' => "{$period}-10",
                'qty_base' => $qtys[$idx],
                'price_base' => 1000,
                'gross' => $qtys[$idx] * 1000,
                'disc_total' => 0,
                'taxed_amt' => $qtys[$idx] * 1000,
                'vat' => 0,
                'ar_amt' => $qtys[$idx] * 1000,
                'cogs' => $qtys[$idx] * 800,
                'period' => $period,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function test_salesman_is_blocked_from_forecasting(): void
    {
        $salesman = User::factory()->create(['role' => 'salesman']);

        $response = $this->actingAs($salesman)->get('/inventory/forecast/multi-period');
        $response->assertStatus(403);
    }

    public function test_admin_can_access_forecasting_with_historical_trends(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/inventory/forecast/multi-period');

        $response->assertStatus(200);
        // Should show product name in the listing
        $response->assertSee('Product One');
    }

    public function test_forecasting_csv_export_returns_streamed_file(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Check CSV export format with explicit principal=all
        $response = $this->actingAs($admin)->get('/inventory/forecast/multi-period?export=csv&principal=all');

        $response->assertStatus(200);
        $response->assertHeader('Content-Disposition', 'attachment; filename=MultiPeriodSalesForecast_2026-03_all.csv');

        // Capture streamed response content
        ob_start();
        $response->sendContent();
        $output = ob_get_clean();

        $this->assertStringContainsString('Product One', $output);
    }
}
