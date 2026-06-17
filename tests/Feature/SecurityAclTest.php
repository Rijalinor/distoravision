<?php

namespace Tests\Feature;

use App\Models\Outlet;
use App\Models\Principal;
use App\Models\SalesPerStock;
use App\Models\SalesPerTransaction;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SecurityAclTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_sales_per_transaction_acl_scope_for_salesman(): void
    {
        $branchId = DB::table('branches')->insertGetId([
            'code' => 'JKT',
            'name' => 'Jakarta',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $salesman1Id = DB::table('salesmen')->insertGetId([
            'branch_id' => $branchId,
            'sales_code' => 'S001',
            'name' => 'SALES ONE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $salesman2Id = DB::table('salesmen')->insertGetId([
            'branch_id' => $branchId,
            'sales_code' => 'S002',
            'name' => 'SALES TWO',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $user = User::factory()->create([
            'role' => 'salesman',
            'salesman_id' => $salesman1Id,
        ]);

        $logId = DB::table('sales_per_import_logs')->insertGetId([
            'filename' => 'test.xlsx',
            'period' => '2026-03',
            'status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert transaction for salesman 1
        DB::table('sales_per_transactions')->insert([
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
            'qty' => 10,
            'subtotal' => 100000,
            'vat' => 0,
            'period' => '2026-03',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert transaction for salesman 2
        DB::table('sales_per_transactions')->insert([
            'sales_per_import_log_id' => $logId,
            'type' => 'I',
            'branch_code' => 'JKT',
            'sales_code' => 'S002',
            'sales_name' => 'SALES TWO',
            'outlet_code' => 'O001',
            'outlet_name' => 'OUTLET ONE',
            'principal_code' => 'P001',
            'principal_name' => 'PRINCIPAL ONE',
            'item_no' => 'I001',
            'item_name' => 'ITEM ONE',
            'qty' => 10,
            'subtotal' => 100000,
            'vat' => 0,
            'period' => '2026-03',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Under salesman 1 context, should only see S001 transaction
        $this->actingAs($user);

        $this->assertEquals(1, SalesPerTransaction::count());
        $this->assertEquals('S001', SalesPerTransaction::first()->sales_code);
    }

    public function test_sales_per_transaction_acl_scope_for_supervisor(): void
    {
        $user = User::factory()->create([
            'role' => 'supervisor',
        ]);

        $principal1Id = DB::table('principals')->insertGetId([
            'code' => 'P001',
            'name' => 'PRINCIPAL ONE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $principal2Id = DB::table('principals')->insertGetId([
            'code' => 'P002',
            'name' => 'PRINCIPAL TWO',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Assign only principal 1 to supervisor
        DB::table('principal_user')->insert([
            'user_id' => $user->id,
            'principal_id' => $principal1Id,
        ]);

        $logId = DB::table('sales_per_import_logs')->insertGetId([
            'filename' => 'test.xlsx',
            'period' => '2026-03',
            'status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Transaction for Principal 1
        DB::table('sales_per_transactions')->insert([
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
            'qty' => 10,
            'subtotal' => 100000,
            'vat' => 0,
            'period' => '2026-03',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Transaction for Principal 2
        DB::table('sales_per_transactions')->insert([
            'sales_per_import_log_id' => $logId,
            'type' => 'I',
            'branch_code' => 'JKT',
            'sales_code' => 'S001',
            'sales_name' => 'SALES ONE',
            'outlet_code' => 'O001',
            'outlet_name' => 'OUTLET ONE',
            'principal_code' => 'P002',
            'principal_name' => 'PRINCIPAL TWO',
            'item_no' => 'I002',
            'item_name' => 'ITEM TWO',
            'qty' => 10,
            'subtotal' => 100000,
            'vat' => 0,
            'period' => '2026-03',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user);

        $this->assertEquals(1, SalesPerTransaction::count());
        $this->assertEquals('PRINCIPAL ONE', SalesPerTransaction::first()->principal_name);
    }

    public function test_sales_per_stock_acl_scope_for_supervisor(): void
    {
        $user = User::factory()->create([
            'role' => 'supervisor',
        ]);

        $principal1Id = DB::table('principals')->insertGetId([
            'code' => 'P001',
            'name' => 'PRINCIPAL ONE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $principal2Id = DB::table('principals')->insertGetId([
            'code' => 'P002',
            'name' => 'PRINCIPAL TWO',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Assign only principal 1 to supervisor
        DB::table('principal_user')->insert([
            'user_id' => $user->id,
            'principal_id' => $principal1Id,
        ]);

        $logId = DB::table('sales_per_import_logs')->insertGetId([
            'filename' => 'test.xlsx',
            'period' => '2026-03',
            'status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Stock for Principal 1
        DB::table('sales_per_stocks')->insert([
            'sales_per_import_log_id' => $logId,
            'principal_code' => 'P001',
            'principal_name' => 'PRINCIPAL ONE',
            'warehouse_code' => 'W001',
            'warehouse_name' => 'WH ONE',
            'item_no' => 'I001',
            'item_name' => 'ITEM ONE',
            'size' => 'PCS',
            'on_hand_base' => 10,
            'on_sales_base' => 10,
            'stock_value_on_hand' => 10000,
            'stock_value_on_sales' => 10000,
            'was' => 1.0,
            'swc' => 1.0,
            'age_of_goods' => '0-30',
            'period' => '2026-03',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Stock for Principal 2
        DB::table('sales_per_stocks')->insert([
            'sales_per_import_log_id' => $logId,
            'principal_code' => 'P002',
            'principal_name' => 'PRINCIPAL TWO',
            'warehouse_code' => 'W001',
            'warehouse_name' => 'WH ONE',
            'item_no' => 'I002',
            'item_name' => 'ITEM TWO',
            'size' => 'PCS',
            'on_hand_base' => 10,
            'on_sales_base' => 10,
            'stock_value_on_hand' => 10000,
            'stock_value_on_sales' => 10000,
            'was' => 1.0,
            'swc' => 1.0,
            'age_of_goods' => '0-30',
            'period' => '2026-03',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($user);

        $this->assertEquals(1, SalesPerStock::count());
        $this->assertEquals('PRINCIPAL ONE', SalesPerStock::first()->principal_name);
    }

    public function test_supervisor_cannot_view_unassigned_principal_details(): void
    {
        $user = User::factory()->create([
            'role' => 'supervisor',
        ]);

        $principal1Id = DB::table('principals')->insertGetId([
            'code' => 'P001',
            'name' => 'PRINCIPAL ONE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $principal2Id = DB::table('principals')->insertGetId([
            'code' => 'P002',
            'name' => 'PRINCIPAL TWO',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Assign only principal 1
        DB::table('principal_user')->insert([
            'user_id' => $user->id,
            'principal_id' => $principal1Id,
        ]);

        // Access principal 1: OK
        $response = $this->actingAs($user)->get("/principals/{$principal1Id}");
        $response->assertStatus(200);

        // Access principal 2: Forbidden
        $response = $this->actingAs($user)->get("/principals/{$principal2Id}");
        $response->assertStatus(403);
    }

    public function test_salesman_cannot_view_unassociated_outlet_details(): void
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

        $outlet1Id = DB::table('outlets')->insertGetId([
            'code' => 'O001',
            'name' => 'OUTLET ONE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $outlet2Id = DB::table('outlets')->insertGetId([
            'code' => 'O002',
            'name' => 'OUTLET TWO',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $principalId = DB::table('principals')->insertGetId([
            'code' => 'P001',
            'name' => 'PRINCIPAL ONE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $productId = DB::table('products')->insertGetId([
            'principal_id' => $principalId,
            'item_no' => 'I001',
            'name' => 'ITEM ONE',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert transaction for salesman S001 with Outlet 1
        DB::table('transactions')->insert([
            'branch_id' => $branchId,
            'salesman_id' => $salesmanId,
            'outlet_id' => $outlet1Id,
            'product_id' => $productId,
            'type' => 'I',
            'so_no' => 'SO-001',
            'so_date' => '2026-03-10',
            'qty_base' => 10,
            'price_base' => 10000,
            'gross' => 100000,
            'disc_total' => 0,
            'taxed_amt' => 100000,
            'vat' => 0,
            'ar_amt' => 100000,
            'cogs' => 80000,
            'period' => '2026-03',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Access outlet 1 (has transaction): OK
        $response = $this->actingAs($user)->get("/outlets/{$outlet1Id}");
        $response->assertStatus(200);

        // Access outlet 2 (no transactions): Forbidden
        $response = $this->actingAs($user)->get("/outlets/{$outlet2Id}");
        $response->assertStatus(403);
    }

    public function test_salesman_accessing_dashboard_redirects_to_personal_dashboard(): void
    {
        $user = User::factory()->create([
            'role' => 'salesman',
        ]);

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertRedirect('/my-dashboard');
    }

    public function test_salesman_accessing_sales_per_dashboard_is_forbidden(): void
    {
        $user = User::factory()->create([
            'role' => 'salesman',
        ]);

        $response = $this->actingAs($user)->get('/sales-per/dashboard');
        $response->assertStatus(403);
    }

    public function test_salesman_accessing_stock_tab_tertahan_is_forbidden(): void
    {
        $user = User::factory()->create([
            'role' => 'salesman',
        ]);

        $response = $this->actingAs($user)->get('/sales-per/stock/tab-tertahan');
        $response->assertStatus(403);
    }

    public function test_salesman_accessing_salesmen_index_redirects_with_warning(): void
    {
        $user = User::factory()->create([
            'role' => 'salesman',
            'salesman_id' => null,
        ]);

        $response = $this->actingAs($user)->get('/salesmen');
        $response->assertRedirect('/my-dashboard');
        $response->assertSessionHas('error', 'Profil salesman Anda belum dikaitkan.');
    }

    public function test_supervisor_accessing_dashboard_sees_only_assigned_principals(): void
    {
        $user = User::factory()->create([
            'role' => 'supervisor',
        ]);

        $principal1Id = DB::table('principals')->insertGetId([
            'code' => 'P001',
            'name' => 'ASSIGNED PRINCIPAL',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $principal2Id = DB::table('principals')->insertGetId([
            'code' => 'P002',
            'name' => 'UNASSIGNED PRINCIPAL',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('principal_user')->insert([
            'user_id' => $user->id,
            'principal_id' => $principal1Id,
        ]);

        $branchId = DB::table('branches')->insertGetId([
            'code' => 'B01',
            'name' => 'Branch One',
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

        $product1Id = DB::table('products')->insertGetId([
            'principal_id' => $principal1Id,
            'item_no' => 'PR01',
            'name' => 'Product One',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $product2Id = DB::table('products')->insertGetId([
            'principal_id' => $principal2Id,
            'item_no' => 'PR02',
            'name' => 'Product Two',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert transactions for both so they would normally both show in the dropdown.
        DB::table('transactions')->insert([
            [
                'branch_id' => $branchId,
                'salesman_id' => $salesmanId,
                'outlet_id' => $outletId,
                'product_id' => $product1Id,
                'type' => 'I',
                'so_no' => 'SO-001',
                'so_date' => '2026-03-10',
                'qty_base' => 10,
                'price_base' => 10000,
                'gross' => 100000,
                'disc_total' => 0,
                'taxed_amt' => 100000,
                'vat' => 0,
                'ar_amt' => 100000,
                'cogs' => 80000,
                'period' => '2026-03',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'branch_id' => $branchId,
                'salesman_id' => $salesmanId,
                'outlet_id' => $outletId,
                'product_id' => $product2Id,
                'type' => 'I',
                'so_no' => 'SO-002',
                'so_date' => '2026-03-10',
                'qty_base' => 10,
                'price_base' => 10000,
                'gross' => 100000,
                'disc_total' => 0,
                'taxed_amt' => 100000,
                'vat' => 0,
                'ar_amt' => 100000,
                'cogs' => 80000,
                'period' => '2026-03',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        $response = $this->actingAs($user)->get('/dashboard');
        $response->assertStatus(200);
        $response->assertSee('ASSIGNED PRINCIPAL');
        $response->assertDontSee('UNASSIGNED PRINCIPAL');
    }

    public function test_salesman_accessing_stock_page_does_not_see_valuation_columns(): void
    {
        $user = User::factory()->create([
            'role' => 'salesman',
        ]);

        $response = $this->actingAs($user)->get('/sales-per/stock');
        $response->assertStatus(200);
        $response->assertDontSee('Nilai Stok');
        $response->assertDontSee('Alokasi Modal Stok');
        $response->assertDontSee('Modal Tertahan');
    }
}
