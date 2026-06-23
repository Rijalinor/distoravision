<?php

namespace App\Http\Controllers;

use App\Exports\BukuRaporExport;
use App\Models\Principal;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware(function ($request, $next) {
                abort_if(auth()->check() && auth()->user()->isSalesman(), 403, 'Akses ditolak. Salesman tidak dapat melihat menu Analisis ini.');

                return $next($request);
            }),
        ];
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
}
