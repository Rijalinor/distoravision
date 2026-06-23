<?php

namespace App\Http\Controllers;

use App\Models\SalesPerTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class SalesPerAnalyticsController extends Controller implements HasMiddleware
{
    /**
     * Block salesman role — consistent with split analytics controllers pattern.
     */
    public static function middleware(): array
    {
        return [
            new Middleware(function ($request, $next) {
                abort_if(auth()->check() && auth()->user()->isSalesman(), 403, 'Akses ditolak. Salesman tidak dapat melihat halaman ini.');

                return $next($request);
            }),
        ];
    }

    public function dashboard(Request $request)
    {
        $periods = SalesPerTransaction::select('period')->distinct()->orderByDesc('period')->pluck('period');

        if ($periods->isEmpty()) {
            return view('sales-per.dashboard', ['hasData' => false, 'periods' => collect()]);
        }

        $period = $request->get('period', $periods->first());
        $selectedSalesCode = $request->get('salesman');
        $selectedPrincipal = $request->get('principal');
        $periodLabel = Carbon::parse($period.'-01')->translatedFormat('F Y');

        // Base query for this period
        $baseQuery = SalesPerTransaction::where('period', $period);
        if ($selectedPrincipal && $selectedPrincipal !== 'all') {
            $baseQuery->where('principal_name', $selectedPrincipal);
        }

        // ══════════════════════════════════════════════════════
        // 1. OVERALL KPIs
        // ══════════════════════════════════════════════════════
        $overallSales = (clone $baseQuery)->invoices()->sum('subtotal');
        $overallReturns = (clone $baseQuery)->returns()->sum('subtotal');
        $overallNetSales = $overallSales - $overallReturns;
        $overallNotaCount = (clone $baseQuery)->invoices()->distinct('so_no')->count('so_no');
        $overallOutletCount = (clone $baseQuery)->invoices()->distinct('outlet_code')->count('outlet_code');
        $activeSalesmenCount = (clone $baseQuery)->invoices()->distinct('sales_code')->count('sales_code');
        $overallReturnRate = $overallSales > 0 ? ($overallReturns / $overallSales) * 100 : 0;

        // ══════════════════════════════════════════════════════
        // 2. LEADERBOARD — Ranking Salesman by Omset
        // ══════════════════════════════════════════════════════
        $leaderboard = SalesPerTransaction::where('period', $period)
            ->when($selectedPrincipal && $selectedPrincipal !== 'all', fn ($q) => $q->where('principal_name', $selectedPrincipal))
            ->select(
                'sales_code',
                DB::raw('MAX(sales_name) as sales_name'),
                DB::raw("SUM(CASE WHEN type='I' THEN subtotal ELSE 0 END) as total_sales"),
                DB::raw("SUM(CASE WHEN type='R' THEN ABS(subtotal) ELSE 0 END) as total_returns"),
                DB::raw("COUNT(DISTINCT CASE WHEN type='I' THEN so_no END) as nota_count"),
                DB::raw("COUNT(DISTINCT CASE WHEN type='I' THEN outlet_code END) as outlet_count")
            )
            ->groupBy('sales_code')
            ->orderBy('sales_name')
            ->get();

        $leaderboard->transform(function ($s) {
            $s->net_sales = $s->total_sales - $s->total_returns;
            $s->return_rate = $s->total_sales > 0 ? ($s->total_returns / $s->total_sales) * 100 : 0;

            return $s;
        });

        // ══════════════════════════════════════════════════════
        // 3. DAILY TREND
        // ══════════════════════════════════════════════════════
        $dailyTrendQuery = (clone $baseQuery);
        if ($selectedSalesCode) {
            $dailyTrendQuery->where('sales_code', $selectedSalesCode);
        }

        $dailyTrend = (clone $dailyTrendQuery)
            ->select(
                DB::raw('DATE(so_date) as sale_date'),
                'type',
                DB::raw('SUM(ABS(subtotal)) as total')
            )
            ->whereNotNull('so_date')
            ->groupBy('sale_date', 'type')
            ->orderBy('sale_date')
            ->get()
            ->groupBy('sale_date');

        // ══════════════════════════════════════════════════════
        // 4. SALESMAN DETAIL (drill-down)
        // ══════════════════════════════════════════════════════
        $salesmanDetail = null;
        $selectedSalesName = null;

        if ($selectedSalesCode) {
            $sq = SalesPerTransaction::where('period', $period)->where('sales_code', $selectedSalesCode);
            if ($selectedPrincipal && $selectedPrincipal !== 'all') {
                $sq->where('principal_name', $selectedPrincipal);
            }

            $selectedSalesName = (clone $sq)->value('sales_name');

            $sSales = (clone $sq)->invoices()->sum('subtotal');
            $sReturns = (clone $sq)->returns()->sum('subtotal');

            $topProducts = (clone $sq)->invoices()
                ->select('item_name', DB::raw('SUM(subtotal) as total_sales'), DB::raw('SUM(qty) as total_qty'))
                ->groupBy('item_name')->orderByDesc('total_sales')->limit(10)->get();

            $topOutlets = (clone $sq)->invoices()
                ->select('outlet_name', 'outlet_code', DB::raw('SUM(subtotal) as total_sales'), DB::raw('COUNT(DISTINCT so_no) as nota_count'))
                ->groupBy('outlet_name', 'outlet_code')->orderByDesc('total_sales')->limit(10)->get();

            $rank = $leaderboard->search(fn ($s) => $s->sales_code === $selectedSalesCode);
            $rank = $rank !== false ? $rank + 1 : '-';

            $salesmanDetail = [
                'sales' => $sSales,
                'returns' => $sReturns,
                'net_sales' => $sSales - $sReturns,
                'nota_count' => (clone $sq)->invoices()->distinct('so_no')->count('so_no'),
                'outlet_count' => (clone $sq)->invoices()->distinct('outlet_code')->count('outlet_code'),
                'return_rate' => $sSales > 0 ? ($sReturns / $sSales) * 100 : 0,
                'contribution' => $overallSales > 0 ? ($sSales / $overallSales) * 100 : 0,
                'top_products' => $topProducts,
                'top_outlets' => $topOutlets,
                'rank' => $rank,
            ];
        }

        // Dropdown lists
        $salesmenList = SalesPerTransaction::where('period', $period)->invoices()
            ->select('sales_code', DB::raw('MAX(sales_name) as sales_name'))
            ->groupBy('sales_code')->orderBy('sales_name')->get();

        $principalList = SalesPerTransaction::where('period', $period)
            ->select('principal_name')->distinct()->orderBy('principal_name')->pluck('principal_name');

        return view('sales-per.dashboard', compact(
            'periods', 'period', 'periodLabel',
            'overallSales', 'overallReturns', 'overallNetSales',
            'overallNotaCount', 'overallOutletCount', 'activeSalesmenCount', 'overallReturnRate',
            'leaderboard', 'dailyTrend',
            'selectedSalesCode', 'selectedSalesName', 'salesmanDetail',
            'salesmenList', 'principalList', 'selectedPrincipal'
        ) + ['hasData' => true]);
    }
}
