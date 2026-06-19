<?php

namespace App\Http\Controllers;

use App\Exports\BukuRaporExport;
use App\Http\Controllers\Traits\CsvExportable;
use App\Models\Outlet;
use App\Models\Principal;
use App\Models\Salesman;
use App\Models\SalesmanTarget;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class AdvancedAnalyticsController extends Controller
{
    use CsvExportable;

    /**
     * Display Pareto Analysis (80/20 Rule) for Products and Outlets
     */
    public function pareto(Request $request)
    {
        $period = $request->get('period', Transaction::max('period') ?? date('Y-m'));
        $periods = Transaction::select('period')->distinct()->orderByDesc('period')->pluck('period');

        $type = $request->get('type', 'product'); // 'product' or 'outlet'

        if ($type === 'product') {
            $data = Transaction::withFilters(request())->invoices()
                ->join('products', 'transactions.product_id', '=', 'products.id')
                ->select('products.name', DB::raw('SUM(transactions.taxed_amt) as total_sales'))
                ->groupBy('products.name')
                ->having('total_sales', '>', 0)
                ->orderByDesc('total_sales')
                ->get();
        } else {
            $data = Transaction::withFilters(request())->invoices()
                ->join('outlets', 'transactions.outlet_id', '=', 'outlets.id')
                ->select('outlets.name', DB::raw('SUM(transactions.taxed_amt) as total_sales'))
                ->groupBy('outlets.name')
                ->having('total_sales', '>', 0)
                ->orderByDesc('total_sales')
                ->get();
        }

        // Calculate Cumulative %
        $totalRevenue = $data->sum('total_sales');

        $cumulative = 0;
        $paretoData = [];
        $classA = [];
        $classB = [];
        $classC = [];

        foreach ($data as $item) {
            $sales = (float) $item->total_sales;
            $percent = $totalRevenue > 0 ? ($sales / $totalRevenue) * 100 : 0;
            $cumulative += $percent;

            $itemData = [
                'name' => $item->name,
                'sales' => $sales,
                'percent' => $percent,
                'cumulative' => $cumulative,
            ];

            $paretoData[] = $itemData;

            if ($cumulative <= 80) {
                $classA[] = $itemData;
            } elseif ($cumulative <= 95) {
                $classB[] = $itemData;
            } else {
                $classC[] = $itemData;
            }
        }

        // Take top 50 for the chart so it doesn't get too heavy
        $chartData = array_slice($paretoData, 0, 50);

        // Pagination for the table
        $page = $request->get('page', 1);
        $perPage = 100; // Match the screenshot's expectation
        $offset = ($page * $perPage) - $perPage;

        $paginatedItems = array_slice($paretoData, $offset, $perPage);
        $paretoPaginator = new LengthAwarePaginator(
            $paginatedItems,
            count($paretoData),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // --- DISTORA AI NARRATIVE GENERATOR ---
        $countA = count($classA);
        $pctA = $countA > 0 && count($data) > 0 ? ($countA / count($data)) * 100 : 0;

        $entityName = $type === 'product' ? 'Produk/SKU' : 'Outlet/Toko';
        $aiNarrative = "🔍 Fakta: Secara mengejutkan, hanya $countA $entityName (".number_format($pctA, 1)."% dari total elemen aktif) yang menyumbang 80% pendapatan utama (Kelas A Pareto).\n".
                       "💪 Kelebihan: Efisiensi tinggi! Tim bisa fokus hanya merawat $countA aset VIP ini untuk mendapat 80% omset perusahaan.\n".
                       "⚠️ Risiko & Saran: Ini bahaya ketergantungan ekstrem! Jika terjadi kelangkaan barang pada Top 3 $entityName, omset bulan depan akan hancur total. Segera matangkan strategi penetrasi untuk $entityName Kelas B.";

        // --- CSV EXPORT ---
        if ($request->get('export') === 'csv') {
            $rows = array_map(fn ($item) => [
                $item['name'],
                $item['sales'],
                round($item['percent'], 2),
                round($item['cumulative'], 2),
                $item['cumulative'] <= 80 ? 'A' : ($item['cumulative'] <= 95 ? 'B' : 'C'),
            ], $paretoData);

            return $this->streamCsv(
                "Pareto_{$type}_{$period}.csv",
                ['Nama', 'Total Sales', '% Kontribusi', '% Kumulatif', 'Kelas'],
                $rows
            );
        }

        return view('analytics.pareto', compact(
            'period', 'periods', 'type', 'paretoData', 'chartData',
            'classA', 'classB', 'classC', 'totalRevenue', 'aiNarrative', 'paretoPaginator'
        ));
    }

    public function crossSelling(Request $request)
    {
        $period = $request->get('period', Transaction::max('period') ?? date('Y-m'));
        $periods = Transaction::select('period')->distinct()->orderByDesc('period')->pluck('period');

        $pairs = Transaction::withFilters(request())
            ->invoices()
            ->join('products', 'transactions.product_id', '=', 'products.id')
            ->select('transactions.outlet_id', 'products.name as product_name')
            ->distinct()
            ->get();

        $baskets = $pairs->groupBy('outlet_id');

        $matrix = [];
        $itemBasketCounts = [];

        foreach ($baskets as $outletId => $itemsInBasket) {
            $items = $itemsInBasket->pluck('product_name')->toArray();
            foreach ($items as $p1) {
                if (! isset($itemBasketCounts[$p1])) {
                    $itemBasketCounts[$p1] = 0;
                }
                $itemBasketCounts[$p1]++;

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
            // Only care if the source product has a meaningful amount of buyers (e.g., > 2)
            if ($itemBasketCounts[$source] < 3) {
                continue;
            }

            arsort($targets);
            $topAssociated = array_slice($targets, 0, 5, true);
            $affinities[] = [
                'item' => $source,
                'total_baskets' => $itemBasketCounts[$source],
                'associations' => $topAssociated,
            ];
        }

        // Sort heavily demanded products first
        usort($affinities, function ($a, $b) {
            return $b['total_baskets'] <=> $a['total_baskets'];
        });

        // --- DISTORA AI NARRATIVE GENERATOR ---
        $topAsso = count($affinities) > 0 ? $affinities[0] : null;
        $aiNarrative = '🔍 Fakta: Mesin Basket Analysis mendeteksi afinitas keranjang. '.($topAsso ? "Produk {$topAsso['item']} paling sering diborong bersaman dengan item lain di {$topAsso['total_baskets']} toko berbeda." : 'Belum ada pola keranjang terbentuk yang signifikan.')."\n".
                       '💡 Saran Eksekusi: Jadikan produk teratas ini sebagai "Lokomotif". Gabungkan/bundling secara paksa produk Dead-Stock (Gerbong) bersama produk Lokomotif ini untuk mempercepat penetrasi cuci gudang!';

        $affinities = array_slice($affinities, 0, 100);

        // --- CSV EXPORT ---
        if ($request->get('export') === 'csv') {
            $rows = [];
            foreach ($affinities as $a) {
                foreach ($a['associations'] as $target => $count) {
                    $pct = $a['total_baskets'] > 0 ? round(($count / $a['total_baskets']) * 100, 1) : 0;
                    $rows[] = [$a['item'], $a['total_baskets'], $target, $count, $pct];
                }
            }

            return $this->streamCsv(
                "CrossSelling_{$period}.csv",
                ['Produk Utama', 'Total Toko', 'Produk Terkait', 'Jumlah Toko', 'Afinitas %'],
                $rows
            );
        }

        return view('analytics.cross-selling', compact('period', 'periods', 'affinities', 'aiNarrative'));
    }

    public function marginAnalysis(Request $request)
    {
        $period = $request->get('period', Transaction::max('period') ?? date('Y-m'));
        $periods = Transaction::select('period')->distinct()->orderByDesc('period')->pluck('period');

        // Global KPIs
        $kpis = Transaction::withFilters(request())
            ->selectRaw('
                SUM(CASE WHEN type = "I" THEN taxed_amt WHEN type = "R" THEN -ABS(taxed_amt) ELSE 0 END) as total_revenue,
                SUM(CASE WHEN type = "I" THEN cogs WHEN type = "R" THEN -ABS(cogs) ELSE 0 END) as total_cogs
            ')->first();

        $totalRevenue = $kpis->total_revenue ?? 0;
        $totalCogs = $kpis->total_cogs ?? 0;
        $totalGrossProfit = $totalRevenue - $totalCogs;
        $blendedMargin = $totalRevenue > 0 ? ($totalGrossProfit / $totalRevenue) * 100 : 0;

        // Principal Margins
        $principalMargins = Transaction::withFilters(request())
            ->join('products', 'transactions.product_id', '=', 'products.id')
            ->join('principals', 'products.principal_id', '=', 'principals.id')
            ->select(
                'principals.name as principal_name',
                DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.taxed_amt WHEN transactions.type = "R" THEN -ABS(transactions.taxed_amt) ELSE 0 END) as revenue'),
                DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.cogs WHEN transactions.type = "R" THEN -ABS(transactions.cogs) ELSE 0 END) as cogs')
            )
            ->groupBy('principals.name')
            ->having('revenue', '>', 0)
            ->get()
            ->map(function ($item) {
                $item->gross_profit = $item->revenue - $item->cogs;
                $item->margin_percent = $item->revenue > 0 ? ($item->gross_profit / $item->revenue) * 100 : 0;
                $item->principal_name = str_replace('PT. ', '', $item->principal_name);

                return $item;
            })
            ->sortByDesc('margin_percent')->values();

        // Top Profitable Products
        $productMargins = Transaction::withFilters(request())
            ->join('products', 'transactions.product_id', '=', 'products.id')
            ->join('principals', 'products.principal_id', '=', 'principals.id')
            ->select(
                'products.name as product_name',
                'principals.name as principal_name',
                DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.taxed_amt WHEN transactions.type = "R" THEN -ABS(transactions.taxed_amt) ELSE 0 END) as revenue'),
                DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.cogs WHEN transactions.type = "R" THEN -ABS(transactions.cogs) ELSE 0 END) as cogs')
            )
            ->groupBy('products.name', 'principals.name')
            ->having('revenue', '>', 0)
            ->get()
            ->map(function ($item) {
                $item->gross_profit = $item->revenue - $item->cogs;
                $item->margin_percent = $item->revenue > 0 ? ($item->gross_profit / $item->revenue) * 100 : 0;

                return $item;
            })
            ->sortByDesc('gross_profit')
            ->take(50)->values();

        // --- DISTORA AI NARRATIVE GENERATOR ---
        $bottomProd = $productMargins->last() ? trim($productMargins->last()->product_name) : 'none';
        $aiNarrative = '🔍 Fakta: Blended Laba Kotor stabil di angka '.number_format($blendedMargin, 2).'% dengan cuan bersih tunai Rp '.number_format($totalGrossProfit, 0, ',', '.').".\n".
                       "💡 Saran Eksekusi: Ada produk beresiko di jajaran paling bawah (contoh: $bottomProd) yang marginnya dimakan diskon atau HPP bengkak. Kaji ulang harga dasar. Jangan sampai lelah jualan tapi hasilnya bakar duit.";

        // --- CSV EXPORT ---
        if ($request->get('export') === 'csv') {
            $rows = $productMargins->map(fn ($p) => [
                $p->product_name,
                str_replace('PT. ', '', $p->principal_name),
                $p->revenue,
                $p->cogs,
                $p->gross_profit,
                round($p->margin_percent, 2),
            ])->toArray();

            return $this->streamCsv(
                "Margin_{$period}.csv",
                ['Produk', 'Principal', 'Revenue', 'COGS', 'Gross Profit', 'Margin %'],
                $rows
            );
        }

        return view('analytics.margin', compact(
            'period', 'periods', 'totalRevenue', 'totalCogs', 'totalGrossProfit', 'blendedMargin',
            'principalMargins', 'productMargins', 'aiNarrative'
        ));
    }

    public function targetTracker(Request $request)
    {
        $period = $request->get('period', Transaction::max('period') ?? date('Y-m'));
        $periods = Transaction::select('period')->distinct()->orderByDesc('period')->pluck('period');

        // MTD Current Performance
        $salesmenPerformances = Transaction::withFilters(request())
            ->invoices()
            ->join('salesmen', 'transactions.salesman_id', '=', 'salesmen.id')
            ->select(
                'salesmen.id as salesman_id',
                'salesmen.name as salesman_name',
                DB::raw('SUM(transactions.taxed_amt) as total_revenue')
            )
            ->groupBy('salesmen.id', 'salesmen.name')
            ->orderByDesc('total_revenue')
            ->get();

        // 3-Month Historical Period Calculation
        $currentCarbon = Carbon::createFromFormat('Y-m', $period);
        $pastPeriods = [];
        for ($i = 1; $i <= 3; $i++) {
            $pastPeriods[] = (clone $currentCarbon)->subMonths($i)->format('Y-m');
        }

        // Fetch Historical 3-Month Sales per Salesman
        $historicalSalesQuery = DB::table('transactions')
            ->whereIn('transactions.period', $pastPeriods)
            ->where('transactions.type', 'I')
            ->select('transactions.salesman_id', DB::raw('SUM(transactions.taxed_amt) as hist_revenue'))
            ->groupBy('transactions.salesman_id');

        // Manually apply Principal Filter (but ignore Date filters to protect the 3-Month logic)
        if ($request->has('principal_id') && ! empty($request->get('principal_id')) && $request->get('principal_id') !== 'all') {
            $historicalSalesQuery->join('products', 'transactions.product_id', '=', 'products.id')
                ->where('products.principal_id', $request->get('principal_id'));
        }

        $historicalSales = $historicalSalesQuery->get()->keyBy('salesman_id');

        $totalHistoricalSales = $historicalSales->sum('hist_revenue');
        // Prevent div by zero if there's absolutely no historical data
        if ($totalHistoricalSales <= 0) {
            $totalHistoricalSales = 1;
        }

        // The user inputs a TOTAL TEAM TARGET
        $teamTarget = $request->get('base_target', 10000000000); // 10 Billion as default team target

        // Simulated day of month
        $isCurrentMonth = ($period == date('Y-m'));
        $workingDays = 26;
        $currentDay = $isCurrentMonth ? (int) date('j') : 26;
        $remainingDays = $workingDays - $currentDay;
        if ($remainingDays <= 0) {
            $remainingDays = 1;
        }

        // Fetch existing SAVED targets from DB
        $savedTargets = SalesmanTarget::where('period', $period)->get()->keyBy('salesman_id');

        $tracking = $salesmenPerformances->map(function ($item) use ($teamTarget, $remainingDays, $historicalSales, $totalHistoricalSales, $savedTargets) {
            // Find historical contribution
            $histSales = $historicalSales->get($item->salesman_id)->hist_revenue ?? 0;
            $contributionRatio = $histSales / $totalHistoricalSales;

            // Priority: Use SAVED target from DB. Fallback to recommendation calculation.
            if ($savedTargets->has($item->salesman_id)) {
                $item->target = $savedTargets->get($item->salesman_id)->target_amount;
                $item->is_custom = true;
            } else {
                $item->target = $contributionRatio * $teamTarget;
                $item->is_custom = false;
            }

            $item->historical_ratio = $contributionRatio * 100; // For UI info
            $item->shortfall = $item->target - $item->total_revenue;
            if ($item->shortfall < 0) {
                $item->shortfall = 0;
            }

            $item->progress = $item->target > 0 ? ($item->total_revenue / $item->target) * 100 : 100;
            // Removed 100% cap to show real performance (e.g. 102%)

            $item->required_run_rate = $item->shortfall / $remainingDays;

            return $item;
        });

        // Re-sort based on their real progress
        $tracking = $tracking->sortByDesc('progress')->values();

        // --- DISTORA AI NARRATIVE GENERATOR ---
        $underperformers = $tracking->filter(fn ($t) => $t->progress < 80)->count();
        $totalSalesMTD = $tracking->sum('total_revenue');
        $totalGap = $teamTarget - $totalSalesMTD;
        $isAchieved = $totalGap <= 0;

        if ($isAchieved) {
            $aiNarrative = '🏆 Fakta: Target Global Perusahaan TELAH TERCAPAI! Akumulasi sales Rp '.number_format($totalSalesMTD, 0, ',', '.').".\n".
                           "🎉 Selamat: Seluruh tim telah bekerja keras melampaui target kolektif.\n".
                           "💡 Saran Eksekusi: Manfaatkan sisa $remainingDays hari untuk mendelegasikan stok ke outlet premium dan kunci PO untuk stok bulan depan!";
        } else {
            $runRate = $totalGap / max(1, $remainingDays);
            $aiNarrative = "🔍 Fakta: Sisa hari kerja aktif tinggal $remainingDays hari. Seluruh tim butuh berlari dengan pace kolektif Rp ".number_format($runRate, 0, ',', '.')." / hari.\n".
                           ($underperformers > 0
                            ? "⚠️ Peringatan: Ada $underperformers Salesman yang progressnya masih lampu merah (<80%).\n"
                            : "✅ Progress: Luar biasa! Seluruh salesman sudah berada di jalur yang benar (>80%).\n").
                           '💡 Saran Eksekusi: '.($underperformers > 0 ? 'Bantu tim yang masih berdarah-darah untuk mendobrak sales!' : 'Jaga momentum dan pastikan semua kiriman terproses tepat waktu!');
        }

        // --- CSV EXPORT ---
        if ($request->get('export') === 'csv') {
            $rows = $tracking->map(fn ($t) => [
                $t->salesman_name,
                $t->target,
                $t->total_revenue,
                round($t->progress, 2),
                $t->shortfall,
                $t->required_run_rate,
                round($t->historical_ratio, 2),
            ])->toArray();

            return $this->streamCsv(
                "TargetTracker_{$period}.csv",
                ['Salesman', 'Target', 'Sales MTD', 'Progress %', 'Shortfall', 'Run Rate/Hari', 'Kontribusi Historis %'],
                $rows
            );
        }

        return view('analytics.target-tracker', compact('period', 'periods', 'tracking', 'teamTarget', 'remainingDays', 'currentDay', 'workingDays', 'isCurrentMonth', 'aiNarrative'));
    }

    /**
     * Batch save salesman targets for a specific period
     */
    public function saveTargets(Request $request)
    {
        $validated = $request->validate([
            'period' => ['required', 'date_format:Y-m'],
            'targets' => ['required', 'array'],
            'targets.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $period = $validated['period'];
        $targets = $validated['targets']; // [salesman_id => amount]
        $validSalesmanIds = Salesman::whereIn('id', array_keys($targets))
            ->pluck('id')
            ->all();

        foreach ($targets as $salesmanId => $amount) {
            if ($amount === null || $amount < 0) {
                continue;
            }
            if (! in_array((int) $salesmanId, $validSalesmanIds, true)) {
                continue;
            }

            SalesmanTarget::updateOrCreate(
                ['salesman_id' => $salesmanId, 'period' => $period],
                ['target_amount' => $amount]
            );
        }

        return back()->with('success', 'Target berhasil disimpan ke database!');
    }

    public function restockPredictor(Request $request)
    {
        $principals = Principal::orderBy('name')->pluck('name', 'id');
        $selectedPrincipal = $request->get('principal_id', 'all');
        $search = $request->get('search');

        $targetDate = Carbon::now();
        $sixMonthsAgo = $targetDate->copy()->subMonths(6)->format('Y-m-d');

        $principalFilter = '';
        $bindings = [$sixMonthsAgo];

        if ($selectedPrincipal !== 'all') {
            $principalFilter = ' AND pr.principal_id = ? ';
            $bindings[] = $selectedPrincipal;
        }

        // MySQL 8.0+ Window Function to calculate average days between purchases per outlet per product
        $sql = "
            SELECT 
                o.name as outlet_name,
                pr.name as product_name,
                p.name as principal_name,
                agg.avg_cycle_days,
                agg.avg_qty_per_order,
                agg.last_purchase_date,
                DATE_ADD(agg.last_purchase_date, INTERVAL ROUND(agg.avg_cycle_days) DAY) as next_purchase_date
            FROM (
                SELECT 
                    outlet_id, 
                    product_id, 
                    AVG(DATEDIFF(so_date, prev_date)) as avg_cycle_days,
                    AVG(daily_qty) as avg_qty_per_order,
                    MAX(so_date) as last_purchase_date,
                    COUNT(so_date) as purchase_count
                FROM (
                    SELECT 
                        t.outlet_id, 
                        t.product_id, 
                        t.so_date,
                        SUM(t.qty_base) as daily_qty,
                        LAG(t.so_date) OVER (PARTITION BY t.outlet_id, t.product_id ORDER BY t.so_date) as prev_date
                    FROM transactions t
                    JOIN products pr ON t.product_id = pr.id
                    WHERE t.so_date >= ? AND t.type = 'I'
                    $principalFilter
                    GROUP BY t.outlet_id, t.product_id, t.so_date
                ) AS sub
                WHERE prev_date IS NOT NULL AND so_date != prev_date
                GROUP BY outlet_id, product_id
                HAVING purchase_count > 1 AND avg_cycle_days > 5
            ) as agg
            JOIN outlets o ON agg.outlet_id = o.id
            JOIN products pr ON agg.product_id = pr.id
            JOIN principals p ON pr.principal_id = p.id
        ";

        if (! empty($search)) {
            $sql .= ' WHERE o.name LIKE ? OR pr.name LIKE ?';
            $bindings[] = "%{$search}%";
            $bindings[] = "%{$search}%";
        }

        $sql .= ' ORDER BY next_purchase_date ASC LIMIT 3000';

        $results = DB::select($sql, $bindings);

        $predictions = [];
        $groupedOutlets = [];

        foreach ($results as $row) {
            $nextDate = Carbon::parse($row->next_purchase_date);
            $diffDays = (int) $targetDate->diffInDays($nextDate, false);

            $row->diff_days = $diffDays;

            $predictions[] = $row;

            if (! isset($groupedOutlets[$row->outlet_name])) {
                $groupedOutlets[$row->outlet_name] = [
                    'outlet_name' => $row->outlet_name,
                    'items' => [],
                ];
            }

            $groupedOutlets[$row->outlet_name]['items'][] = $row;
        }

        $groupedOutlets = array_values($groupedOutlets);
        usort($groupedOutlets, function ($a, $b) {
            return count($b['items']) <=> count($a['items']); // Sort by total items
        });

        foreach ($groupedOutlets as &$g) {
            usort($g['items'], function ($a, $b) {
                return $a->avg_cycle_days <=> $b->avg_cycle_days; // Sort by cycle length
            });
        }
        unset($g);

        $totalAnalyzed = count($results);
        $totalOutlets = count($groupedOutlets);

        // --- CSV EXPORT ---
        if ($request->get('export') === 'csv') {
            $rows = array_map(function ($p) {
                return [
                    $p->outlet_name,
                    $p->product_name,
                    $p->principal_name,
                    round($p->avg_cycle_days),
                    round($p->avg_qty_per_order),
                    $p->last_purchase_date,
                    $p->next_purchase_date,
                ];
            }, $predictions);

            return $this->streamCsv(
                "PolaSiklusToko_{$targetDate->format('Ymd')}.csv",
                ['Toko', 'Produk', 'Principal', 'Siklus Rata-Rata (Hari)', 'Rata-Rata Volume Beli', 'Terakhir Beli', 'Estimasi Pesan Berikutnya'],
                $rows
            );
        }

        $perPage = 30;
        $page = Paginator::resolveCurrentPage() ?: 1;
        $groupedCollection = collect($groupedOutlets);
        $paginatedPredictions = new LengthAwarePaginator(
            $groupedCollection->forPage($page, $perPage),
            $groupedCollection->count(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath(), 'query' => $request->query()]
        );

        $aiNarrative = "🔍 Fakta: AI memonitor {$totalAnalyzed} pola beli-ulang dari {$totalOutlets} toko.\n💡 Insight: Tampilan ini murni menampilkan pola perilaku belanja tiap toko (Siklus Hari & Rata-rata Volume). Gunakan data ini untuk memahami karakter pesanan outlet.";

        return view('analytics.restock-predictor', compact(
            'paginatedPredictions', 'principals', 'selectedPrincipal', 'search',
            'totalAnalyzed', 'totalOutlets', 'aiNarrative'
        ));
    }

    public function cohortAnalysis(Request $request)
    {
        // 1. Get first transaction month for each outlet
        $cohorts = DB::table('transactions')
            ->select('outlet_id', DB::raw('MIN(period) as cohort_month'))
            ->groupBy('outlet_id')
            ->get()
            ->keyBy('outlet_id');

        // 2. Get distinct transactions per outlet per period
        $allTxns = DB::table('transactions')
            ->select('outlet_id', 'period')
            ->distinct()
            ->orderBy('period')
            ->get();

        $matrix = [];
        $periods = [];

        foreach ($allTxns as $txn) {
            $period = $txn->period;
            if (! in_array($period, $periods)) {
                $periods[] = $period;
            }

            $cohortMonth = $cohorts[$txn->outlet_id]->cohort_month ?? null;
            if (! $cohortMonth) {
                continue;
            }

            if (! isset($matrix[$cohortMonth])) {
                $matrix[$cohortMonth] = [];
            }
            if (! isset($matrix[$cohortMonth][$period])) {
                $matrix[$cohortMonth][$period] = 0;
            }

            $matrix[$cohortMonth][$period]++;
        }

        sort($periods);
        ksort($matrix);

        return view('analytics.cohort', compact('matrix', 'periods'));
    }

    public function generateReport(Request $request)
    {
        $period = $request->get('period', Transaction::max('period') ?? date('Y-m'));
        $periods = Transaction::select('period')->distinct()->orderByDesc('period')->pluck('period');
        $principalName = 'Semua Principal';

        if ($request->has('principal_id') && $request->get('principal_id') !== 'all') {
            $principal = Principal::find($request->get('principal_id'));
            if ($principal) {
                $principalName = $principal->name;
            }
        }

        // --- EXCEL EXPORT BRANCH ---
        if ($request->get('export') === 'excel') {
            set_time_limit(300);

            $filename = 'Buku_Rapor_360_'.str_replace([' ', '/'], ['_', '-'], $principalName).'_'.$period.'.xlsx';

            return Excel::download(new BukuRaporExport($request, $period, $principalName), $filename);
        }

        // 1. Basic KPIs & Profitability
        $kpis = Transaction::withFilters(request())
            ->invoices()
            ->selectRaw('
                SUM(taxed_amt) as total_omset,
                SUM(cogs) as total_cogs,
                SUM(gross) as gross_sales,
                SUM(disc_total) as total_discount,
                COUNT(DISTINCT so_no) as invoice_count,
                COUNT(DISTINCT outlet_id) as outlet_count
            ')->first();

        $totalReturns = (float) Transaction::withFilters(request())->returns()->sum(DB::raw('ABS(taxed_amt)'));
        $totalOmset = (float) ($kpis->total_omset ?? 0);
        $netSales = $totalOmset - $totalReturns;
        $totalCogs = $kpis->total_cogs ?? 0;
        $grossProfit = $netSales - $totalCogs;
        $totalDiscount = $kpis->total_discount ?? 0;
        $blendedMargin = $netSales > 0 ? ($grossProfit / $netSales) * 100 : 0;
        $returnRate = ($netSales + $totalReturns) > 0 ? ($totalReturns / ($netSales + $totalReturns)) * 100 : 0;
        $invoiceCount = (int) ($kpis->invoice_count ?? 0);
        $outletCount = (int) ($kpis->outlet_count ?? 0);

        // MoM Growth
        $prevPeriod = Carbon::parse($period.'-01')->subMonth()->format('Y-m');
        $prevReq = new Request;
        $prevReq->merge(['start_period' => $prevPeriod, 'end_period' => $prevPeriod, 'principal_id' => $request->get('principal_id')]);
        $prevNetSales = (float) Transaction::withFilters($prevReq)->invoices()->sum('taxed_amt')
            - (float) Transaction::withFilters($prevReq)->returns()->sum(DB::raw('ABS(taxed_amt)'));
        $momGrowth = $prevNetSales > 0 ? (($netSales - $prevNetSales) / $prevNetSales) * 100 : 0;

        // 2. Product Top Movers (with COGS for margin)
        $topProducts = Transaction::withFilters(request())
            ->join('products', 'transactions.product_id', '=', 'products.id')
            ->select(
                'products.name as product_name',
                DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.taxed_amt WHEN transactions.type = "R" THEN -ABS(transactions.taxed_amt) ELSE 0 END) as revenue'),
                DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.cogs WHEN transactions.type = "R" THEN -ABS(transactions.cogs) ELSE 0 END) as cogs')
            )
            ->groupBy('products.name')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get();

        // 3. Sleeping Outlets Quick Count
        $dateStart = Carbon::parse(strlen($period) == 7 ? $period.'-01' : clone $period);
        if (strlen($period) == 7) {
            $dateStart = Carbon::parse($period.'-01');
            $prevStartPeriod = $dateStart->copy()->subMonth()->format('Y-m');
        } else {
            $prevStartPeriod = Carbon::now()->subMonth()->format('Y-m');
        }

        $prevRequest = new Request;
        $prevRequest->merge([
            'start_period' => $prevStartPeriod,
            'end_period' => $prevStartPeriod,
            'principal_id' => $request->get('principal_id'),
        ]);

        $prevOutlets = Transaction::withFilters($prevRequest)
            ->select('outlet_id', DB::raw('SUM(CASE WHEN type = "I" THEN taxed_amt WHEN type = "R" THEN -ABS(taxed_amt) ELSE 0 END) as prev_sales'))
            ->groupBy('outlet_id')
            ->having('prev_sales', '>', 0)
            ->get()
            ->keyBy('outlet_id');

        $currentOutlets = Transaction::withFilters(request())
            ->select('outlet_id')
            ->groupBy('outlet_id')
            ->having(DB::raw('SUM(CASE WHEN type = "I" THEN taxed_amt WHEN type = "R" THEN -ABS(taxed_amt) ELSE 0 END)'), '>', 0)
            ->pluck('outlet_id')
            ->toArray();

        $churnedOutletIds = $prevOutlets->keys()->diff($currentOutlets);
        $sleepingOutletsCount = $churnedOutletIds->count();
        $sleepingOutletsLoss = 0;
        foreach ($churnedOutletIds as $id) {
            $sleepingOutletsLoss += $prevOutlets[$id]->prev_sales;
        }

        // 4. Margin per Principal
        $principalMargins = Transaction::withFilters(request())
            ->join('products', 'transactions.product_id', '=', 'products.id')
            ->join('principals', 'products.principal_id', '=', 'principals.id')
            ->select(
                'principals.name as principal_name',
                DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.taxed_amt WHEN transactions.type = "R" THEN -ABS(transactions.taxed_amt) ELSE 0 END) as revenue'),
                DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.cogs WHEN transactions.type = "R" THEN -ABS(transactions.cogs) ELSE 0 END) as cogs')
            )
            ->groupBy('principals.name')
            ->having('revenue', '>', 0)
            ->get()
            ->map(function ($item) use ($netSales) {
                $item->gross_profit = $item->revenue - $item->cogs;
                $item->margin_percent = $item->revenue > 0 ? ($item->gross_profit / $item->revenue) * 100 : 0;
                $item->contribution = $netSales > 0 ? ($item->revenue / $netSales) * 100 : 0;
                $item->principal_name = str_replace('PT. ', '', $item->principal_name);

                return $item;
            })
            ->sortByDesc('revenue')->values();

        // 5. Salesman Profitability (Top 10)
        $topSalesmen = Transaction::withFilters(request())
            ->join('salesmen', 'transactions.salesman_id', '=', 'salesmen.id')
            ->select(
                'salesmen.name as salesman_name',
                DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.taxed_amt ELSE 0 END) as gross_sales'),
                DB::raw('SUM(CASE WHEN transactions.type = "R" THEN ABS(transactions.taxed_amt) ELSE 0 END) as total_returns'),
                DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.cogs WHEN transactions.type = "R" THEN -ABS(transactions.cogs) ELSE 0 END) as net_cogs'),
                DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.disc_total ELSE 0 END) as total_discount'),
                DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.gross ELSE 0 END) as total_gross'),
                DB::raw('COUNT(DISTINCT CASE WHEN transactions.type = "I" THEN transactions.outlet_id END) as outlet_count'),
                DB::raw('COUNT(DISTINCT CASE WHEN transactions.type = "I" THEN transactions.so_no END) as invoice_count')
            )
            ->groupBy('salesmen.name')
            ->having('gross_sales', '>', 0)
            ->get()
            ->map(function ($s) {
                $s->net_sales = $s->gross_sales - $s->total_returns;
                $s->gross_profit = $s->net_sales - $s->net_cogs;
                $s->margin_percent = $s->net_sales > 0 ? ($s->gross_profit / $s->net_sales) * 100 : 0;
                $s->discount_depth = $s->total_gross > 0 ? ($s->total_discount / $s->total_gross) * 100 : 0;

                return $s;
            })
            ->sortByDesc('gross_profit')->take(10)->values();

        // 6. Outlet Trajectory Summary (6-month lookback)
        $endDate = Carbon::parse($period.'-01');
        $lookbackMonths = 6;
        $startDate = $endDate->copy()->subMonths($lookbackMonths - 1);
        $periodRange = [];
        for ($i = 0; $i < $lookbackMonths; $i++) {
            $periodRange[] = $startDate->copy()->addMonths($i)->format('Y-m');
        }

        $rawQuery = Transaction::query()
            ->whereIn('transactions.period', $periodRange)
            ->join('outlets', 'transactions.outlet_id', '=', 'outlets.id');
        if ($request->has('principal_id') && ! empty($request->get('principal_id')) && $request->get('principal_id') !== 'all') {
            $rawQuery->whereHas('product', fn ($q) => $q->where('principal_id', $request->get('principal_id')));
        }
        $monthlySales = $rawQuery->select(
            'transactions.outlet_id',
            'transactions.period',
            DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.taxed_amt WHEN transactions.type = "R" THEN -ABS(transactions.taxed_amt) ELSE 0 END) as net_sales')
        )->groupBy('transactions.outlet_id', 'transactions.period')->get()->groupBy('outlet_id');

        $trajectorySegments = ['Growing' => 0, 'Stable' => 0, 'Declining' => 0, 'New' => 0, 'Dead' => 0];
        foreach ($monthlySales as $outletId => $monthlyData) {
            $activeMonths = $monthlyData->pluck('net_sales', 'period');
            $values = [];
            foreach ($periodRange as $p) {
                $values[] = (float) ($activeMonths[$p] ?? 0);
            }
            $n = count($values);
            $monthCount = count(array_filter($values, fn ($v) => $v > 0));
            $latestSales = end($values);
            $prevSalesVal = $values[$n - 2] ?? 0;

            $sumX = $sumY = $sumXY = $sumX2 = 0;
            for ($i = 0; $i < $n; $i++) {
                $sumX += $i;
                $sumY += $values[$i];
                $sumXY += $i * $values[$i];
                $sumX2 += $i * $i;
            }
            $denom = ($n * $sumX2) - ($sumX * $sumX);
            $slope = $denom > 0 ? (($n * $sumXY) - ($sumX * $sumY)) / $denom : 0;
            $avgSales = array_sum($values) / max($monthCount, 1);
            $slopePct = $avgSales > 0 ? ($slope / $avgSales) * 100 : 0;

            if ($monthCount <= 1 && $latestSales > 0) {
                $classification = 'New';
            } elseif ($monthCount <= 1 && $latestSales <= 0) {
                $classification = 'Dead';
            } elseif ($latestSales <= 0 && $prevSalesVal <= 0) {
                $classification = 'Dead';
            } elseif ($slopePct > 10) {
                $classification = 'Growing';
            } elseif ($slopePct < -10) {
                $classification = 'Declining';
            } else {
                $classification = 'Stable';
            }
            $trajectorySegments[$classification]++;
        }
        $totalTrajectoryOutlets = array_sum($trajectorySegments);

        // 7. Pareto Summary
        $paretoData = Transaction::withFilters(request())->invoices()
            ->join('products', 'transactions.product_id', '=', 'products.id')
            ->select('products.name', DB::raw('SUM(transactions.taxed_amt) as total_sales'))
            ->groupBy('products.name')->having('total_sales', '>', 0)
            ->orderByDesc('total_sales')->get();
        $totalParetoRev = (float) $paretoData->sum('total_sales');
        $paretoCumulative = 0.0;
        $paretoKlasses = ['A' => 0, 'B' => 0, 'C' => 0];
        foreach ($paretoData as $item) {
            $pct = $totalParetoRev > 0 ? ($item->total_sales / $totalParetoRev) * 100 : 0;
            $paretoCumulative += $pct;
            if ($paretoCumulative <= 80) {
                $paretoKlasses['A']++;
            } elseif ($paretoCumulative <= 95) {
                $paretoKlasses['B']++;
            } else {
                $paretoKlasses['C']++;
            }
        }
        $totalParetoProducts = array_sum($paretoKlasses);

        // 8. Promo Uplift Summary
        $allPeriods = Transaction::select('period')->distinct()->orderByDesc('period')->pluck('period');
        $promoReq = clone $request;
        if (! $request->has('start_period') && ! $request->has('end_period') && $allPeriods->isNotEmpty()) {
            $promoReq->merge(['start_period' => $allPeriods->last(), 'end_period' => $allPeriods->first()]);
        }
        $promoData = Transaction::withFilters($promoReq)->invoices()
            ->where('transactions.gross', '>', 0)
            ->join('products', 'transactions.product_id', '=', 'products.id')
            ->select(
                'transactions.product_id', 'transactions.period',
                DB::raw('SUM(transactions.qty_base) as total_qty'),
                DB::raw('SUM(transactions.gross) as total_gross'),
                DB::raw('SUM(transactions.disc_total) as total_discount'),
                DB::raw('SUM(transactions.cogs) as total_cogs'),
                DB::raw('ROUND((SUM(transactions.disc_total) / SUM(transactions.gross)) * 100, 2) as discount_pct')
            )
            ->groupBy('transactions.product_id', 'transactions.period')
            ->havingRaw('SUM(transactions.qty_base) > 0')->get();

        $promoGrouped = [];
        foreach ($promoData as $row) {
            $promoGrouped[$row->product_id]['periods'][$row->period] = $row;
        }

        $promoSuccessCount = 0;
        $promoFailCount = 0;
        $promoTotalSubsidy = 0;
        foreach ($promoGrouped as $prod) {
            $periodData = $prod['periods'];
            if (count($periodData) < 2) {
                continue;
            }
            $sorted = collect($periodData)->sortBy('discount_pct')->values();
            $baseline = $sorted->first();
            $promo = $sorted->last();
            if (($promo->discount_pct - $baseline->discount_pct) < 3 || $promo->total_qty < 10 || $baseline->total_qty < 10) {
                continue;
            }
            $profitNormal = ($baseline->total_gross - $baseline->total_discount) - $baseline->total_cogs;
            $profitPromo = ($promo->total_gross - $promo->total_discount) - $promo->total_cogs;
            if (($profitPromo - $profitNormal) > 0) {
                $promoSuccessCount++;
            } else {
                $promoFailCount++;
            }
            $promoTotalSubsidy += $promo->total_discount;
        }

        return view('analytics.report', compact(
            'period', 'periods', 'principalName', 'netSales', 'totalCogs', 'grossProfit',
            'totalDiscount', 'blendedMargin', 'topProducts', 'totalReturns', 'returnRate',
            'invoiceCount', 'outletCount', 'momGrowth', 'prevNetSales',
            'sleepingOutletsCount', 'sleepingOutletsLoss',
            'principalMargins', 'topSalesmen',
            'trajectorySegments', 'totalTrajectoryOutlets',
            'paretoKlasses', 'totalParetoProducts',
            'promoSuccessCount', 'promoFailCount', 'promoTotalSubsidy'
        ));
    }

    /**
     * Promo Uplift & ROI Analytics
     * Compares Baseline (lowest discount %) month vs Promo Spike (highest discount %) month per product.
     * Detects anomalies: Stockout (high discount but volume drops) and Forward Buying (T-1 volume spike).
     */
    public function promoUplift(Request $request)
    {
        $period = $request->get('period', Transaction::max('period') ?? date('Y-m'));
        $periods = Transaction::select('period')->distinct()->orderByDesc('period')->pluck('period');

        // Promo Uplift needs multi-month data to compare baseline vs promo months.
        // If no explicit period filter is set, auto-inject the full available range.
        if (! $request->has('start_period') && ! $request->has('end_period') && $periods->isNotEmpty()) {
            $request->merge([
                'start_period' => $periods->last(),
                'end_period' => $periods->first(),
            ]);
        }

        // Pull monthly data per product with filters applied
        $data = Transaction::withFilters($request)
            ->invoices()
            ->where('transactions.gross', '>', 0)
            ->join('products', 'transactions.product_id', '=', 'products.id')
            ->join('principals', 'products.principal_id', '=', 'principals.id')
            ->select(
                'transactions.product_id',
                'products.name as product_name',
                'principals.name as principal_name',
                'transactions.period',
                DB::raw('SUM(transactions.qty_base) as total_qty'),
                DB::raw('SUM(transactions.gross) as total_gross'),
                DB::raw('SUM(transactions.disc_total) as total_discount'),
                DB::raw('SUM(transactions.cogs) as total_cogs'),
                DB::raw('ROUND((SUM(transactions.disc_total) / SUM(transactions.gross)) * 100, 2) as discount_pct')
            )
            ->groupBy('transactions.product_id', 'products.name', 'principals.name', 'transactions.period')
            ->havingRaw('SUM(transactions.qty_base) > 0')
            ->get();

        // Group by product
        $grouped = [];
        foreach ($data as $row) {
            if (! isset($grouped[$row->product_id])) {
                $grouped[$row->product_id] = [
                    'name' => $row->product_name,
                    'principal' => str_replace('PT. ', '', $row->principal_name),
                    'periods' => [],
                ];
            }
            $grouped[$row->product_id]['periods'][$row->period] = $row;
        }

        $results = [];
        $successCount = 0;
        $failCount = 0;
        $totalSubsidy = 0;
        $anomalyCount = 0;

        foreach ($grouped as $pid => $prod) {
            $periodData = $prod['periods'];
            if (count($periodData) < 2) {
                continue;
            }

            // Sort periods by discount_pct to find baseline (lowest) and promo (highest)
            $sorted = collect($periodData)->sortBy('discount_pct')->values();
            $baseline = $sorted->first();
            $promo = $sorted->last();

            // Skip if discount difference is too small to be meaningful
            if (($promo->discount_pct - $baseline->discount_pct) < 3) {
                continue;
            }
            // Skip very low volume noise
            if ($promo->total_qty < 10 || $baseline->total_qty < 10) {
                continue;
            }

            $upliftQty = $promo->total_qty - $baseline->total_qty;
            $upliftPct = $baseline->total_qty > 0 ? (($promo->total_qty - $baseline->total_qty) / $baseline->total_qty) * 100 : 0;

            $profitNormal = ($baseline->total_gross - $baseline->total_discount) - $baseline->total_cogs;
            $profitPromo = ($promo->total_gross - $promo->total_discount) - $promo->total_cogs;
            $profitDiff = $profitPromo - $profitNormal;

            $isSuccess = $profitDiff > 0;
            if ($isSuccess) {
                $successCount++;
            } else {
                $failCount++;
            }
            $totalSubsidy += $promo->total_discount;

            // --- ANOMALY DETECTION ---
            $anomalyFlags = [];

            // 1. STOCKOUT: Discount goes UP but volume drops >= 30%
            if ($promo->discount_pct > $baseline->discount_pct && $upliftPct <= -30) {
                $anomalyFlags[] = 'STOCKOUT';
                $anomalyCount++;
            }

            // 2. FORWARD BUYING: Check if T-1 (month before promo) had qty spike without discount increase
            $promoMonth = Carbon::parse($promo->period.'-01');
            $t1Period = $promoMonth->copy()->subMonth()->format('Y-m');
            if (isset($periodData[$t1Period])) {
                $t1 = $periodData[$t1Period];
                $t1QtyChange = $baseline->total_qty > 0 ? (($t1->total_qty - $baseline->total_qty) / $baseline->total_qty) * 100 : 0;
                $t1DiscDiff = $t1->discount_pct - $baseline->discount_pct;
                // If T-1 volume spiked >= 40% without meaningful discount increase (<3pp)
                if ($t1QtyChange >= 40 && $t1DiscDiff < 3) {
                    $anomalyFlags[] = 'FORWARD BUY';
                    $anomalyCount++;
                }
            }

            $results[] = [
                'product_name' => $prod['name'],
                'principal_name' => $prod['principal'],
                'baseline_period' => $baseline->period,
                'baseline_disc_pct' => $baseline->discount_pct,
                'baseline_qty' => (int) $baseline->total_qty,
                'baseline_profit' => $profitNormal,
                'promo_period' => $promo->period,
                'promo_disc_pct' => $promo->discount_pct,
                'promo_qty' => (int) $promo->total_qty,
                'promo_subsidy' => (float) $promo->total_discount,
                'promo_profit' => $profitPromo,
                'uplift_qty' => $upliftQty,
                'uplift_pct' => $upliftPct,
                'profit_diff' => $profitDiff,
                'is_success' => $isSuccess,
                'anomaly_flags' => $anomalyFlags,
            ];
        }

        // Sort by profit_diff descending (best ROI first)
        usort($results, function ($a, $b) {
            return $b['profit_diff'] <=> $a['profit_diff'];
        });

        // Chart data: top 15 by profit_diff
        $chartData = array_slice($results, 0, 15);

        // --- DISTORA AI NARRATIVE GENERATOR ---
        $totalAnalyzed = count($results);
        $aiNarrative = "🔍 Fakta: Mesin DistoraVision berhasil membedah $totalAnalyzed produk yang mengalami pergeseran diskon signifikan antar periode.\n"
            ."✅ Hasil: $successCount promo SUKSES menghasilkan laba tambahan. $failCount promo GAGAL (Rugi Bandar).\n"
            .($anomalyCount > 0
                ? "⚠️ Anomali: Terdeteksi $anomalyCount kejadian mencurigakan (barang kosong pabrik atau toko menimbun). Periksa flag merah di tabel!\n"
                : '')
            .'💡 Saran Eksekusi: Ulangi promo yang SUKSES bulan depan. Untuk yang GAGAL, bekukan program hingga stok dan strategi dimatangkan!';

        // --- CSV EXPORT ---
        if ($request->get('export') === 'csv') {
            $rows = array_map(fn ($r) => [
                $r['product_name'],
                $r['principal_name'],
                $r['baseline_period'],
                $r['baseline_disc_pct'],
                $r['baseline_qty'],
                $r['promo_period'],
                $r['promo_disc_pct'],
                $r['promo_qty'],
                round($r['uplift_pct'], 1),
                $r['promo_subsidy'],
                $r['profit_diff'],
                $r['is_success'] ? 'SUKSES' : 'GAGAL',
                implode(', ', $r['anomaly_flags']),
            ], $results);

            return $this->streamCsv(
                "PromoUplift_{$period}.csv",
                ['Produk', 'Principal', 'Bln Normal', 'Disc Normal %', 'Qty Normal', 'Bln Promo', 'Disc Promo %', 'Qty Promo', 'Uplift %', 'Subsidi', 'Selisih Laba', 'Status', 'Flag'],
                $rows
            );
        }

        return view('analytics.promo-uplift', compact(
            'period', 'periods', 'results', 'chartData',
            'successCount', 'failCount', 'totalSubsidy', 'anomalyCount',
            'aiNarrative'
        ));
    }

    /**
     * Salesman Profitability Analysis
     * Ranks salesmen not just by revenue, but by gross profit contribution and margin quality.
     * Detects "Discount Kings" — salesmen who sell a lot but burn margin.
     */
    public function salesmanProfitability(Request $request)
    {
        $period = $request->get('period', Transaction::max('period') ?? date('Y-m'));
        $periods = Transaction::select('period')->distinct()->orderByDesc('period')->pluck('period');

        $startPeriod = $request->get('start_period', $request->get('period', $period));
        $endPeriod = $request->get('end_period', $request->get('period', $period));

        $salesmen = Transaction::withFilters($request)
            ->join('salesmen', 'transactions.salesman_id', '=', 'salesmen.id')
            ->select(
                'salesmen.id as salesman_id',
                'salesmen.name as salesman_name',
                DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.taxed_amt ELSE 0 END) as gross_sales'),
                DB::raw('SUM(CASE WHEN transactions.type = "R" THEN ABS(transactions.taxed_amt) ELSE 0 END) as total_returns'),
                DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.cogs WHEN transactions.type = "R" THEN -ABS(transactions.cogs) ELSE 0 END) as net_cogs'),
                DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.disc_total ELSE 0 END) as total_discount'),
                DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.gross ELSE 0 END) as total_gross'),
                DB::raw('COUNT(DISTINCT CASE WHEN transactions.type = "I" THEN transactions.outlet_id END) as outlet_count'),
                DB::raw('COUNT(DISTINCT CASE WHEN transactions.type = "I" THEN transactions.so_no END) as invoice_count')
            )
            ->groupBy('salesmen.id', 'salesmen.name')
            ->having('gross_sales', '>', 0)
            ->get()
            ->map(function ($s) {
                $s->net_sales = $s->gross_sales - $s->total_returns;
                $s->gross_profit = $s->net_sales - $s->net_cogs;
                $s->margin_percent = $s->net_sales > 0 ? ($s->gross_profit / $s->net_sales) * 100 : 0;
                $s->discount_depth = $s->total_gross > 0 ? ($s->total_discount / $s->total_gross) * 100 : 0;
                $s->return_rate = $s->gross_sales > 0 ? ($s->total_returns / $s->gross_sales) * 100 : 0;
                $s->avg_per_outlet = $s->outlet_count > 0 ? $s->net_sales / $s->outlet_count : 0;
                $s->avg_per_invoice = $s->invoice_count > 0 ? $s->net_sales / $s->invoice_count : 0;

                return $s;
            });

        // Overall KPIs
        $totalNetSales = $salesmen->sum('net_sales');
        $totalGrossProfit = $salesmen->sum('gross_profit');
        $avgMargin = $totalNetSales > 0 ? ($totalGrossProfit / $totalNetSales) * 100 : 0;

        // Assign contribution % and profitability rank
        $salesmen = $salesmen->map(function ($s) use ($totalNetSales, $totalGrossProfit) {
            $s->revenue_contribution = $totalNetSales > 0 ? ($s->net_sales / $totalNetSales) * 100 : 0;
            $s->profit_contribution = $totalGrossProfit > 0 ? ($s->gross_profit / $totalGrossProfit) * 100 : 0;

            // Efficiency Score: Profit contribution vs Revenue contribution
            // > 1 means they bring more profit than their revenue share (efficient)
            // < 1 means they burn margin relative to their sales volume (inefficient)
            $s->efficiency_ratio = $s->revenue_contribution > 0
                ? $s->profit_contribution / $s->revenue_contribution
                : 0;

            return $s;
        });

        // Sort options
        $sortBy = $request->get('sort', 'gross_profit');
        $salesmen = $salesmen->sortByDesc($sortBy)->values();

        // Rank by profit
        $profitRanked = $salesmen->sortByDesc('gross_profit')->values();
        $revenueRanked = $salesmen->sortByDesc('net_sales')->values();

        $salesmen = $salesmen->map(function ($s) use ($profitRanked, $revenueRanked) {
            $s->profit_rank = $profitRanked->search(fn ($x) => $x->salesman_id === $s->salesman_id) + 1;
            $s->revenue_rank = $revenueRanked->search(fn ($x) => $x->salesman_id === $s->salesman_id) + 1;
            $s->rank_shift = $s->revenue_rank - $s->profit_rank; // Positive = promoted when ranked by profit

            return $s;
        });

        // Detect "Discount Kings" — high revenue but low margin
        $discountKings = $salesmen->filter(fn ($s) => $s->efficiency_ratio < 0.8 && $s->net_sales > 0)->count();

        // --- DISTORA AI NARRATIVE GENERATOR ---
        $topProfit = $salesmen->sortByDesc('gross_profit')->first();
        $worstMargin = $salesmen->sortBy('margin_percent')->first();
        $aiNarrative = '🔍 Fakta: Ranking berubah drastis saat dilihat dari kacamata LABA, bukan omset. '.
            ($topProfit ? "Kontributor laba #1 adalah {$topProfit->salesman_name} dengan cuan Rp ".number_format($topProfit->gross_profit, 0, ',', '.').'.' : '')."\n".
            ($discountKings > 0
                ? "⚠️ Peringatan: Ada {$discountKings} salesman yang jualan banyak tapi marginnya HABIS dimakan diskon & return (efisiensi < 0.8x). Mereka adalah 'Raja Diskon' yang perlu diawasi!\n"
                : "✅ Bagus: Semua salesman memiliki efisiensi margin yang sehat.\n").
            ($worstMargin ? "💡 Saran: Evaluasi {$worstMargin->salesman_name} yang margin-nya hanya ".number_format($worstMargin->margin_percent, 1).'%. Apakah outlet-nya memang marjinal atau dia terlalu agresif beri diskon?' : '');

        // --- CSV EXPORT ---
        if ($request->get('export') === 'csv') {
            $rows = $salesmen->map(fn ($s) => [
                $s->salesman_name,
                $s->net_sales,
                $s->gross_profit,
                round($s->margin_percent, 2),
                round($s->discount_depth, 2),
                round($s->return_rate, 2),
                $s->outlet_count,
                $s->invoice_count,
                round($s->efficiency_ratio, 2),
                $s->profit_rank,
            ])->toArray();

            return $this->streamCsv(
                "SalesmanProfitability_{$period}.csv",
                ['Salesman', 'Net Sales', 'Gross Profit', 'Margin %', 'Discount Depth %', 'Return Rate %', 'Jumlah Outlet', 'Jumlah Invoice', 'Efisiensi Ratio', 'Rank Laba'],
                $rows
            );
        }

        return view('analytics.salesman-profitability', compact(
            'period', 'periods', 'salesmen', 'totalNetSales', 'totalGrossProfit',
            'avgMargin', 'discountKings', 'sortBy', 'aiNarrative'
        ));
    }

    /**
     * Outlet Growth Trajectory Analysis
     * Classifies each outlet as Growing 📈, Stable ➡️, Declining 📉, New 🆕, or Dead 💀
     * based on 6-month sales trend using linear regression slope.
     */
    public function outletTrajectory(Request $request)
    {
        $period = $request->get('period', Transaction::max('period') ?? date('Y-m'));
        $periods = Transaction::select('period')->distinct()->orderByDesc('period')->pluck('period');

        // Look back 6 months from the selected period
        $endDate = Carbon::parse($period.'-01');
        $lookbackMonths = 6;
        $startDate = $endDate->copy()->subMonths($lookbackMonths - 1);
        $periodRange = [];
        for ($i = 0; $i < $lookbackMonths; $i++) {
            $periodRange[] = $startDate->copy()->addMonths($i)->format('Y-m');
        }

        // Filter segment
        $segment = $request->get('segment', 'all');

        // Fetch monthly sales per outlet for the 6-month window
        $rawQuery = Transaction::query()
            ->whereIn('transactions.period', $periodRange)
            ->join('outlets', 'transactions.outlet_id', '=', 'outlets.id');

        // Apply principal filter if present
        if ($request->has('principal_id') && ! empty($request->get('principal_id')) && $request->get('principal_id') !== 'all') {
            $rawQuery->whereHas('product', function ($q) use ($request) {
                $q->where('principal_id', $request->get('principal_id'));
            });
        }

        $monthlySales = $rawQuery->select(
            'transactions.outlet_id',
            'outlets.name as outlet_name',
            'outlets.city',
            'outlets.code as outlet_code',
            'transactions.period',
            DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.taxed_amt WHEN transactions.type = "R" THEN -ABS(transactions.taxed_amt) ELSE 0 END) as net_sales')
        )
            ->groupBy('transactions.outlet_id', 'outlets.name', 'outlets.city', 'outlets.code', 'transactions.period')
            ->get()
            ->groupBy('outlet_id');

        $trajectories = [];
        $segments = ['Growing' => 0, 'Stable' => 0, 'Declining' => 0, 'New' => 0, 'Dead' => 0];

        foreach ($monthlySales as $outletId => $monthlyData) {
            $outlet = $monthlyData->first();
            $activeMonths = $monthlyData->pluck('net_sales', 'period');

            // Build 6-month series (fill zeroes for missing months)
            $series = [];
            foreach ($periodRange as $p) {
                $series[$p] = (float) ($activeMonths[$p] ?? 0);
            }

            $values = array_values($series);
            $n = count($values);
            $monthCount = count(array_filter($values, fn ($v) => $v > 0));
            $totalSales = array_sum($values);
            $latestSales = end($values);
            $prevSales = $values[$n - 2] ?? 0;

            // Calculate linear regression slope to determine trend direction
            $sumX = 0;
            $sumY = 0;
            $sumXY = 0;
            $sumX2 = 0;
            for ($i = 0; $i < $n; $i++) {
                $sumX += $i;
                $sumY += $values[$i];
                $sumXY += $i * $values[$i];
                $sumX2 += $i * $i;
            }
            $denominator = ($n * $sumX2) - ($sumX * $sumX);
            $slope = $denominator > 0 ? (($n * $sumXY) - ($sumX * $sumY)) / $denominator : 0;
            $avgSales = $totalSales / max($monthCount, 1);

            // Normalize slope as percentage of average sales
            $slopePct = $avgSales > 0 ? ($slope / $avgSales) * 100 : 0;

            // Classify
            if ($monthCount <= 1 && $latestSales > 0) {
                $classification = 'New';
                $icon = '🆕';
            } elseif ($monthCount <= 1 && $latestSales <= 0) {
                $classification = 'Dead';
                $icon = '💀';
            } elseif ($latestSales <= 0 && $prevSales <= 0) {
                $classification = 'Dead';
                $icon = '💀';
            } elseif ($slopePct > 10) {
                $classification = 'Growing';
                $icon = '📈';
            } elseif ($slopePct < -10) {
                $classification = 'Declining';
                $icon = '📉';
            } else {
                $classification = 'Stable';
                $icon = '➡️';
            }

            $segments[$classification]++;

            $trajectories[] = (object) [
                'outlet_id' => $outletId,
                'outlet_name' => $outlet->outlet_name,
                'outlet_code' => $outlet->outlet_code,
                'city' => $outlet->city,
                'classification' => $classification,
                'icon' => $icon,
                'total_sales' => $totalSales,
                'latest_sales' => $latestSales,
                'avg_sales' => $avgSales,
                'active_months' => $monthCount,
                'slope_pct' => round($slopePct, 1),
                'series' => $series,
            ];
        }

        // Filter by segment
        if ($segment !== 'all') {
            $trajectories = array_values(array_filter($trajectories, fn ($t) => $t->classification === $segment));
        }

        // Sort: Declining first (most urgent), then by total sales
        usort($trajectories, function ($a, $b) {
            $order = ['Declining' => 0, 'Dead' => 1, 'New' => 2, 'Stable' => 3, 'Growing' => 4];
            $classCompare = ($order[$a->classification] ?? 5) <=> ($order[$b->classification] ?? 5);
            if ($classCompare !== 0) {
                return $classCompare;
            }

            return $b->total_sales <=> $a->total_sales;
        });

        $totalOutlets = array_sum($segments);

        // --- DISTORA AI NARRATIVE GENERATOR ---
        $decliningValue = collect($trajectories)->where('classification', 'Declining')->sum('total_sales');
        $aiNarrative = "🔍 Fakta: Dari {$totalOutlets} outlet yang terdata, ".
            "{$segments['Growing']} outlet sedang TUMBUH 📈, ".
            "{$segments['Stable']} STABIL ➡️, ".
            "{$segments['Declining']} MENURUN 📉, ".
            "{$segments['New']} BARU 🆕, dan ".
            "{$segments['Dead']} MATI 💀.\n".
            ($segments['Declining'] > 0
                ? '⚠️ Perhatian: '.$segments['Declining'].' outlet sedang dalam tren PENURUNAN dengan total kontribusi Rp '.number_format($decliningValue, 0, ',', '.').". Ini adalah toko yang BELUM mati tapi sedang menuju ke sana — selamatkan sebelum terlambat!\n"
                : "✅ Semua outlet dalam kondisi sehat, tidak ada yang menunjukkan tren penurunan.\n").
            '💡 Saran: Prioritaskan kunjungan ke outlet Declining. Tanya langsung: kenapa order berkurang? Kompetitor masuk? Stok kita tidak cocok? Toko sepi pembeli?';

        // --- CSV EXPORT ---
        if ($request->get('export') === 'csv') {
            $headers = ['Outlet', 'Kode', 'Kota', 'Klasifikasi', 'Slope %', 'Bulan Aktif'];
            $salesHeaders = array_map(fn ($p) => 'Sales '.Carbon::parse($p.'-01')->format('M Y'), $periodRange);
            $headers = array_merge($headers, $salesHeaders, ['Sales Terakhir', 'Rata-rata', 'Total 6 Bln']);

            $rows = array_map(function ($t) use ($periodRange) {
                $row = [
                    $t->outlet_name,
                    $t->outlet_code,
                    $t->city,
                    $t->classification,
                    $t->slope_pct,
                    $t->active_months,
                ];
                $salesData = array_map(fn ($p) => $t->series[$p] ?? 0, $periodRange);

                return array_merge($row, $salesData, [$t->latest_sales, $t->avg_sales, $t->total_sales]);
            }, $trajectories);

            return $this->streamCsv(
                "OutletTrajectory_{$period}.csv",
                $headers,
                $rows
            );
        }

        return view('analytics.outlet-trajectory', compact(
            'period', 'periods', 'trajectories', 'segments', 'totalOutlets',
            'segment', 'periodRange', 'aiNarrative'
        ));
    }
}
