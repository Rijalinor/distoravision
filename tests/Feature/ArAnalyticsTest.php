<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ArAnalyticsTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected int $logId;

    protected function setUp(): void
    {
        parent::setUp();

        $admin = User::factory()->create(['role' => 'admin']);

        $this->logId = DB::table('ar_import_logs')->insertGetId([
            'user_id' => $admin->id,
            'filename' => 'ar.xlsx',
            'report_date' => '2026-03-31',
            'status' => 'completed',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Insert receivables with different overdue days
        DB::table('ar_receivables')->insert([
            [
                'ar_import_log_id' => $this->logId,
                'outlet_code' => 'O01',
                'outlet_name' => 'OUTLET ONE',
                'outlet_ref' => 'REF01',
                'supervisor' => 'SPV1',
                'salesman_code' => 'S01',
                'salesman_name' => 'SALES ONE',
                'principal_code' => 'P01',
                'principal_name' => 'PT. INDOFOOD',
                'pfi_sn' => 'PFI01',
                'doc_date' => '2026-03-01',
                'due_date' => '2026-03-31',
                'top' => 30,
                'si_cn' => 'SI01',
                'cm' => 0,
                'cn_balance' => 0,
                'ar_amount' => 1000000,
                'ar_paid' => 200000,
                'ar_balance' => 800000, // Outstanding, overdue 0 days (Current)
                'credit_limit' => 5000000,
                'overdue_days' => 0,
                'branch_sheet' => 'JKT',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function test_admin_can_access_ar_dashboard_with_correct_aging_buckets(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $response = $this->actingAs($admin)->get('/ar/dashboard');

        $response->assertStatus(200);
        $response->assertSee(number_format(800000, 0, ',', '.'));
    }

    public function test_salesman_can_access_ar_dashboard(): void
    {
        $salesman = User::factory()->create(['role' => 'salesman']);

        $response = $this->actingAs($salesman)->get('/ar/dashboard');
        $response->assertStatus(200);
    }

    public function test_ar_import_log_can_be_deleted(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->assertDatabaseHas('ar_import_logs', ['id' => $this->logId]);
        $this->assertDatabaseHas('ar_receivables', ['ar_import_log_id' => $this->logId]);

        $response = $this->actingAs($admin)->delete("/ar/imports/{$this->logId}");

        $response->assertRedirect('/ar/imports');
        $this->assertDatabaseMissing('ar_import_logs', ['id' => $this->logId]);
        $this->assertDatabaseMissing('ar_receivables', ['ar_import_log_id' => $this->logId]);
    }
}
