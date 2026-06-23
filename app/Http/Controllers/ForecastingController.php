<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\CsvExportable;
use App\Models\Product;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class ForecastingController extends Controller implements HasMiddleware
{
    use CsvExportable;

    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware(function ($request, $next) {
                abort_if(auth()->check() && auth()->user()->isSalesman(), 403, 'Akses ditolak. Salesman tidak dapat melihat Prediksi Inventory.');

                return $next($request);
            }),
        ];
    }

    public function index(Request $request)
    {
        $periods = cache()->remember('transaction_periods', 3600, function () {
            return Transaction::select('period')->distinct()->orderByDesc('period')->pluck('period');
        });

        if ($periods->isEmpty()) {
            return view('inventory.forecast', ['hasData' => false, 'periods' => collect()]);
        }

        $hasData = true;
        $period = $request->get('period', $periods->first());
        $selectedPrincipal = $request->get('principal');

        $targetPeriodDate = Carbon::parse($period.'-01')->addMonth();
        $targetPeriodLabel = $targetPeriodDate->translatedFormat('F Y');
        $targetPeriodYm = $targetPeriodDate->format('Y-m');

        // Get 13 months of history: T-1 to T-13
        $historyMonths = [];
        for ($i = 0; $i <= 13; $i++) {
            $historyMonths[] = Carbon::parse($period.'-01')->subMonths($i)->format('Y-m');
        }

        // Fetch Historical Sales Data (Quantity & Order Freq)
        $salesQuery = Transaction::whereIn('period', $historyMonths);

        $salesData = $salesQuery->select(
            'product_id',
            'period',
            DB::raw("SUM(CASE WHEN type='I' THEN qty_base WHEN type='R' THEN -qty_base ELSE 0 END) as total_qty"),
            DB::raw('COUNT(DISTINCT outlet_id) as active_outlets'),
            DB::raw('COUNT(DISTINCT so_date) as days_sold')
        )
            ->groupBy('product_id', 'period')
            ->get()
            ->groupBy('product_id');

        $productIds = $salesData->keys()->toArray();
        if (empty($productIds)) {
            return view('inventory.forecast', [
                'hasData' => true, 'periods' => $periods, 'period' => $period, 'selectedPrincipal' => $selectedPrincipal,
                'forecasts' => collect(), 'targetPeriodLabel' => $targetPeriodLabel, 'targetPeriodYm' => $targetPeriodYm,
                'totalItemsAnalyzed' => 0, 'totalQtyForecast' => 0,
            ]);
        }

        $productsQuery = Product::whereIn('id', $productIds)->with('principal');
        if ($selectedPrincipal && $selectedPrincipal !== 'all') {
            $productsQuery->whereHas('principal', function ($q) use ($selectedPrincipal) {
                $q->where('name', $selectedPrincipal);
            });
        }
        $products = $productsQuery->get()->keyBy('id');

        $forecasts = [];

        foreach ($products as $productId => $product) {
            $history = $salesData->get($productId, collect())->keyBy('period');

            $getQty = fn ($p) => max(0, $history->get($p)->total_qty ?? 0);
            $getDays = fn ($p) => $history->get($p)->active_outlets ?? 0;
            $getDaysSold = fn ($p) => $history->get($p)->days_sold ?? 0;

            $t1 = $historyMonths[0]; // Selected period
            $t2 = $historyMonths[1];
            $t3 = $historyMonths[2];
            $t4 = $historyMonths[3];

            $qtyT1 = $getQty($t1);
            $qtyT2 = $getQty($t2);
            $qtyT3 = $getQty($t3);
            $qtyT4 = $getQty($t4);

            $outletsT1 = $getDays($t1);
            $avgOutlets = ($getDays($t2) + $getDays($t3) + $getDays($t4)) / 3;
            $avgQty = ($qtyT2 + $qtyT3 + $qtyT4) / 3;

            $daysSoldT1 = $getDaysSold($t1);
            $avgDaysSold = ($getDaysSold($t2) + $getDaysSold($t3) + $getDaysSold($t4)) / 3;

            $flags = [];
            $usedT1 = $qtyT1;

            // Extract conversion factor from name, e.g. "(1x12x10)" -> 120
            $conversion = 1;
            if (preg_match('/\(([\d\sX]+)\)/i', $product->name, $matches)) {
                $parts = explode('X', strtoupper(str_replace(' ', '', $matches[1])));
                $calc = 1;
                foreach ($parts as $p) {
                    if (is_numeric($p) && $p > 0) {
                        $calc *= (int) $p;
                    }
                }
                if ($calc > 1) {
                    $conversion = $calc;
                }
            }

            // 1. Advanced Stockout Imputation (Time-Gap Anomaly)
            if ($avgDaysSold > 10 && $daysSoldT1 > 0 && $daysSoldT1 < ($avgDaysSold * 0.6)) {
                $runRate = $qtyT1 / $daysSoldT1;
                $imputedQtyT1 = $runRate * $avgDaysSold;
                $usedT1 = $imputedQtyT1;

                $recoveredCtn = round(($imputedQtyT1 - $qtyT1) / $conversion);
                $flags[] = "oos_imputed (+{$recoveredCtn} CTN recovered)";
            }
            // 2. Existing Stockout Fallback (No sales at all / Huge Drop)
            elseif ($outletsT1 < ($avgOutlets * 0.4) && $qtyT1 < ($avgQty * 0.5) && $avgOutlets > 0) {
                $usedT1 = $qtyT2; // Fallback to T-2
                $flags[] = 'stockout_drop';
            }
            // 3. Outlier Trimming (Forward Buying / Promo Spike)
            elseif ($qtyT1 > ($avgQty * 1.5) && $avgQty > 10) {
                $usedT1 = $avgQty * 1.5;
                $flags[] = 'promo_spike';
            }

            // Calculate Base WMA (3-Month: 50%, 30%, 20%)
            $wma = ($usedT1 * 0.5) + ($qtyT2 * 0.3) + ($qtyT3 * 0.2);

            // 3. Seasonality Injection (YoY)
            $targetMonthLastYear = Carbon::parse($targetPeriodYm)->subYear()->format('Y-m');
            $qtyLastYear = $getQty($targetMonthLastYear);

            // Calculate average of last year (T-1 to T-12)
            $lastYearTotal = 0;
            $lastYearMonths = 0;
            for ($i = 1; $i <= 12; $i++) {
                $m = $historyMonths[$i];
                $q = $getQty($m);
                if ($q > 0) {
                    $lastYearTotal += $q;
                    $lastYearMonths++;
                }
            }
            $lastYearAvg = $lastYearMonths > 0 ? ($lastYearTotal / $lastYearMonths) : 0;

            $seasonalIndex = 1.0;
            if ($lastYearAvg > 0 && $qtyLastYear > 0) {
                $seasonalIndex = $qtyLastYear / $lastYearAvg;
                $seasonalIndex = max(0.5, min(2.0, $seasonalIndex));
                if ($seasonalIndex > 1.2) {
                    $flags[] = 'seasonal_up';
                } elseif ($seasonalIndex < 0.8) {
                    $flags[] = 'seasonal_down';
                }
            }

            // Final Forecast
            $finalForecast = ceil($wma * $seasonalIndex);

            // Skip if forecast is zero and no recent sales
            if ($finalForecast == 0 && $qtyT1 == 0 && $qtyT2 == 0) {
                continue;
            }

            // (Conversion extraction moved up for early flag calculation)

            $forecasts[] = (object) [
                'item_no' => $product->item_no,
                'item_name' => $product->name,
                'principal' => $product->principal->name ?? '-',
                'avg_qty' => round($avgQty / $conversion),
                't1_actual' => ceil($qtyT1 / $conversion),
                't1_outlets' => $outletsT1,
                'avg_outlets' => round($avgOutlets),
                'wma_base' => round($wma / $conversion),
                'seasonal_index' => round($seasonalIndex, 2),
                'forecast_qty' => ceil($finalForecast / $conversion),
                'flags' => $flags,
            ];
        }

        // Sort by Forecast Qty descending
        usort($forecasts, fn ($a, $b) => $b->forecast_qty <=> $a->forecast_qty);

        $forecasts = collect($forecasts);

        // Global KPIs
        $totalItemsAnalyzed = $forecasts->count();
        $totalQtyForecast = $forecasts->sum('forecast_qty');

        // --- CSV EXPORT ---
        if ($request->get('export') === 'csv') {
            $rows = $forecasts->map(fn ($f) => [
                $f->item_name,
                $f->principal,
                $f->t1_actual,
                $f->t1_outlets,
                round($f->wma_base, 2),
                $f->forecast_qty,
                empty($f->flags) ? '' : implode(', ', $f->flags),
            ])->toArray();

            return $this->streamCsv(
                "PureDemandForecast_{$targetPeriodYm}_{$selectedPrincipal}.csv",
                ['Produk', 'Principal', 'Penjualan T-1 Aktual', 'Toko Aktif T-1 (Freq)', 'Baseline WMA', 'Prediksi Qty', 'Anomali Flag'],
                $rows
            );
        }

        return view('inventory.forecast', compact(
            'hasData', 'periods', 'period', 'selectedPrincipal', 'targetPeriodLabel', 'targetPeriodYm',
            'forecasts', 'totalItemsAnalyzed', 'totalQtyForecast'
        ));
    }

    public function multiPeriodForecast(Request $request)
    {
        $periods = cache()->remember('transaction_periods', 3600, function () {
            return Transaction::select('period')->distinct()->orderByDesc('period')->pluck('period');
        });

        if ($periods->isEmpty()) {
            return view('inventory.multi-period-forecast', ['hasData' => false, 'periods' => collect()]);
        }

        $hasData = true;
        $period = $request->get('period', $periods->first());
        $selectedPrincipal = $request->get('principal');

        // Target periods T+1 to T+6
        $targetPeriods = [];
        $baseDate = Carbon::parse($period.'-01');
        for ($i = 1; $i <= 6; $i++) {
            $targetPeriods[$i] = [
                'ym' => $baseDate->copy()->addMonths($i)->format('Y-m'),
                'label' => $baseDate->copy()->addMonths($i)->translatedFormat('M Y'),
                'last_year_ym' => $baseDate->copy()->addMonths($i)->subYear()->format('Y-m'),
            ];
        }

        // Get 13 months of history: T-1 to T-13
        $historyMonths = [];
        for ($i = 0; $i <= 13; $i++) {
            $historyMonths[] = Carbon::parse($period.'-01')->subMonths($i)->format('Y-m');
        }

        // Fetch Historical Sales Data (Quantity & Order Freq)
        $salesQuery = Transaction::whereIn('period', $historyMonths);

        $salesData = $salesQuery->select(
            'product_id',
            'period',
            DB::raw("SUM(CASE WHEN type='I' THEN qty_base WHEN type='R' THEN -qty_base ELSE 0 END) as total_qty"),
            DB::raw('COUNT(DISTINCT outlet_id) as active_outlets'),
            DB::raw('COUNT(DISTINCT so_date) as days_sold')
        )
            ->groupBy('product_id', 'period')
            ->get()
            ->groupBy('product_id');

        $productIds = $salesData->keys()->toArray();
        if (empty($productIds)) {
            return view('inventory.multi-period-forecast', [
                'hasData' => true, 'periods' => $periods, 'period' => $period, 'selectedPrincipal' => $selectedPrincipal,
                'forecasts' => collect(), 'targetPeriods' => $targetPeriods,
                'totalAnalyzed' => 0, 'aiNarrative' => 'Belum ada riwayat transaksi.',
            ]);
        }

        $productsQuery = Product::whereIn('id', $productIds)->with('principal');
        if ($selectedPrincipal && $selectedPrincipal !== 'all') {
            $productsQuery->whereHas('principal', function ($q) use ($selectedPrincipal) {
                $q->where('name', $selectedPrincipal);
            });
        }
        $products = $productsQuery->get()->keyBy('id');

        $forecasts = [];

        foreach ($products as $productId => $product) {
            $history = $salesData->get($productId, collect())->keyBy('period');

            $getQty = fn ($p) => max(0, $history->get($p)->total_qty ?? 0);
            $getDays = fn ($p) => $history->get($p)->active_outlets ?? 0;
            $getDaysSold = fn ($p) => $history->get($p)->days_sold ?? 0;

            $t1 = $historyMonths[0]; // Current period
            $t2 = $historyMonths[1];
            $t3 = $historyMonths[2];

            $qtyT1 = $getQty($t1);
            $qtyT2 = $getQty($t2);
            $qtyT3 = $getQty($t3);

            $outletsT1 = $getDays($t1);

            $daysSoldT1 = $getDaysSold($t1);
            $t4 = $historyMonths[3] ?? null; // For multi-period we only extracted t1..t3 variables initially, grab t4 from array
            $avgDaysSold = ($getDaysSold($t2) + $getDaysSold($t3) + ($t4 ? $getDaysSold($t4) : 0)) / 3;

            $usedT1 = $qtyT1;
            $oosRecovered = false;

            // Advanced OOS Imputation (Time-Gap Anomaly)
            if ($avgDaysSold > 10 && $daysSoldT1 > 0 && $daysSoldT1 < ($avgDaysSold * 0.6)) {
                $runRate = $qtyT1 / $daysSoldT1;
                $usedT1 = $runRate * $avgDaysSold;
                $oosRecovered = true;
            }

            // WMA Base Trend (3-Month Weighted Moving Average)
            $wma = ($usedT1 * 0.5) + ($qtyT2 * 0.3) + ($qtyT3 * 0.2);

            // Calculate Recent Growth Trend (Momentum / Slope) over the last 6 months
            $trendValues = [];
            for ($i = 5; $i >= 0; $i--) {
                $trendValues[] = $getQty($historyMonths[$i]);
            }
            $n = 6;
            $sumX = 0;
            $sumY = 0;
            $sumXY = 0;
            $sumX2 = 0;
            for ($i = 0; $i < $n; $i++) {
                $sumX += $i;
                $sumY += $trendValues[$i];
                $sumXY += $i * $trendValues[$i];
                $sumX2 += $i * $i;
            }
            $denominator = ($n * $sumX2) - ($sumX * $sumX);
            $slope = $denominator > 0 ? (($n * $sumXY) - ($sumX * $sumY)) / $denominator : 0;
            $avgTrendQty = $sumY / max($n, 1);

            // Limit the monthly growth slope to a safe range (max +/- 10% per month)
            $monthlyGrowthRate = $avgTrendQty > 0 ? ($slope / $avgTrendQty) : 0;
            $monthlyGrowthRate = max(-0.10, min(0.10, $monthlyGrowthRate));

            // Calculate average of last year (T-1 to T-12) to find seasonal index
            $lastYearTotal = 0;
            $lastYearMonths = 0;
            for ($i = 1; $i <= 12; $i++) {
                $q = $getQty($historyMonths[$i]);
                if ($q > 0) {
                    $lastYearTotal += $q;
                    $lastYearMonths++;
                }
            }
            $lastYearAvg = $lastYearMonths > 0 ? ($lastYearTotal / $lastYearMonths) : 0;

            $multiForecast = [];
            $total6Month = 0;

            // Base WMA is our starting point (Month 0)
            $currentBase = $wma;

            for ($i = 1; $i <= 6; $i++) {
                $qtyLastYear = $getQty($targetPeriods[$i]['last_year_ym']);
                $seasonalIndex = 1.0;

                if ($lastYearAvg > 0 && $qtyLastYear > 0) {
                    $seasonalIndex = $qtyLastYear / $lastYearAvg;
                    $seasonalIndex = max(0.5, min(2.0, $seasonalIndex));
                }

                // Apply trend incrementally (compound growth)
                $currentBase = $currentBase * (1 + $monthlyGrowthRate);

                $projectedQty = (int) ceil($currentBase * $seasonalIndex);
                $projectedQty = max(0, $projectedQty); // Ensure no negative projection

                $multiForecast[$i] = $projectedQty;
                $total6Month += $projectedQty;
            }

            // Skip if no projected sales
            if ($total6Month == 0 && $qtyT1 == 0 && $qtyT2 == 0) {
                continue;
            }

            $status = 'Normal';
            $icon = '✅';

            // Highlight based on trend and OOS
            if ($oosRecovered) {
                $status = 'OOS Recovered';
                $icon = '🔄';
            } elseif ($wma > 0 && $multiForecast[6] > $wma * 1.5) {
                $status = 'Trending Up';
                $icon = '📈';
            } elseif ($wma > 0 && $multiForecast[6] < $wma * 0.5) {
                $status = 'Trending Down';
                $icon = '📉';
            }

            // Extract conversion factor from name, e.g. "(1x12x10)" -> 120
            $conversion = 1;
            if (preg_match('/\(([\d\sX]+)\)/i', $product->name, $matches)) {
                $parts = explode('X', strtoupper(str_replace(' ', '', $matches[1])));
                $calc = 1;
                foreach ($parts as $p) {
                    if (is_numeric($p) && $p > 0) {
                        $calc *= (int) $p;
                    }
                }
                if ($calc > 1) {
                    $conversion = $calc;
                }
            }

            $total6MonthCtn = 0;
            foreach ($multiForecast as $k => $v) {
                $multiForecast[$k] = ceil($v / $conversion);
                $total6MonthCtn += $multiForecast[$k];
            }

            $forecasts[] = (object) [
                'item_no' => $product->item_no,
                'item_name' => $product->name,
                'principal' => $product->principal->name ?? '-',
                'wma_base' => round($wma / $conversion),
                't1_outlets' => $outletsT1,
                'multi_forecast' => $multiForecast,
                'total_6_month' => $total6MonthCtn,
                'status' => $status,
                'icon' => $icon,
            ];
        }

        $forecasts = collect($forecasts);

        // Sort by total_6_month descending to show Top Sellers next month
        $forecasts = $forecasts->sortByDesc('total_6_month')->values();

        $totalAnalyzed = $forecasts->count();
        $topSeller = $forecasts->first();
        $trendingUpCount = $forecasts->where('status', 'Trending Up')->count();

        // --- DISTORA AI NARRATIVE GENERATOR ---
        $aiNarrative = "🔍 Fakta: Mesin Pure Demand 6-Month Predictor telah mensimulasikan masa depan untuk $totalAnalyzed produk aktif.\n".
                       ($topSeller ? "🏆 Puncak Permintaan: '{$topSeller->item_name}' diprediksi akan laku terbanyak dengan total proyeksi {$topSeller->total_6_month} karton dalam 6 bulan ke depan. Pastikan pabrik siap suplai!\n" : '').
                       ($trendingUpCount > 0 ? "📈 Peluang: Ada $trendingUpCount barang yang akan mengalami lonjakan pesat (Trending Up) di akhir semester ini berdasarkan pola musiman historis." : '');

        // --- CSV EXPORT ---
        if ($request->get('export') === 'csv') {
            $headers = ['Produk', 'Principal', 'Active Stores', 'Base Trend (WMA)', 'Total 6 Bln'];
            foreach ($targetPeriods as $i => $tp) {
                $headers[] = $tp['label'];
            }
            $headers[] = 'Status';

            $rows = $forecasts->map(function ($f) {
                $row = [
                    $f->item_name,
                    $f->principal,
                    $f->t1_outlets,
                    $f->wma_base,
                    $f->total_6_month,
                ];
                for ($i = 1; $i <= 6; $i++) {
                    $row[] = $f->multi_forecast[$i];
                }
                $row[] = $f->status;

                return $row;
            })->toArray();

            return $this->streamCsv(
                "MultiPeriodSalesForecast_{$period}_{$selectedPrincipal}.csv",
                $headers,
                $rows
            );
        }

        return view('inventory.multi-period-forecast', compact(
            'hasData', 'periods', 'period', 'selectedPrincipal', 'targetPeriods',
            'forecasts', 'totalAnalyzed', 'trendingUpCount', 'aiNarrative'
        ));
    }
}
