<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SalesmanDashboardTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_salesman_dashboard_uses_sales_per_transaction_data(): void
    {
        $branchId = DB::table('branches')->insertGetId([
            'code' => 'JKT',
            'name' => 'Jakarta',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $salesmanId = DB::table('salesmen')->insertGetId([
            'branch_id' => $branchId,
            'sales_code' => 'S001',
            'name' => 'SALES ONE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
            'role' => 'salesman',
            'salesman_id' => $salesmanId,
        ]);

        $logId = DB::table('sales_per_import_logs')->insertGetId([
            'filename' => 'test.xlsx',
            'period' => '2026-03',
            'status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert daily transactions in sales_per_transactions
        DB::table('sales_per_transactions')->insert([
            [
                'sales_per_import_log_id' => $logId,
                'type' => 'I',
                'branch_code' => 'JKT',
                'sales_code' => 'S001',
                'sales_name' => 'SALES ONE',
                'outlet_code' => 'O001',
                'outlet_name' => 'OUTLET ONE',
                'principal_code' => 'P001',
                'principal_name' => 'PRINCIPAL ONE',
                'item_no' => 'I001',
                'item_name' => 'ITEM ONE',
                'so_no' => 'SO-001',
                'pfi_no' => 'PFI-001',
                'so_date' => '2026-03-10',
                'qty' => 10,
                'subtotal' => 150000.00,
                'vat' => 0,
                'period' => '2026-03',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'sales_per_import_log_id' => $logId,
                'type' => 'R',
                'branch_code' => 'JKT',
                'sales_code' => 'S001',
                'sales_name' => 'SALES ONE',
                'outlet_code' => 'O001',
                'outlet_name' => 'OUTLET ONE',
                'principal_code' => 'P001',
                'principal_name' => 'PRINCIPAL ONE',
                'item_no' => 'I001',
                'item_name' => 'ITEM ONE',
                'so_no' => 'SO-002',
                'pfi_no' => 'PFI-002',
                'so_date' => '2026-03-12',
                'qty' => 2,
                'subtotal' => 30000.00,
                'vat' => 0,
                'period' => '2026-03',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($user)->get('/my-dashboard?period=2026-03');

        $response->assertStatus(200);

        // Check view data values
        $response->assertViewHas('totalSales', 150000.00);
        $response->assertViewHas('totalReturns', 30000.00);
        $response->assertViewHas('netSales', 120000.00);
        $response->assertViewHas('invoiceCount', 1); // 1 invoice
        $response->assertViewHas('outletCount', 1); // 1 unique outlet

        $response->assertViewHas('recentInvoices');
        $recentInvoices = $response->viewData('recentInvoices');
        $this->assertCount(2, $recentInvoices);

        $firstInvoice = $recentInvoices->first(fn ($i) => $i->so_no === 'SO-001');
        $this->assertNotNull($firstInvoice);
        $this->assertEquals(150000.00, $firstInvoice->total_value);
        $this->assertEquals(10, $firstInvoice->total_qty);
        $this->assertCount(1, $firstInvoice->items);

        // Check target analysis rendering
        $response->assertSee('Pencapaian target Anda saat ini');
        $response->assertSee('Mari tingkatkan penjualan');
    }
}
