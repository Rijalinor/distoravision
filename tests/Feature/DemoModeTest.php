<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\ImportLog;
use App\Models\Outlet;
use App\Models\Principal;
use App\Models\Product;
use App\Models\Salesman;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_user_only_sees_demo_data_and_regular_user_only_sees_regular_data(): void
    {
        // 1. Create a regular user and a demo user
        $regularUser = User::factory()->create([
            'email' => 'admin@admin.com',
            'role' => 'admin',
        ]);

        $demoUser = User::factory()->create([
            'email' => 'demo@admin.com',
            'role' => 'admin',
        ]);

        // 2. Setup master data
        $branch = Branch::create(['code' => 'BJM', 'name' => 'Banjarmasin']);
        $principal = Principal::create(['code' => 'P01', 'name' => 'Nestle']);
        $salesman = Salesman::create([
            'branch_id' => $branch->id,
            'sales_code' => 'SL01',
            'name' => 'Sales BJM',
        ]);
        $outlet = Outlet::create([
            'code' => 'OT001',
            'name' => 'Toko Rejeki',
        ]);
        $product = Product::create([
            'principal_id' => $principal->id,
            'item_no' => 'P001',
            'name' => 'Dancow',
        ]);

        // 3. Create demo import log and demo transaction
        $demoLog = ImportLog::create([
            'user_id' => $demoUser->id,
            'filename' => 'demo_sales.xlsx',
            'period' => '2026-06',
            'status' => 'completed',
        ]);

        $demoTx = Transaction::create([
            'branch_id' => $branch->id,
            'salesman_id' => $salesman->id,
            'outlet_id' => $outlet->id,
            'product_id' => $product->id,
            'type' => 'I',
            'period' => '2026-06',
            'import_log_id' => $demoLog->id,
        ]);

        // 4. Create regular import log and regular transaction
        $regularLog = ImportLog::create([
            'user_id' => $regularUser->id,
            'filename' => 'real_sales.xlsx',
            'period' => '2026-06',
            'status' => 'completed',
        ]);

        $regularTx = Transaction::create([
            'branch_id' => $branch->id,
            'salesman_id' => $salesman->id,
            'outlet_id' => $outlet->id,
            'product_id' => $product->id,
            'type' => 'I',
            'period' => '2026-06',
            'import_log_id' => $regularLog->id,
        ]);

        // 5. Test scope as Demo User
        $this->actingAs($demoUser);

        // Fetch logs
        $logsForDemo = ImportLog::all();
        $this->assertCount(1, $logsForDemo);
        $this->assertEquals($demoLog->id, $logsForDemo->first()->id);

        // Fetch transactions
        $txsForDemo = Transaction::all();
        $this->assertCount(1, $txsForDemo);
        $this->assertEquals($demoTx->id, $txsForDemo->first()->id);

        // 6. Test scope as Regular User
        $this->actingAs($regularUser);

        // Fetch logs
        $logsForRegular = ImportLog::all();
        $this->assertCount(1, $logsForRegular);
        $this->assertEquals($regularLog->id, $logsForRegular->first()->id);

        // Fetch transactions
        $txsForRegular = Transaction::all();
        $this->assertCount(1, $txsForRegular);
        $this->assertEquals($regularTx->id, $txsForRegular->first()->id);
    }
}
