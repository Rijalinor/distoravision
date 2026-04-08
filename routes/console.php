<?php

use App\Models\Branch;
use App\Models\Outlet;
use App\Models\Principal;
use App\Models\Product;
use App\Models\Salesman;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('demo:seed {period} {--rows=1500}', function () {
    $period = (string) $this->argument('period');
    $rows = max(100, (int) $this->option('rows'));

    if (!preg_match('/^\d{4}-\d{2}$/', $period)) {
        $this->error('Format period wajib YYYY-MM. Contoh: 2026-03');
        return self::FAILURE;
    }

    $prefix = (string) env('DEMO_FAKE_SO_PREFIX', 'DMO-');
    $this->info("Generate fake data period {$period} dengan prefix {$prefix} ...");

    Transaction::where('period', $period)->where('so_no', 'like', $prefix . '%')->delete();

    $branches = collect([
        ['code' => 'DMO-JKT', 'name' => 'Demo Branch Jakarta'],
        ['code' => 'DMO-BDG', 'name' => 'Demo Branch Bandung'],
        ['code' => 'DMO-SBY', 'name' => 'Demo Branch Surabaya'],
    ])->map(fn ($b) => Branch::firstOrCreate(['code' => $b['code']], ['name' => $b['name']]))->values();

    $principals = collect([
        ['code' => 'P-DMO-01', 'name' => 'Principal Demo Alpha'],
        ['code' => 'P-DMO-02', 'name' => 'Principal Demo Beta'],
        ['code' => 'P-DMO-03', 'name' => 'Principal Demo Gamma'],
    ])->map(fn ($p) => Principal::firstOrCreate(['name' => $p['name']], ['code' => $p['code']]))->values();

    $salesmen = collect(range(1, 12))->map(function ($i) use ($branches) {
        return Salesman::firstOrCreate(
            ['sales_code' => 'SDMO-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT)],
            [
                'branch_id' => $branches[($i - 1) % $branches->count()]->id,
                'name' => 'Salesman Demo ' . $i,
            ]
        );
    })->values();

    $outlets = collect(range(1, 90))->map(function ($i) {
        $cities = ['Jakarta', 'Bandung', 'Surabaya', 'Semarang', 'Yogyakarta'];
        return Outlet::firstOrCreate(
            ['code' => 'ODMO-' . str_pad((string) $i, 4, '0', STR_PAD_LEFT)],
            [
                'name' => 'Outlet Demo ' . $i,
                'address' => 'Jl. Demo No. ' . $i,
                'city' => $cities[($i - 1) % count($cities)],
                'route' => 'R' . (($i - 1) % 12 + 1),
                'phone' => '08' . str_pad((string) random_int(100000000, 999999999), 9, '0', STR_PAD_LEFT),
            ]
        );
    })->values();

    $products = collect();
    foreach ($principals as $principalIndex => $principal) {
        for ($i = 1; $i <= 10; $i++) {
            $products->push(Product::firstOrCreate(
                ['principal_id' => $principal->id, 'item_no' => 'SKU-DMO-' . ($principalIndex + 1) . '-' . str_pad((string) $i, 2, '0', STR_PAD_LEFT)],
                ['name' => 'Demo Product ' . ($principalIndex + 1) . '-' . $i, 'uom_sku' => 'PCS']
            ));
        }
    }

    $start = Carbon::createFromFormat('Y-m-d', $period . '-01')->startOfMonth();
    $days = $start->daysInMonth - 1;
    $rowsData = [];

    for ($i = 1; $i <= $rows; $i++) {
        $salesman = $salesmen->random();
        $branch = $branches->firstWhere('id', $salesman->branch_id) ?: $branches->random();
        $outlet = $outlets->random();
        $product = $products->random();

        $type = random_int(1, 100) <= 8 ? 'R' : 'I';
        $qty = random_int(1, 48);
        $price = random_int(12000, 280000);
        $gross = $qty * $price;
        $disc = (int) round($gross * (random_int(0, 25) / 100));
        $taxed = max(0, $gross - $disc);
        $vat = (int) round($taxed * 0.11);
        $arAmt = $taxed + $vat;
        $cogs = (int) round($gross * (random_int(55, 85) / 100));

        if ($type === 'R') {
            $qty *= -1;
            $gross *= -1;
            $disc *= -1;
            $taxed *= -1;
            $vat *= -1;
            $arAmt *= -1;
            $cogs *= -1;
        }

        $date = $start->copy()->addDays(random_int(0, max(0, $days)));

        $rowsData[] = [
            'branch_id' => $branch->id,
            'salesman_id' => $salesman->id,
            'outlet_id' => $outlet->id,
            'product_id' => $product->id,
            'type' => $type,
            'so_no' => $prefix . strtoupper(Str::random(10)) . '-' . $i,
            'so_date' => $date->format('Y-m-d'),
            'ref_no' => null,
            'pfi_cn_no' => null,
            'pfi_cn_date' => null,
            'gi_gr_no' => null,
            'gi_gr_date' => null,
            'si_cn_no' => null,
            'month' => $date->format('m'),
            'week' => (int) ceil($date->day / 7),
            'warehouse' => 'WH-DEMO',
            'tax_invoice' => null,
            'qty_base' => $qty,
            'price_base' => $price,
            'gross' => $gross,
            'disc_total' => $disc,
            'taxed_amt' => $taxed,
            'vat' => $vat,
            'ar_amt' => $arAmt,
            'cogs' => $cogs,
            'period' => $period,
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    foreach (array_chunk($rowsData, 500) as $chunk) {
        Transaction::insert($chunk);
    }

    $this->info('Fake data selesai dibuat.');
    $this->line("Rows inserted: {$rows}");
    $this->line("Sekarang klik tombol 'Aktifkan Demo' di UI.");

    return self::SUCCESS;
})->purpose('Generate fake transactions for demo presentation period');
