<?php

namespace App\Services;

use App\Models\ArImportLog;
use App\Models\ArReceivable;
use App\Models\Outlet;
use App\Models\Product;
use App\Models\Salesman;
use App\Models\SalesPerStock;
use App\Models\Transaction;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class AiContextService
{
    /**
     * Gather the Unified Business Snapshot for AI Chat context.
     * Uses caching (1 hour TTL) and batch-optimized queries.
     *
     * @return array<string, mixed>
     */
    public function getUnifiedSnapshot(): array
    {
        $period = Transaction::max('period') ?? date('Y-m');
        $prevPeriod = date('Y-m', strtotime($period.'-01 -1 month'));

        return Cache::remember("ai_unified_snapshot_{$period}", 3600, function () use ($period, $prevPeriod) {
            return $this->gatherUnifiedContext($period, $prevPeriod);
        });
    }

    /**
     * Get the current and previous period identifiers.
     *
     * @return array{period: string, prevPeriod: string}
     */
    public function getPeriods(): array
    {
        $period = Transaction::max('period') ?? date('Y-m');
        $prevPeriod = date('Y-m', strtotime($period.'-01 -1 month'));

        return compact('period', 'prevPeriod');
    }

    /**
     * Build the full unified context using batch-optimized queries.
     *
     * @return array<string, mixed>
     */
    private function gatherUnifiedContext(string $period, string $prevPeriod): array
    {
        // 1. Global Financial Metrics (2 periods × 1 query each = 2 queries)
        $globalNow = $this->getFinancialSummary($period);
        $globalPrev = $this->getFinancialSummary($prevPeriod);

        // 2. Top & Bottom Performers — batch-optimized (eliminates N+1)
        $salesmenIni = $this->getEnrichedSalesmen($period);
        $salesmenLalu = $this->getEnrichedSalesmen($prevPeriod);

        $produkIni = $this->getEnrichedProducts($period);
        $produkLalu = $this->getEnrichedProducts($prevPeriod);

        $tokoIni = $this->getEnrichedOutlets($period);
        $tokoLalu = $this->getEnrichedOutlets($prevPeriod);

        // 3. Bottom 3 Performers (3 simple queries — already efficient)
        $bottomSalesmen = $this->getBottomPerformers($period, 'salesman');
        $bottomProduk = $this->getBottomPerformers($period, 'product');
        $bottomToko = $this->getBottomPerformers($period, 'outlet');

        // 4. Inventory Alerts
        $stokKritis = SalesPerStock::where('period', $period)
            ->where('swc', '<', 2)
            ->orderByDesc('on_sales_base')->limit(5)
            ->get()->map(fn ($s) => ['nama_barang' => $s->item_name, 'sisa_stok_minggu' => (float) $s->swc]);

        // 5. Sleeping Outlets & Trajectory (reuse buyer sets to avoid duplicate queries)
        $buyersLastMonth = Transaction::where('period', $prevPeriod)->where('type', 'I')
            ->selectRaw('outlet_id, SUM(taxed_amt) as total_sales')->groupBy('outlet_id')
            ->having('total_sales', '>', 0)->get()->keyBy('outlet_id');

        $buyersThisMonth = Transaction::where('period', $period)->where('type', 'I')
            ->selectRaw('outlet_id, SUM(taxed_amt) as total_sales')->groupBy('outlet_id')
            ->having('total_sales', '>', 0)->get()->keyBy('outlet_id');

        $sleepingOutlets = $this->buildSleepingOutlets($buyersLastMonth, $buyersThisMonth);
        [$tokoMeroket, $tokoAnjlok] = $this->buildTrajectory($buyersLastMonth, $buyersThisMonth);

        // 6. Overstock
        $overstock = SalesPerStock::where('period', $period)->where('swc', '>', 12)->orderByDesc('stock_value_on_hand')->limit(3)
            ->get()->map(fn ($s) => ['nama_barang' => $s->item_name, 'uang_macet_rp' => (float) $s->stock_value_on_hand])->toArray();

        // 7. AR Tunggakan Kritis
        $tunggakan = $this->getCriticalReceivables($period);

        // 8. Cross-Selling Bundling
        $bundling = $this->getCrossSelling($period);

        return [
            'ringkasan_bisnis' => [
                "periode_{$period}_(bulan_ini)" => $globalNow,
                "periode_{$prevPeriod}_(bulan_lalu)" => $globalPrev,
            ],
            'top_5_salesman_bulan_ini' => $salesmenIni,
            'top_5_salesman_bulan_lalu' => $salesmenLalu,
            'top_5_produk_terlaris_bulan_ini' => $produkIni,
            'top_5_produk_terlaris_bulan_lalu' => $produkLalu,
            'top_5_toko_terlaris_bulan_ini' => $tokoIni,
            'top_5_toko_terlaris_bulan_lalu' => $tokoLalu,
            'peringatan_stok_kritis_hampir_habis' => $stokKritis,
            'kinerja_terburuk_bottom_3' => [
                'bottom_3_salesman_penyumbang_terkecil' => $bottomSalesmen,
                'bottom_3_produk_paling_tidak_laku' => $bottomProduk,
                'bottom_3_toko_pembelanjaan_terkecil' => $bottomToko,
            ],
            'analisa_lanjutan_advance_analytics' => [
                'toko_hilang_sleeping_outlets' => $sleepingOutlets,
                'toko_meroket_naik' => $tokoMeroket,
                'toko_anjlok_turun' => $tokoAnjlok,
                'uang_macet_overstock' => $overstock,
                'tunggakan_kritis_ar_piutang' => $tunggakan,
                'rekomendasi_bundling_cross_selling' => $bundling,
            ],
        ];
    }

    /**
     * Single-query financial summary for a period using conditional aggregates.
     *
     * @return array<string, float>
     */
    private function getFinancialSummary(string $period): array
    {
        $row = Transaction::where('period', $period)
            ->selectRaw('
                SUM(CASE WHEN type = "I" THEN taxed_amt ELSE 0 END) as total_sales,
                SUM(CASE WHEN type = "R" THEN ABS(taxed_amt) ELSE 0 END) as total_returns,
                SUM(CASE WHEN type = "I" THEN cogs ELSE 0 END) as invoice_cogs,
                SUM(CASE WHEN type = "R" THEN ABS(cogs) ELSE 0 END) as return_cogs
            ')
            ->first();

        $totalSales = (float) ($row->total_sales ?? 0);
        $totalReturns = (float) ($row->total_returns ?? 0);
        $totalCogs = (float) ($row->invoice_cogs ?? 0) - (float) ($row->return_cogs ?? 0);
        $netSales = $totalSales - $totalReturns;
        $margin = $netSales > 0 ? (($netSales - $totalCogs) / $netSales) * 100 : 0;

        return [
            'omset_kotor_rp' => $totalSales,
            'retur_rp' => $totalReturns,
            'penjualan_bersih_rp' => $netSales,
            'margin_kotor_persen' => round($margin, 2),
        ];
    }

    /**
     * Top 5 Salesmen with their top product & outlet — batch-optimized.
     * Eliminates N+1 by collecting all IDs first, then batch-loading enrichments.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getEnrichedSalesmen(string $period): array
    {
        // Step 1: Get Top 5 salesmen IDs with revenue
        $salesmen = Transaction::where('period', $period)
            ->selectRaw('salesman_id, SUM(taxed_amt) as rev')
            ->groupBy('salesman_id')->orderByDesc('rev')->limit(5)
            ->get();

        if ($salesmen->isEmpty()) {
            return [];
        }

        $salesmanIds = $salesmen->pluck('salesman_id')->toArray();

        // Step 2: Batch-load salesman names
        $salesmanModels = Salesman::whereIn('id', $salesmanIds)->pluck('name', 'id');

        // Step 3: Batch-load top product per salesman (single query via ranked subquery)
        $topProducts = DB::select("
            SELECT sub.salesman_id, sub.product_id, p.name as product_name
            FROM (
                SELECT salesman_id, product_id, SUM(taxed_amt) as rev,
                       ROW_NUMBER() OVER (PARTITION BY salesman_id ORDER BY SUM(taxed_amt) DESC) as rn
                FROM transactions
                WHERE period = ? AND type = 'I'
                GROUP BY salesman_id, product_id
            ) sub
            JOIN products p ON sub.product_id = p.id
            WHERE sub.rn = 1 AND sub.salesman_id IN (".implode(',', array_fill(0, count($salesmanIds), '?')).')
        ', array_merge([$period], $salesmanIds));
        $topProductMap = collect($topProducts)->keyBy('salesman_id');

        // Step 4: Batch-load top outlet per salesman (single query via ranked subquery)
        $topOutlets = DB::select("
            SELECT sub.salesman_id, sub.outlet_id, o.name as outlet_name
            FROM (
                SELECT salesman_id, outlet_id, SUM(taxed_amt) as rev,
                       ROW_NUMBER() OVER (PARTITION BY salesman_id ORDER BY SUM(taxed_amt) DESC) as rn
                FROM transactions
                WHERE period = ? AND type = 'I'
                GROUP BY salesman_id, outlet_id
            ) sub
            JOIN outlets o ON sub.outlet_id = o.id
            WHERE sub.rn = 1 AND sub.salesman_id IN (".implode(',', array_fill(0, count($salesmanIds), '?')).')
        ', array_merge([$period], $salesmanIds));
        $topOutletMap = collect($topOutlets)->keyBy('salesman_id');

        // Step 5: Combine into final array
        return $salesmen->map(function ($t) use ($salesmanModels, $topProductMap, $topOutletMap) {
            return [
                'nama' => $salesmanModels[$t->salesman_id] ?? 'Unknown',
                'omset_rp' => (float) $t->rev,
                'produk_terlaris_andalan_dia' => $topProductMap[$t->salesman_id]->product_name ?? 'Unknown',
                'toko_pelanggan_terbesar_dia' => $topOutletMap[$t->salesman_id]->outlet_name ?? 'Unknown',
            ];
        })->toArray();
    }

    /**
     * Top 5 Products with their top outlet — batch-optimized.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getEnrichedProducts(string $period): array
    {
        $products = Transaction::where('period', $period)
            ->selectRaw('product_id, SUM(taxed_amt) as rev')
            ->groupBy('product_id')->orderByDesc('rev')->limit(5)
            ->get();

        if ($products->isEmpty()) {
            return [];
        }

        $productIds = $products->pluck('product_id')->toArray();

        // Batch-load product names
        $productModels = Product::whereIn('id', $productIds)->pluck('name', 'id');

        // Batch-load top outlet per product (single query)
        $topOutlets = DB::select("
            SELECT sub.product_id, sub.outlet_id, o.name as outlet_name
            FROM (
                SELECT product_id, outlet_id, SUM(taxed_amt) as rev,
                       ROW_NUMBER() OVER (PARTITION BY product_id ORDER BY SUM(taxed_amt) DESC) as rn
                FROM transactions
                WHERE period = ? AND type = 'I'
                GROUP BY product_id, outlet_id
            ) sub
            JOIN outlets o ON sub.outlet_id = o.id
            WHERE sub.rn = 1 AND sub.product_id IN (".implode(',', array_fill(0, count($productIds), '?')).')
        ', array_merge([$period], $productIds));
        $topOutletMap = collect($topOutlets)->keyBy('product_id');

        return $products->map(function ($t) use ($productModels, $topOutletMap) {
            return [
                'nama' => $productModels[$t->product_id] ?? 'Unknown',
                'omset_rp' => (float) $t->rev,
                'toko_yang_paling_banyak_beli_produk_ini' => $topOutletMap[$t->product_id]->outlet_name ?? 'Unknown',
            ];
        })->toArray();
    }

    /**
     * Top 5 Outlets with their top product — batch-optimized.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getEnrichedOutlets(string $period): array
    {
        $outlets = Transaction::where('period', $period)
            ->selectRaw('outlet_id, SUM(taxed_amt) as rev')
            ->groupBy('outlet_id')->orderByDesc('rev')->limit(5)
            ->get();

        if ($outlets->isEmpty()) {
            return [];
        }

        $outletIds = $outlets->pluck('outlet_id')->toArray();

        // Batch-load outlet names
        $outletModels = Outlet::whereIn('id', $outletIds)->pluck('name', 'id');

        // Batch-load top product per outlet (single query)
        $topProducts = DB::select("
            SELECT sub.outlet_id, sub.product_id, p.name as product_name
            FROM (
                SELECT outlet_id, product_id, SUM(taxed_amt) as rev,
                       ROW_NUMBER() OVER (PARTITION BY outlet_id ORDER BY SUM(taxed_amt) DESC) as rn
                FROM transactions
                WHERE period = ? AND type = 'I'
                GROUP BY outlet_id, product_id
            ) sub
            JOIN products p ON sub.product_id = p.id
            WHERE sub.rn = 1 AND sub.outlet_id IN (".implode(',', array_fill(0, count($outletIds), '?')).')
        ', array_merge([$period], $outletIds));
        $topProductMap = collect($topProducts)->keyBy('outlet_id');

        return $outlets->map(function ($t) use ($outletModels, $topProductMap) {
            return [
                'nama' => $outletModels[$t->outlet_id] ?? 'Unknown',
                'omset_rp' => (float) $t->rev,
                'barang_yang_paling_banyak_dibeli_toko_ini' => $topProductMap[$t->outlet_id]->product_name ?? 'Unknown',
            ];
        })->toArray();
    }

    /**
     * Bottom 3 performers for a given entity type.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getBottomPerformers(string $period, string $entity): array
    {
        $column = $entity.'_id';
        $relation = $entity.':id,name';

        return Transaction::where('period', $period)
            ->selectRaw("{$column}, SUM(taxed_amt) as rev")
            ->groupBy($column)->having('rev', '>', 0)->orderBy('rev', 'asc')->limit(3)
            ->with($relation)
            ->get()->map(fn ($t) => [
                'nama' => optional($t->{$entity})->name ?? 'Unknown',
                'omset_rp' => (float) $t->rev,
            ])->toArray();
    }

    /**
     * Identify sleeping outlets (active last month, gone this month).
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildSleepingOutlets($buyersLastMonth, $buyersThisMonth): array
    {
        $sleepingIds = array_diff($buyersLastMonth->keys()->toArray(), $buyersThisMonth->keys()->toArray());
        $sleepingList = [];
        foreach ($sleepingIds as $oid) {
            $lostRev = $buyersLastMonth[$oid]->total_sales;
            if ($lostRev > 50000) {
                $sleepingList[$oid] = $lostRev;
            }
        }
        arsort($sleepingList);
        $topSleeping = array_slice($sleepingList, 0, 3, true);

        if (empty($topSleeping)) {
            return [];
        }

        $outletModels = Outlet::whereIn('id', array_keys($topSleeping))->pluck('name', 'id');

        return collect($topSleeping)->map(fn ($lostRev, $oid) => [
            'nama_outlet' => $outletModels[$oid] ?? 'Unknown',
            'potensi_hilang_rp' => (float) $lostRev,
        ])->values()->toArray();
    }

    /**
     * Build outlet trajectory: top 3 rising and top 3 declining.
     *
     * @return array{0: array, 1: array}
     */
    private function buildTrajectory($buyersLastMonth, $buyersThisMonth): array
    {
        $trajectory = [];
        foreach ($buyersThisMonth as $oid => $data) {
            $nowRev = $data->total_sales;
            $prevRev = isset($buyersLastMonth[$oid]) ? $buyersLastMonth[$oid]->total_sales : 0;
            $diff = $nowRev - $prevRev;
            if (abs($diff) > 100000) {
                $trajectory[$oid] = ['now' => $nowRev, 'prev' => $prevRev, 'diff' => $diff];
            }
        }

        uasort($trajectory, fn ($a, $b) => $b['diff'] <=> $a['diff']);
        $trendingUp = array_slice($trajectory, 0, 3, true);
        uasort($trajectory, fn ($a, $b) => $a['diff'] <=> $b['diff']);
        $trendingDown = array_slice($trajectory, 0, 3, true);

        $allIds = array_merge(array_keys($trendingUp), array_keys($trendingDown));
        $outletModels = ! empty($allIds) ? Outlet::whereIn('id', $allIds)->pluck('name', 'id') : collect();

        $tokoMeroket = collect($trendingUp)->map(fn ($d, $oid) => [
            'nama_outlet' => $outletModels[$oid] ?? 'Unknown',
            'peningkatan_omset_rp' => (float) $d['diff'],
            'omset_ini_rp' => (float) $d['now'],
        ])->values()->toArray();

        $tokoAnjlok = collect($trendingDown)->map(fn ($d, $oid) => [
            'nama_outlet' => $outletModels[$oid] ?? 'Unknown',
            'penurunan_omset_rp' => (float) abs($d['diff']),
            'omset_ini_rp' => (float) $d['now'],
        ])->values()->toArray();

        return [$tokoMeroket, $tokoAnjlok];
    }

    /**
     * Get critical AR receivables (overdue > 60 days).
     *
     * @return array<int, array<string, mixed>>
     */
    private function getCriticalReceivables(string $period): array
    {
        $log = ArImportLog::where('status', 'completed')
            ->where('report_date', 'like', $period.'%')
            ->orderByDesc('report_date')->first()
            ?? ArImportLog::where('status', 'completed')->orderByDesc('report_date')->first();

        if (! $log) {
            return [];
        }

        return ArReceivable::where('ar_import_log_id', $log->id)
            ->where('overdue_days', '>', 60)
            ->orderByDesc('ar_balance')->limit(3)
            ->get()->map(fn ($a) => [
                'toko' => $a->outlet_name,
                'tunggakan_rp' => (float) $a->ar_balance,
                'telat_hari' => $a->overdue_days,
            ])->toArray();
    }

    /**
     * Get top 3 cross-selling product pairs — batch-optimized.
     *
     * @return array<int, array<string, mixed>>
     */
    private function getCrossSelling(string $period): array
    {
        $pairs = Transaction::where('period', $period)->where('type', 'I')
            ->select('outlet_id', 'product_id')->distinct()->get()->groupBy('outlet_id');

        $matrix = [];
        foreach ($pairs as $itemsInBasket) {
            $items = $itemsInBasket->pluck('product_id')->toArray();
            foreach ($items as $p1) {
                if (! isset($matrix[$p1])) {
                    $matrix[$p1] = [];
                }
                foreach ($items as $p2) {
                    if ($p1 != $p2) {
                        if (! isset($matrix[$p1][$p2])) {
                            $matrix[$p1][$p2] = 0;
                        }
                        $matrix[$p1][$p2]++;
                    }
                }
            }
        }

        $affinities = [];
        foreach ($matrix as $source => $targets) {
            if (empty($targets)) {
                continue;
            }
            arsort($targets);
            $topTarget = array_key_first($targets);
            $count = $targets[$topTarget];
            if ($count > 3) {
                $affinities[] = ['source' => $source, 'target' => $topTarget, 'count' => $count];
            }
        }
        usort($affinities, fn ($a, $b) => $b['count'] <=> $a['count']);
        $topCrossSell = array_slice($affinities, 0, 3);

        if (empty($topCrossSell)) {
            return [];
        }

        // Batch-load all referenced product names in one query
        $prodIds = array_unique(array_merge(
            array_column($topCrossSell, 'source'),
            array_column($topCrossSell, 'target')
        ));
        $productModels = Product::whereIn('id', $prodIds)->pluck('name', 'id');

        return collect($topCrossSell)->map(fn ($pair) => [
            'produk_utama' => $productModels[$pair['source']] ?? 'Unknown',
            'sering_dibeli_bersamaan_dengan' => $productModels[$pair['target']] ?? 'Unknown',
            'total_toko_yang_beli_bersamaan' => $pair['count'],
        ])->toArray();
    }
}
