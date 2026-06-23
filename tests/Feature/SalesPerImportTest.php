<?php

namespace Tests\Feature;

use App\Models\SalesPerImportLog;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SalesPerImportTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_admin_can_access_imports_index_and_create_pages(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/sales-per/imports');
        $response->assertStatus(200);

        $response = $this->actingAs($admin)->get('/sales-per/imports/create');
        $response->assertStatus(200);
    }

    public function test_salesman_and_supervisor_cannot_access_imports_index_and_create_pages(): void
    {
        $salesman = User::factory()->create(['role' => 'salesman']);
        $supervisor = User::factory()->create(['role' => 'supervisor']);

        $response = $this->actingAs($salesman)->get('/sales-per/imports');
        $response->assertStatus(403);

        $response = $this->actingAs($supervisor)->get('/sales-per/imports');
        $response->assertStatus(403);
    }

    public function test_store_validation_requires_file_period_and_import_mode(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->post('/sales-per/imports', []);
        $response->assertSessionHasErrors(['file', 'period', 'import_mode']);
    }

    public function test_store_validation_fails_with_invalid_period_format(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $file = UploadedFile::fake()->create('sales.xlsx', 100);

        $response = $this->actingAs($admin)->post('/sales-per/imports', [
            'file' => $file,
            'period' => '2026/03', // should be YYYY-MM
            'import_mode' => 'tambah',
        ]);
        $response->assertSessionHasErrors(['period']);
    }

    public function test_admin_can_upload_and_dispatch_sales_per_import(): void
    {
        Queue::fake();

        $admin = User::factory()->create(['role' => 'admin']);
        $file = UploadedFile::fake()->create('sales.xlsx', 100);

        $response = $this->actingAs($admin)->post('/sales-per/imports', [
            'file' => $file,
            'period' => '2026-03',
            'import_mode' => 'tambah',
        ]);

        $response->assertRedirect('/sales-per/imports');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('sales_per_import_logs', [
            'user_id' => $admin->id,
            'filename' => 'sales.xlsx',
            'period' => '2026-03',
            'status' => 'pending',
        ]);
    }

    public function test_destroy_removes_log_and_all_associated_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $logId = DB::table('sales_per_import_logs')->insertGetId([
            'user_id' => $admin->id,
            'filename' => 'sales.xlsx',
            'period' => '2026-03',
            'status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('sales_per_transactions')->insert([
            'sales_per_import_log_id' => $logId,
            'type' => 'I',
            'branch_code' => 'JKT',
            'sales_code' => 'S01',
            'sales_name' => 'SALES ONE',
            'outlet_code' => 'O01',
            'outlet_name' => 'OUTLET ONE',
            'principal_code' => 'P01',
            'principal_name' => 'PRINCIPAL ONE',
            'item_no' => 'I01',
            'item_name' => 'ITEM ONE',
            'qty' => 5,
            'subtotal' => 50000,
            'vat' => 0,
            'period' => '2026-03',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('sales_per_stocks')->insert([
            'sales_per_import_log_id' => $logId,
            'principal_code' => 'P01',
            'principal_name' => 'PRINCIPAL ONE',
            'warehouse_code' => 'W01',
            'warehouse_name' => 'WH ONE',
            'item_no' => 'I01',
            'item_name' => 'ITEM ONE',
            'size' => 'PCS',
            'on_hand_base' => 10,
            'on_sales_base' => 10,
            'stock_value_on_hand' => 10000,
            'stock_value_on_sales' => 10000,
            'was' => 1.0,
            'swc' => 1.0,
            'age_of_goods' => 10,
            'period' => '2026-03',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $importLog = SalesPerImportLog::findOrFail($logId);

        $response = $this->actingAs($admin)->delete("/sales-per/imports/{$logId}");

        $response->assertRedirect('/sales-per/imports');
        $response->assertSessionHas('success');

        // Verify clean deletion
        $this->assertDatabaseMissing('sales_per_import_logs', ['id' => $logId]);
        $this->assertDatabaseMissing('sales_per_transactions', ['sales_per_import_log_id' => $logId]);
        $this->assertDatabaseMissing('sales_per_stocks', ['sales_per_import_log_id' => $logId]);
    }
}
