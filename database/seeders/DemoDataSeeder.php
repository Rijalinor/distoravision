<?php

namespace Database\Seeders;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DemoDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create demo user if not exists
        $demoUser = User::firstOrCreate(
            ['email' => 'demo@admin.com'],
            [
                'name' => 'Demo Administrator',
                'role' => 'admin',
                'password' => bcrypt('password'),
            ]
        );
        $demoUserId = $demoUser->id;

        // 1. Truncate existing data (keeping users)
        Schema::disableForeignKeyConstraints();
        DB::table('transactions')->truncate();
        DB::table('salesman_targets')->truncate();
        DB::table('salesmen')->truncate();
        DB::table('outlets')->truncate();
        DB::table('products')->truncate();
        DB::table('principals')->truncate();
        DB::table('branches')->truncate();
        DB::table('import_logs')->truncate();
        DB::table('ar_receivables')->truncate();
        DB::table('ar_import_logs')->truncate();
        DB::table('sales_per_transactions')->truncate();
        DB::table('sales_per_stocks')->truncate();
        DB::table('sales_per_import_logs')->truncate();
        Schema::enableForeignKeyConstraints();

        // 2. Seed Branches
        $branches = [
            ['id' => 1, 'code' => 'BJM', 'name' => 'Banjarmasin', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'code' => 'BRB', 'name' => 'Barabai', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'code' => 'BTL', 'name' => 'Batulicin', 'created_at' => now(), 'updated_at' => now()],
        ];
        DB::table('branches')->insert($branches);

        // 3. Seed Principals
        $principals = [
            ['id' => 1, 'code' => 'PR01', 'name' => 'PT. Nestle Indonesia', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'code' => 'PR02', 'name' => 'PT. Unilever Indonesia', 'created_at' => now(), 'updated_at' => now()],
        ];
        DB::table('principals')->insert($principals);

        // 4. Seed Salesmen
        $salesmen = [
            ['id' => 1, 'branch_id' => 1, 'sales_code' => 'SL01', 'name' => 'Budi Santoso', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'branch_id' => 1, 'sales_code' => 'SL02', 'name' => 'Andi Wijaya', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'branch_id' => 2, 'sales_code' => 'SL03', 'name' => 'Siti Rahma', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'branch_id' => 3, 'sales_code' => 'SL04', 'name' => 'Joko Susilo', 'created_at' => now(), 'updated_at' => now()],
        ];
        DB::table('salesmen')->insert($salesmen);

        // 5. Seed Outlets
        $outlets = [
            ['id' => 1, 'code' => 'OT001', 'name' => 'Toko Rejeki Baru', 'address' => 'Jl. Ahmad Yani KM 5', 'city' => 'Banjarmasin', 'route' => 'R01', 'phone' => '08123456789', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'code' => 'OT002', 'name' => 'Mini Market Sentosa', 'address' => 'Jl. Hasan Basri No. 12', 'city' => 'Banjarmasin', 'route' => 'R01', 'phone' => '08123456790', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'code' => 'OT003', 'name' => 'Warung Mbah Sri', 'address' => 'Jl. Pramuka Gg. Melati', 'city' => 'Banjarmasin', 'route' => 'R02', 'phone' => '08123456791', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'code' => 'OT004', 'name' => 'Toko Sinar Jaya', 'address' => 'Jl. Veteran No. 45', 'city' => 'Banjarmasin', 'route' => 'R02', 'phone' => '08123456792', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'code' => 'OT005', 'name' => 'Apotek Sehat', 'address' => 'Jl. Gatot Subroto', 'city' => 'Banjarmasin', 'route' => 'R03', 'phone' => '08123456793', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 6, 'code' => 'OT006', 'name' => 'Toko Barokah', 'address' => 'Jl. Sudirman No. 8', 'city' => 'Barabai', 'route' => 'R04', 'phone' => '08123456794', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 7, 'code' => 'OT007', 'name' => 'Mart Indah', 'address' => 'Jl. Kemakmuran', 'city' => 'Barabai', 'route' => 'R04', 'phone' => '08123456795', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 8, 'code' => 'OT008', 'name' => 'Warung Pojok', 'address' => 'Jl. Merdeka', 'city' => 'Batulicin', 'route' => 'R05', 'phone' => '08123456796', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 9, 'code' => 'OT009', 'name' => 'Kios Kelontong', 'address' => 'Jl. Samudra', 'city' => 'Batulicin', 'route' => 'R05', 'phone' => '08123456797', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 10, 'code' => 'OT010', 'name' => 'Toko Sukses', 'address' => 'Jl. Pelabuhan', 'city' => 'Batulicin', 'route' => 'R05', 'phone' => '08123456798', 'created_at' => now(), 'updated_at' => now()],
        ];
        DB::table('outlets')->insert($outlets);

        // 6. Seed Products
        $products = [
            ['id' => 1, 'principal_id' => 1, 'item_no' => 'P001', 'name' => 'Nestle Dancow Cokelat 800g', 'uom_sku' => 'Karton', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'principal_id' => 1, 'item_no' => 'P002', 'name' => 'Milo Activ-Go 1kg', 'uom_sku' => 'Karton', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 3, 'principal_id' => 1, 'item_no' => 'P003', 'name' => 'Nescafé Classic 100g', 'uom_sku' => 'Karton', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 4, 'principal_id' => 1, 'item_no' => 'P004', 'name' => 'Bear Brand 189ml', 'uom_sku' => 'Karton', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 5, 'principal_id' => 2, 'item_no' => 'P005', 'name' => 'Pepsodent Jumbo 190g', 'uom_sku' => 'Karton', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 6, 'principal_id' => 2, 'item_no' => 'P006', 'name' => 'Lifebuoy Bodywash Refill', 'uom_sku' => 'Karton', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 7, 'principal_id' => 2, 'item_no' => 'P007', 'name' => 'Rinso Anti Noda 700g', 'uom_sku' => 'Karton', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 8, 'principal_id' => 2, 'item_no' => 'P008', 'name' => 'Sunsilk Hijab Refresh 170ml', 'uom_sku' => 'Karton', 'created_at' => now(), 'updated_at' => now()],
        ];
        DB::table('products')->insert($products);

        // 7. Seed Import Logs (Metadata logs)
        $periods = ['2026-01', '2026-02', '2026-03', '2026-04', '2026-05', '2026-06'];
        $importLogs = [];
        foreach ($periods as $i => $period) {
            $importLogs[] = [
                'id' => $i + 1,
                'user_id' => $demoUserId,
                'filename' => "import_{$period}.xlsx",
                'period' => $period,
                'status' => 'completed',
                'total_rows' => 50,
                'imported_rows' => 50,
                'skipped_rows' => 0,
                'failed_rows' => 0,
                'errors' => null,
                'started_at' => now()->subMonths(6 - $i),
                'completed_at' => now()->subMonths(6 - $i)->addMinutes(2),
                'created_at' => now()->subMonths(6 - $i),
                'updated_at' => now()->subMonths(6 - $i),
            ];
        }
        DB::table('import_logs')->insert($importLogs);

        // 8. Seed Salesman Targets
        $targets = [];
        foreach ($salesmen as $s) {
            foreach ($periods as $period) {
                $targets[] = [
                    'salesman_id' => $s['id'],
                    'period' => $period,
                    'target_amount' => rand(50000000, 100000000), // 50jt - 100jt
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }
        DB::table('salesman_targets')->insert($targets);

        // 9. Seed Transactions (Secondary Sales)
        $transactions = [];
        $txId = 1;

        // Let's create realistic monthly sales growth
        $baseAmounts = [
            '2026-01' => 180000000,
            '2026-02' => 210000000,
            '2026-03' => 230000000,
            '2026-04' => 270000000,
            '2026-05' => 290000000,
            '2026-06' => 320000000,
        ];

        foreach ($periods as $periodIndex => $period) {
            $year = 2026;
            $month = (int) substr($period, 5, 2);
            $daysInMonth = 28;

            // Total transactions to generate for this month
            $txCount = rand(45, 60);

            for ($k = 0; $k < $txCount; $k++) {
                $salesman = $salesmen[array_rand($salesmen)];
                $outlet = $outlets[array_rand($outlets)];
                $product = $products[array_rand($products)];

                $day = rand(1, $daysInMonth);
                $dateString = sprintf('%04d-%02d-%02d', $year, $month, $day);

                // Determine Invoice or Return (e.g. 5% returns)
                $type = (rand(1, 100) > 95) ? 'R' : 'I';
                $qty = rand(5, 80);

                // Price calculations
                $priceBase = rand(50000, 150000);
                $gross = $qty * $priceBase;

                // Apply randomized discount (e.g. 0% to 15%)
                $discPercent = rand(0, 15) / 100;
                $discTotal = round($gross * $discPercent, 2);
                $taxedAmt = $gross - $discTotal;
                $vat = round($taxedAmt * 0.11, 2);
                $arAmt = $taxedAmt + $vat;
                $cogs = round($gross * 0.75, 2); // 75% margin cost

                // If return, negate the quantities and values
                if ($type === 'R') {
                    $qty = -$qty;
                    $gross = -$gross;
                    $discTotal = -$discTotal;
                    $taxedAmt = -$taxedAmt;
                    $vat = -$vat;
                    $arAmt = -$arAmt;
                    $cogs = -$cogs;
                }

                $transactions[] = [
                    'id' => $txId++,
                    'branch_id' => $salesman['branch_id'],
                    'salesman_id' => $salesman['id'],
                    'outlet_id' => $outlet['id'],
                    'product_id' => $product['id'],
                    'type' => $type,
                    'so_no' => 'SO'.rand(100000, 999999),
                    'so_date' => $dateString,
                    'ref_no' => null,
                    'pfi_cn_no' => 'PFI'.rand(100000, 999999),
                    'pfi_cn_date' => $dateString,
                    'gi_gr_no' => 'GI'.rand(100000, 999999),
                    'gi_gr_date' => $dateString,
                    'si_cn_no' => 'SI'.rand(100000, 999999),
                    'month' => $month,
                    'week' => rand(1, 4),
                    'warehouse' => 'Gudang Utama',
                    'tax_invoice' => 'FP'.rand(100000, 999999),
                    'qty_base' => $qty,
                    'price_base' => $priceBase,
                    'gross' => $gross,
                    'disc_total' => $discTotal,
                    'taxed_amt' => $taxedAmt,
                    'vat' => $vat,
                    'ar_amt' => $arAmt,
                    'cogs' => $cogs,
                    'period' => $period,
                    'import_log_id' => $periodIndex + 1,
                    'dedupe_key' => uniqid(),
                    'created_at' => Carbon::parse($dateString),
                    'updated_at' => Carbon::parse($dateString),
                ];
            }
        }

        // Insert transactions in chunks to be database-friendly
        $chunks = array_chunk($transactions, 100);
        foreach ($chunks as $chunk) {
            DB::table('transactions')->insert($chunk);
        }

        // 10. Seed AR Import Logs
        $arImportLog = [
            'id' => 1,
            'user_id' => $demoUserId,
            'filename' => 'ar_receivables_current.xlsx',
            'report_date' => now()->format('Y-m-d'),
            'sheet_name' => 'BJM',
            'status' => 'completed',
            'total_rows' => 15,
            'imported_rows' => 15,
            'failed_rows' => 0,
            'errors' => null,
            'started_at' => now(),
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
        DB::table('ar_import_logs')->insert($arImportLog);

        // 11. Seed AR Receivables (Aging Piutang)
        $arItems = [];
        $overdues = [0, 5, 12, 28, 45, 65, 95, 110, 150];

        foreach ($outlets as $idx => $outlet) {
            $salesman = $salesmen[$idx % count($salesmen)];
            $principal = $principals[$idx % count($principals)];

            $arAmount = rand(5000000, 25000000);
            $overdue = $overdues[$idx % count($overdues)];

            // Calculate document dates based on overdue days
            $docDate = now()->subDays($overdue + 30);
            $dueDate = now()->subDays($overdue);

            $arPaid = ($overdue == 0) ? $arAmount : round($arAmount * (rand(0, 50) / 100), 2);
            $arBalance = $arAmount - $arPaid;

            $arItems[] = [
                'ar_import_log_id' => 1,
                'outlet_code' => $outlet['code'],
                'outlet_name' => $outlet['name'],
                'outlet_ref' => null,
                'supervisor' => 'SPV '.$salesman['name'],
                'salesman_code' => $salesman['sales_code'],
                'salesman_name' => $salesman['name'],
                'principal_code' => $principal['code'],
                'principal_name' => $principal['name'],
                'pfi_sn' => 'SN'.rand(2000000, 9000000),
                'doc_date' => $docDate->format('Y-m-d'),
                'due_date' => $dueDate->format('Y-m-d'),
                'inv_exchange_date' => $docDate->addDays(7)->format('Y-m-d'),
                'top' => 30,
                'si_cn' => 'SI'.rand(100000, 999999),
                'cm' => 0,
                'cn_balance' => 0,
                'ar_amount' => $arAmount,
                'ar_paid' => $arPaid,
                'ar_balance' => $arBalance,
                'credit_limit' => rand(30000000, 50000000),
                'paid_date' => ($arBalance == 0) ? now()->format('Y-m-d') : null,
                'overdue_days' => $overdue,
                'giro_no' => null,
                'bank_code' => null,
                'bank_name' => null,
                'giro_amount' => null,
                'giro_due_date' => null,
                'branch_sheet' => $branches[$idx % count($branches)]['code'],
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('ar_receivables')->insert($arItems);

        // 12. Seed Sales Per Import Log
        $salesPerImportLog = [
            'id' => 1,
            'user_id' => $demoUserId,
            'filename' => 'sales_per_stock_report.xlsx',
            'period' => '2026-06',
            'status' => 'completed',
            'total_rows' => 20,
            'imported_rows' => 20,
            'skipped_rows' => 0,
            'failed_rows' => 0,
            'errors' => null,
            'started_at' => now(),
            'completed_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
        DB::table('sales_per_import_logs')->insert($salesPerImportLog);

        // 13. Seed Sales Per Transactions
        $salesPerTx = [];
        for ($i = 0; $i < 30; $i++) {
            $outlet = $outlets[array_rand($outlets)];
            $product = $products[array_rand($products)];
            $salesman = $salesmen[array_rand($salesmen)];
            $principal = $principals[array_rand($principals)];
            $branch = $branches[array_rand($branches)];

            $qty = rand(1, 40);
            $subtotal = $qty * rand(60000, 140000);

            $salesPerTx[] = [
                'sales_per_import_log_id' => 1,
                'type' => (rand(1, 100) > 95) ? 'R' : 'I',
                'branch_code' => $branch['code'],
                'sales_code' => $salesman['sales_code'],
                'sales_name' => $salesman['name'],
                'outlet_code' => $outlet['code'],
                'outlet_name' => $outlet['name'],
                'principal_code' => $principal['code'],
                'principal_name' => $principal['name'],
                'item_no' => $product['item_no'],
                'item_name' => $product['name'],
                'so_no' => 'SO'.rand(100000, 999999),
                'pfi_no' => 'PFI'.rand(100000, 999999),
                'so_date' => now()->subDays(rand(1, 20))->format('Y-m-d'),
                'qty' => $qty,
                'subtotal' => $subtotal,
                'vat' => round($subtotal * 0.11, 2),
                'period' => '2026-06',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('sales_per_transactions')->insert($salesPerTx);

        // 14. Seed Sales Per Stocks
        $salesPerStock = [];
        $stockStatuses = [
            // [on_hand_base, was, age]
            [0, 10, 0], // Empty stock / Critical
            [2, 8, 45], // Critical SWC < 2
            [25, 4, 30], // Healthy SWC > 4
            [100, 12, 15], // Healthy
            [50, 0, 90], // Slow-moving / aged
        ];

        foreach ($products as $idx => $product) {
            $principal = $principals[$idx % count($principals)];
            $branch = $branches[$idx % count($branches)];

            $status = $stockStatuses[$idx % count($stockStatuses)];
            $onHand = $status[0];
            $was = $status[1];
            $age = $status[2];

            $swc = ($was > 0) ? round($onHand / $was, 1) : 0;
            $val = $onHand * rand(60000, 120000);

            $salesPerStock[] = [
                'sales_per_import_log_id' => 1,
                'principal_code' => $principal['code'],
                'principal_name' => $principal['name'],
                'warehouse_code' => 'GD'.$branch['code'],
                'warehouse_name' => 'Gudang '.$branch['name'],
                'item_no' => $product['item_no'],
                'item_name' => $product['name'],
                'size' => '800g',
                'on_hand_base' => $onHand,
                'on_sales_base' => $onHand,
                'stock_value_on_hand' => $val,
                'stock_value_on_sales' => $val,
                'was' => $was,
                'swc' => $swc,
                'age_of_goods' => $age,
                'period' => '2026-06',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        DB::table('sales_per_stocks')->insert($salesPerStock);
    }
}
