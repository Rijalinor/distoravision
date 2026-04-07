<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Outlet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\BukuRaporExport;
use App\Models\SalesmanTarget;
use App\Models\Salesman;

class AdvancedAnalyticsController extends Controller
{
    /**
     * Display Pareto Analysis (80/20 Rule) for Products and Outlets
     */
    public function pareto(Request $request)
    {
        $period = $request->get('period', Transaction::max('period') ?? date('Y-m'));
        $periods = Transaction::select('period')->distinct()->orderByDesc('period')->pluck('period');
        
        $type = $request->get('type', 'product'); // 'product' or 'outlet'

        if ($type === 'product') {
            $data = Transaction::withFilters(request())
                ->invoices()
                ->join('products', 'transactions.product_id', '=', 'products.id')
                ->select('products.name', DB::raw('SUM(transactions.ar_amt) as total_sales'))
                ->groupBy('products.name')
                ->having('total_sales', '>', 0)
                ->orderByDesc('total_sales')
                ->get();
        } else {
            $data = Transaction::withFilters(request())
                ->invoices()
                ->join('outlets', 'transactions.outlet_id', '=', 'outlets.id')
                ->select('outlets.name', DB::raw('SUM(transactions.ar_amt) as total_sales'))
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
                'cumulative' => $cumulative
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

        // --- DISTORA AI NARRATIVE GENERATOR ---
        $countA = count($classA);
        $pctA = $countA > 0 && count($data) > 0 ? ($countA / count($data)) * 100 : 0;
        
        $entityName = $type === 'product' ? 'Produk/SKU' : 'Outlet/Toko';
        $aiNarrative = "🔍 Fakta: Secara mengejutkan, hanya $countA $entityName (" . number_format($pctA, 1) . "% dari total elemen aktif) yang menyumbang 80% pendapatan utama (Kelas A Pareto).\n" .
                       "💪 Kelebihan: Efisiensi tinggi! Tim bisa fokus hanya merawat $countA aset VIP ini untuk mendapat 80% omset perusahaan.\n" .
                       "⚠️ Risiko & Saran: Ini bahaya ketergantungan ekstrem! Jika terjadi kelangkaan barang pada Top 3 $entityName, omset bulan depan akan hancur total. Segera matangkan strategi penetrasi untuk $entityName Kelas B.";

        return view('analytics.pareto', compact(
            'period', 'periods', 'type', 'paretoData', 'chartData', 
            'classA', 'classB', 'classC', 'totalRevenue', 'aiNarrative'
        ));
    }

    /**
     * Display Sleeping Outlets (Active in T-1, 0 transactions in T)
     */
    public function sleepingOutlets(Request $request)
    {
        $period = $request->get('period', Transaction::max('period') ?? date('Y-m'));
        $periods = Transaction::select('period')->distinct()->orderByDesc('period')->pluck('period');
        
        // Dynamic Range Calculation
        $startPeriod = $request->get('start_period', $period);
        $endPeriod = $request->get('end_period', $period);
        
        $dateStart = \Carbon\Carbon::parse($startPeriod . '-01');
        $dateEnd = \Carbon\Carbon::parse($endPeriod . '-01');
        $monthsDiff = $dateStart->diffInMonths($dateEnd) + 1; 

        // T-1 equivalent range
        $prevStartPeriod = $dateStart->copy()->subMonths($monthsDiff)->format('Y-m');
        $prevEndPeriod = $dateEnd->copy()->subMonths($monthsDiff)->format('Y-m');
        
        $previousPeriod = $prevStartPeriod === $prevEndPeriod ? $prevStartPeriod : "$prevStartPeriod s/d $prevEndPeriod";

        $prevRequest = new \Illuminate\Http\Request();
        $prevRequest->merge([
            'start_period' => $prevStartPeriod,
            'end_period' => $prevEndPeriod,
            'principal_id' => $request->get('principal_id')
        ]);

        // Outlets that purchased in previous period T-1
        $prevOutlets = Transaction::withFilters($prevRequest)
            ->invoices()
            ->select('outlet_id', DB::raw('SUM(ar_amt) as prev_sales'), DB::raw('MAX(so_date) as last_order_date'))
            ->groupBy('outlet_id')
            ->having('prev_sales', '>', 0)
            ->get()
            ->keyBy('outlet_id');

        // Outlets that purchased in current period T
        $currentOutlets = Transaction::withFilters($request)
            ->invoices()
            ->select('outlet_id')
            ->groupBy('outlet_id')
            ->having(DB::raw('SUM(ar_amt)'), '>', 0)
            ->pluck('outlet_id')
            ->toArray();

        // Get the churned outlets
        $churnedOutletIds = $prevOutlets->keys()->diff($currentOutlets);

        // Fetch outlet details
        $sleepingOutletsList = [];
        $totalLostRevenue = 0;
        
        if ($churnedOutletIds->isNotEmpty()) {
            $outlets = Outlet::whereIn('id', $churnedOutletIds)->get()->keyBy('id');
            
            foreach ($churnedOutletIds as $id) {
                if (isset($outlets[$id])) {
                    $prevSale = $prevOutlets[$id]->prev_sales;
                    $sleepingOutletsList[] = (object) [
                        'outlet' => $outlets[$id],
                        'prev_sales' => $prevSale,
                        'last_order' => $prevOutlets[$id]->last_order_date
                    ];
                    $totalLostRevenue += $prevSale;
                }
            }

            // Sort by lost revenue descending
            usort($sleepingOutletsList, function($a, $b) {
                return $b->prev_sales <=> $a->prev_sales;
            });
        }

        // --- DISTORA AI NARRATIVE GENERATOR ---
        $aiNarrative = "🔍 Fakta: Terdapat " . count($sleepingOutletsList) . " outlet yang bulan lalu berlaga aktif namun bulan ini lenyap tak berjejak. Kerugian Opportunity Loss kita mencapai Rp " . number_format($totalLostRevenue, 0, ',', '.') . ".\n" .
                       "💡 Saran Eksekusi: Ekspor daftar ini segera. Kerahkan tim Salesman ke area terbanyak untuk memukul balik. Jangan biarkan kompetitor mengambil nafas di toko-toko ini!";

        return view('analytics.sleeping-outlets', compact(
            'period', 'periods', 'previousPeriod', 'sleepingOutletsList', 'totalLostRevenue', 'aiNarrative'
        ));
    }
    public function discountEffectiveness(Request $request)
    {
        $period = $request->get('period', Transaction::max('period') ?? date('Y-m'));
        $periods = Transaction::select('period')->distinct()->orderByDesc('period')->pluck('period'); // For fallback compatibility in view logic

        // Global KPI: Totals across filtered range
        $kpi = Transaction::withFilters(request())
            ->invoices()
            ->selectRaw('
                SUM(gross) as total_gross,
                SUM(disc_total) as total_discount,
                SUM(ar_amt) as total_net
            ')->first();

        // Prevent division by zero
        $totalGross = $kpi->total_gross ?? 0;
        $totalDiscount = $kpi->total_discount ?? 0;
        $totalNet = $kpi->total_net ?? 0;
        $avgDiscountPercent = $totalGross > 0 ? ($totalDiscount / $totalGross) * 100 : 0;

        // Principal Discount Distribution
        // Compares which principal absorbs the most discount budget
        $principalDiscounts = Transaction::withFilters(request())
            ->invoices()
            ->join('products', 'transactions.product_id', '=', 'products.id')
            ->join('principals', 'products.principal_id', '=', 'principals.id')
            ->select(
                'principals.name as principal_name',
                DB::raw('SUM(transactions.gross) as gross_sales'),
                DB::raw('SUM(transactions.disc_total) as discount_given'),
                DB::raw('SUM(transactions.ar_amt) as net_sales')
            )
            ->groupBy('principals.name')
            ->having('discount_given', '>', 0)
            ->orderByDesc('discount_given')
            ->get()
            ->map(function ($item) {
                $item->discount_percent = $item->gross_sales > 0 ? ($item->discount_given / $item->gross_sales) * 100 : 0;
                $item->principal_name = str_replace('PT. ', '', $item->principal_name);
                return $item;
            });

        // Top 20 Most Heavily Discounted Products
        // Find products with high absolute discount value, calculate their % depth
        $topDiscountedProducts = Transaction::withFilters(request())
            ->invoices()
            ->join('products', 'transactions.product_id', '=', 'products.id')
            ->join('principals', 'products.principal_id', '=', 'principals.id')
            ->select(
                'products.name as product_name',
                'principals.name as principal_name',
                DB::raw('SUM(transactions.gross) as gross_sales'),
                DB::raw('SUM(transactions.disc_total) as discount_given'),
                DB::raw('SUM(transactions.qty_base) as qty_sold')
            )
            ->groupBy('products.name', 'principals.name')
            ->having('discount_given', '>', 0)
            ->orderByDesc('discount_given')
            ->limit(20)
            ->get()
            ->map(function ($item) {
                $item->discount_percent = $item->gross_sales > 0 ? ($item->discount_given / $item->gross_sales) * 100 : 0;
                return $item;
            });

        // --- DISTORA AI NARRATIVE GENERATOR ---
        $topDisc = $principalDiscounts->first() ? $principalDiscounts->first()->principal_name : 'N/A';
        $aiNarrative = "🔍 Fakta: Perusahaan telah menghentakkan diskon sebesar Rp " . number_format($totalDiscount, 0, ',', '.') . " (" . number_format($avgDiscountPercent, 2) . "% dari Gross).\n" .
                       "📊 Evaluasi: Principal yang paling menguras budget promosi adalah $topDisc.\n" .
                       "💡 Saran Eksekusi: Jika Net Margin masih hijau, diskon ini sukses menjadi magnet penetrasi pasar. Jika margin megap-megap, segera bekukan program promo agresif milik produk-produk teratas di list ini.";

        return view('analytics.discount', compact(
            'period', 'periods', 'totalGross', 'totalDiscount', 'totalNet', 'avgDiscountPercent',
            'principalDiscounts', 'topDiscountedProducts', 'aiNarrative'
        ));
    }
    public function rfmAnalysis(Request $request)
    {
        $period = $request->get('period', Transaction::max('period') ?? date('Y-m'));
        $periods = Transaction::select('period')->distinct()->orderByDesc('period')->pluck('period');
        
        $outletStats = Transaction::withFilters(request())
            ->invoices()
            ->join('outlets', 'transactions.outlet_id', '=', 'outlets.id')
            ->select(
                'outlets.name as outlet_name',
                DB::raw('MAX(so_date) as last_order_date'),
                DB::raw('COUNT(DISTINCT so_no) as frequency'),
                DB::raw('SUM(ar_amt) as monetary')
            )
            ->groupBy('outlets.name')
            ->get();

        $count = $outletStats->count();

        $tiers = [
            'Champion' => 0,
            'Loyal' => 0,
            'Need Attention' => 0,
            'At Risk' => 0
        ];

        if ($count > 0) {
            $rSorted = $outletStats->sortBy('last_order_date')->pluck('outlet_name')->toArray(); 
            $fSorted = $outletStats->sortBy('frequency')->pluck('outlet_name')->toArray();
            $mSorted = $outletStats->sortBy('monetary')->pluck('outlet_name')->toArray();

            $rIndex = array_flip(array_values($rSorted)); 
            $fIndex = array_flip(array_values($fSorted));
            $mIndex = array_flip(array_values($mSorted));

            $outletStats = $outletStats->map(function($item) use ($count, $rIndex, $fIndex, $mIndex, &$tiers) {
                $name = $item->outlet_name;
                $rScore = $rIndex[$name] >= ($count * 0.66) ? 3 : ($rIndex[$name] >= ($count * 0.33) ? 2 : 1);
                $fScore = $fIndex[$name] >= ($count * 0.66) ? 3 : ($fIndex[$name] >= ($count * 0.33) ? 2 : 1);
                $mScore = $mIndex[$name] >= ($count * 0.66) ? 3 : ($mIndex[$name] >= ($count * 0.33) ? 2 : 1);

                $overall = $rScore + $fScore + $mScore; 
                
                if ($overall >= 8) {
                    $segment = 'Champion';
                } elseif ($overall >= 6) {
                    $segment = 'Loyal';
                } elseif ($overall >= 4) {
                    $segment = 'Need Attention';
                } else {
                    $segment = 'At Risk';
                }
                
                $item->r_score = $rScore;
                $item->f_score = $fScore;
                $item->m_score = $mScore;
                $item->overall = $overall;
                $item->segment = $segment;
                $tiers[$segment]++;
                return $item;
            });
            
            $selectedSegment = $request->get('segment', 'all');
            if ($selectedSegment !== 'all') {
                $outletStats = $outletStats->filter(function($item) use ($selectedSegment) {
                    return $item->segment === $selectedSegment;
                });
            }

            // Sort by monetary to rank inside the group
            $outletStats = $outletStats->sortByDesc('monetary')->values();
        }

        // --- DISTORA AI NARRATIVE GENERATOR ---
        $champCount = $tiers['Champion'];
        $riskCount = $tiers['At Risk'];
        $aiNarrative = "🔍 Fakta: Ada $champCount toko 'Sultan' (Champions) yang loyal & sering belanja, sementara $riskCount toko masuk zona merah (At Risk/Sleepers).\n" .
                       "💡 Saran Eksekusi: Kasih reward eksklusif / bonus produk untuk para Champions biar kompetitor gigit jari. Sebaliknya, bentuk tim Taktis Khusus untuk mengepung balik $riskCount toko At Risk sebelum mereka lupa nama brand kita!";

        return view('analytics.rfm', compact('period', 'periods', 'outletStats', 'tiers', 'count', 'aiNarrative'));
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
                if (!isset($itemBasketCounts[$p1])) $itemBasketCounts[$p1] = 0;
                $itemBasketCounts[$p1]++;

                if (!isset($matrix[$p1])) $matrix[$p1] = [];
                
                foreach ($items as $p2) {
                    if ($p1 != $p2) {
                        if (!isset($matrix[$p1][$p2])) $matrix[$p1][$p2] = 0;
                        $matrix[$p1][$p2]++;
                    }
                }
            }
        }

        $affinities = [];
        foreach ($matrix as $source => $targets) {
            // Only care if the source product has a meaningful amount of buyers (e.g., > 2)
            if($itemBasketCounts[$source] < 3) continue;

            arsort($targets);
            $topAssociated = array_slice($targets, 0, 5, true); 
            $affinities[] = [
                'item' => $source,
                'total_baskets' => $itemBasketCounts[$source],
                'associations' => $topAssociated 
            ];
        }

        // Sort heavily demanded products first
        usort($affinities, function($a, $b) {
            return $b['total_baskets'] <=> $a['total_baskets'];
        });

        // --- DISTORA AI NARRATIVE GENERATOR ---
        $topAsso = count($affinities) > 0 ? $affinities[0] : null;
        $aiNarrative = "🔍 Fakta: Mesin Basket Analysis mendeteksi afinitas keranjang. " . ($topAsso ? "Produk {$topAsso['item']} paling sering diborong bersaman dengan item lain di {$topAsso['total_baskets']} toko berbeda." : "Belum ada pola keranjang terbentuk yang signifikan.") . "\n" .
                       "💡 Saran Eksekusi: Jadikan produk teratas ini sebagai \"Lokomotif\". Gabungkan/bundling secara paksa produk Dead-Stock (Gerbong) bersama produk Lokomotif ini untuk mempercepat penetrasi cuci gudang!";

        $affinities = array_slice($affinities, 0, 100);

        return view('analytics.cross-selling', compact('period', 'periods', 'affinities', 'aiNarrative'));
    }
    public function marginAnalysis(Request $request)
    {
        $period = $request->get('period', Transaction::max('period') ?? date('Y-m'));
        $periods = Transaction::select('period')->distinct()->orderByDesc('period')->pluck('period');

        // Global KPIs
        $kpis = Transaction::withFilters(request())
            ->invoices()
            ->selectRaw('
                SUM(ar_amt) as total_revenue,
                SUM(cogs) as total_cogs
            ')->first();

        $totalRevenue = $kpis->total_revenue ?? 0;
        $totalCogs = $kpis->total_cogs ?? 0;
        $totalGrossProfit = $totalRevenue - $totalCogs;
        $blendedMargin = $totalRevenue > 0 ? ($totalGrossProfit / $totalRevenue) * 100 : 0;

        // Principal Margins
        $principalMargins = Transaction::withFilters(request())
            ->invoices()
            ->join('products', 'transactions.product_id', '=', 'products.id')
            ->join('principals', 'products.principal_id', '=', 'principals.id')
            ->select(
                'principals.name as principal_name',
                DB::raw('SUM(transactions.ar_amt) as revenue'),
                DB::raw('SUM(transactions.cogs) as cogs')
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
            ->invoices()
            ->join('products', 'transactions.product_id', '=', 'products.id')
            ->join('principals', 'products.principal_id', '=', 'principals.id')
            ->select(
                'products.name as product_name',
                'principals.name as principal_name',
                DB::raw('SUM(transactions.ar_amt) as revenue'),
                DB::raw('SUM(transactions.cogs) as cogs')
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
        $aiNarrative = "🔍 Fakta: Blended Laba Kotor stabil di angka " . number_format($blendedMargin, 2) . "% dengan cuan bersih tunai Rp " . number_format($totalGrossProfit, 0, ',', '.') . ".\n" .
                       "💡 Saran Eksekusi: Ada produk beresiko di jajaran paling bawah (contoh: $bottomProd) yang marginnya dimakan diskon atau HPP bengkak. Kaji ulang harga dasar. Jangan sampai lelah jualan tapi hasilnya bakar duit.";

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
                DB::raw('SUM(transactions.ar_amt) as total_revenue')
            )
            ->groupBy('salesmen.id', 'salesmen.name')
            ->orderByDesc('total_revenue')
            ->get();

        // 3-Month Historical Period Calculation
        $currentCarbon = \Carbon\Carbon::createFromFormat('Y-m', $period);
        $pastPeriods = [];
        for ($i = 1; $i <= 3; $i++) {
            $pastPeriods[] = (clone $currentCarbon)->subMonths($i)->format('Y-m');
        }

        // Fetch Historical 3-Month Sales per Salesman
        $historicalSalesQuery = DB::table('transactions')
            ->whereIn('transactions.period', $pastPeriods)
            ->where('transactions.type', 'I')
            ->select('transactions.salesman_id', DB::raw('SUM(transactions.ar_amt) as hist_revenue'))
            ->groupBy('transactions.salesman_id');

        // Manually apply Principal Filter (but ignore Date filters to protect the 3-Month logic)
        if ($request->has('principal_id') && !empty($request->get('principal_id')) && $request->get('principal_id') !== 'all') {
            $historicalSalesQuery->join('products', 'transactions.product_id', '=', 'products.id')
                                 ->where('products.principal_id', $request->get('principal_id'));
        }

        $historicalSales = $historicalSalesQuery->get()->keyBy('salesman_id');
            
        $totalHistoricalSales = $historicalSales->sum('hist_revenue');
        // Prevent div by zero if there's absolutely no historical data
        if ($totalHistoricalSales <= 0) $totalHistoricalSales = 1;

        // The user inputs a TOTAL TEAM TARGET
        $teamTarget = $request->get('base_target', 10000000000); // 10 Billion as default team target

        // Simulated day of month
        $isCurrentMonth = ($period == date('Y-m'));
        $workingDays = 26; 
        $currentDay = $isCurrentMonth ? (int)date('j') : 26;
        $remainingDays = $workingDays - $currentDay; 
        if($remainingDays <= 0) $remainingDays = 1;

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
            if ($item->shortfall < 0) $item->shortfall = 0;
            
            $item->progress = $item->target > 0 ? ($item->total_revenue / $item->target) * 100 : 100;
            if ($item->progress > 100) $item->progress = 100;

            $item->required_run_rate = $item->shortfall / $remainingDays;
            
            return $item;
        });

        // Re-sort based on their progressive target
        $tracking = $tracking->sortByDesc('progress')->values();

        // --- DISTORA AI NARRATIVE GENERATOR ---
        $underperformers = $tracking->filter(fn($t) => $t->progress < 80)->count();
        $totalSalesMTD = $tracking->sum('total_revenue');
        $runRate = $isCurrentMonth && $teamTarget > $totalSalesMTD ? ($teamTarget - $totalSalesMTD) / max(1, $remainingDays) : 0;
        
        $aiNarrative = "🔍 Fakta: Sisa hari kerja aktif tinggal $remainingDays hari. Seluruh tim butuh berlari dengan pace kolektif Rp " . number_format($runRate, 0, ',', '.') . " / hari.\n" .
                       "⚠️ Peringatan: Sangat gawat! Ada $underperformers Salesman yang progressnya masih lampu merah (<80%).\n" .
                       "💡 Saran Eksekusi: Stop push tim yang sudah Achieved, alihkan fokus manajemen dan buffer barang untuk mendobrak sales tim yang masih berdarah-darah!";

        return view('analytics.target-tracker', compact('period', 'periods', 'tracking', 'teamTarget', 'remainingDays', 'currentDay', 'workingDays', 'isCurrentMonth', 'aiNarrative'));
    }

    /**
     * Batch save salesman targets for a specific period
     */
    public function saveTargets(Request $request)
    {
        $period = $request->get('period');
        $targets = $request->get('targets', []); // [salesman_id => amount]

        foreach ($targets as $salesmanId => $amount) {
            if ($amount === null || $amount < 0) continue;

            SalesmanTarget::updateOrCreate(
                ['salesman_id' => $salesmanId, 'period' => $period],
                ['target_amount' => $amount]
            );
        }

        return back()->with('success', 'Target berhasil disimpan ke database!');
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
        
        foreach($allTxns as $txn) {
            $period = $txn->period;
            if(!in_array($period, $periods)) {
                $periods[] = $period;
            }
            
            $cohortMonth = $cohorts[$txn->outlet_id]->cohort_month ?? null;
            if (!$cohortMonth) continue;
            
            if(!isset($matrix[$cohortMonth])) $matrix[$cohortMonth] = [];
            if(!isset($matrix[$cohortMonth][$period])) $matrix[$cohortMonth][$period] = 0;
            
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
            $principal = \App\Models\Principal::find($request->get('principal_id'));
            if ($principal) $principalName = $principal->name;
        }

        // --- EXCEL EXPORT BRANCH ---
        if ($request->get('export') === 'excel') {
            $filename = 'Buku_Rapor_360_' . str_replace([' ', '/'], ['_', '-'], $principalName) . '_' . $period . '.xlsx';
            return Excel::download(new BukuRaporExport($request, $period, $principalName), $filename);
        }

        // 1. Basic KPIs & Profitability
        $kpis = Transaction::withFilters(request())
            ->invoices()
            ->selectRaw('
                SUM(ar_amt) as net_sales,
                SUM(cogs) as total_cogs,
                SUM(gross) as gross_sales,
                SUM(disc_total) as total_discount
            ')->first();
            
        $netSales = $kpis->net_sales ?? 0;
        $totalCogs = $kpis->total_cogs ?? 0;
        $grossProfit = $netSales - $totalCogs;
        $totalDiscount = $kpis->total_discount ?? 0;
        $blendedMargin = $netSales > 0 ? ($grossProfit / $netSales) * 100 : 0;
        
        // 2. Product Top Movers
        $topProducts = Transaction::withFilters(request())
            ->invoices()
            ->join('products', 'transactions.product_id', '=', 'products.id')
            ->select('products.name as product_name', DB::raw('SUM(transactions.ar_amt) as revenue'))
            ->groupBy('products.name')
            ->orderByDesc('revenue')
            ->limit(10)
            ->get();
            
        // 3. Sleeping Outlets Quick Count
        $dateStart = \Carbon\Carbon::parse(strlen($period) == 7 ? $period.'-01' : clone $period); 
        if (strlen($period) == 7) {
             $dateStart = \Carbon\Carbon::parse($period.'-01');
             $prevStartPeriod = $dateStart->copy()->subMonth()->format('Y-m');
        } else {
             $prevStartPeriod = \Carbon\Carbon::now()->subMonth()->format('Y-m'); 
        }
        
        $prevRequest = new \Illuminate\Http\Request();
        $prevRequest->merge([
            'start_period' => $prevStartPeriod,
            'end_period' => $prevStartPeriod,
            'principal_id' => $request->get('principal_id')
        ]);
        
        $prevOutlets = Transaction::withFilters($prevRequest)
            ->invoices()
            ->select('outlet_id', DB::raw('SUM(ar_amt) as prev_sales'))
            ->groupBy('outlet_id')
            ->having('prev_sales', '>', 0)
            ->get()
            ->keyBy('outlet_id');
            
        $currentOutlets = Transaction::withFilters(request())
            ->invoices()
            ->select('outlet_id')
            ->groupBy('outlet_id')
            ->having(DB::raw('SUM(ar_amt)'), '>', 0)
            ->pluck('outlet_id')
            ->toArray();
            
        $churnedOutletIds = $prevOutlets->keys()->diff($currentOutlets);
        $sleepingOutletsCount = $churnedOutletIds->count();
        $sleepingOutletsLoss = 0;
        foreach($churnedOutletIds as $id) {
            $sleepingOutletsLoss += $prevOutlets[$id]->prev_sales;
        }

        return view('analytics.report', compact(
            'period', 'periods', 'principalName', 'netSales', 'totalCogs', 'grossProfit', 
            'totalDiscount', 'blendedMargin', 'topProducts', 
            'sleepingOutletsCount', 'sleepingOutletsLoss'
        ));
    }
}
