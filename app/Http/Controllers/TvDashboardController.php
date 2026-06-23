<?php

namespace App\Http\Controllers;

use App\Models\ArImportLog;
use App\Models\ArReceivable;
use App\Models\SalesmanTarget;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TvDashboardController extends Controller
{
    public function index(Request $request)
    {
        $period = Transaction::max('period') ?? date('Y-m');

        // ==== 1. GLOBAL KPIs ====
        $kpis = Transaction::where('period', $period)
            ->selectRaw('
                SUM(CASE WHEN type = "I" THEN taxed_amt WHEN type = "R" THEN -ABS(taxed_amt) ELSE 0 END) as net_sales,
                COUNT(DISTINCT CASE WHEN type = "I" THEN outlet_id END) as active_outlets,
                COUNT(DISTINCT CASE WHEN type = "I" THEN so_no END) as invoice_count
            ')->first();

        $netSales = $kpis->net_sales ?? 0;

        // Calculate Team Target
        $savedTargetValue = SalesmanTarget::where('period', $period)->sum('target_amount');
        $teamTarget = $savedTargetValue > 0 ? $savedTargetValue : 10000000000; // 10B fallback

        $achievementPct = $teamTarget > 0 ? ($netSales / $teamTarget) * 100 : 0;
        $gap = $teamTarget - $netSales;

        // ==== 2. SALESMAN LEADERBOARD ====
        $leaderboard = Transaction::where('period', $period)
            ->invoices()
            ->join('salesmen', 'transactions.salesman_id', '=', 'salesmen.id')
            ->select(
                'salesmen.id',
                'salesmen.name',
                DB::raw('SUM(transactions.taxed_amt) as total_sales')
            )
            ->groupBy('salesmen.id', 'salesmen.name')
            ->having('total_sales', '>', 0)
            ->orderByDesc('total_sales')
            ->get();

        // ==== Historical 3-Month Sales for Target Distribution ====
        // Uses past performance instead of current-month sales to avoid circular reference
        $currentCarbon = Carbon::createFromFormat('Y-m', $period);
        $pastPeriods = [];
        for ($i = 1; $i <= 3; $i++) {
            $pastPeriods[] = (clone $currentCarbon)->subMonths($i)->format('Y-m');
        }

        $historicalSales = DB::table('transactions')
            ->whereIn('period', $pastPeriods)
            ->where('type', 'I')
            ->select('salesman_id', DB::raw('SUM(taxed_amt) as hist_revenue'))
            ->groupBy('salesman_id')
            ->get()
            ->keyBy('salesman_id');

        $totalHistoricalSales = max($historicalSales->sum('hist_revenue'), 1);

        // ==== Fetch AR (Piutang) ====
        $latestImport = ArImportLog::where('status', 'completed')->orderByDesc('report_date')->first();
        $arBalances = collect();
        $arSummary = null;
        $topArOutlets = collect();

        if ($latestImport) {
            $arBalances = ArReceivable::where('ar_import_log_id', $latestImport->id)
                ->where('ar_balance', '>', 0)
                ->whereNotNull('salesman_name')
                ->groupBy('salesman_name')
                ->selectRaw('salesman_name, SUM(ar_balance) as total_ar')
                ->pluck('total_ar', 'salesman_name');

            // Slide 3: AR Summary Statistics
            $arSummary = ArReceivable::where('ar_import_log_id', $latestImport->id)
                ->selectRaw('
                    SUM(ar_balance) as total_balance,
                    SUM(CASE WHEN overdue_days > 0 THEN ar_balance ELSE 0 END) as total_overdue,
                    SUM(CASE WHEN overdue_days > 30 THEN ar_balance ELSE 0 END) as overdue_30,
                    SUM(CASE WHEN overdue_days > 90 THEN ar_balance ELSE 0 END) as overdue_90
                ')->first();

            // Slide 3: Top Outlets by Outstanding AR
            $topArOutlets = ArReceivable::where('ar_import_log_id', $latestImport->id)
                ->where('ar_balance', '>', 0)
                ->select('outlet_name', 'ar_balance', 'overdue_days')
                ->orderByDesc('ar_balance')
                ->limit(5)
                ->get();
        }

        // Attach individual targets and AR to the leaderboard
        $salesmanTargets = SalesmanTarget::where('period', $period)->get()->keyBy('salesman_id');
        $leaderboard = $leaderboard->map(function ($s) use ($salesmanTargets, $teamTarget, $historicalSales, $totalHistoricalSales, $arBalances) {
            // Use historical contribution ratio (not current-month) to avoid circular reference
            $histSales = $historicalSales->get($s->id)->hist_revenue ?? 0;
            $ratio = $histSales / $totalHistoricalSales;

            $s->target = $salesmanTargets->has($s->id) ? $salesmanTargets->get($s->id)->target_amount : ($ratio * $teamTarget);
            $s->progress = $s->target > 0 ? ($s->total_sales / $s->target) * 100 : 100;

            // Map AR
            $s->ar_balance = $arBalances->get($s->name, 0);

            return $s;
        });

        // Re-sort the leaderboard by absolute sales
        $leaderboard = $leaderboard->sortByDesc('total_sales')->values();

        // ==== 3. TOP PRODUCTS ====
        $topProducts = Transaction::where('period', $period)
            ->invoices()
            ->join('products', 'transactions.product_id', '=', 'products.id')
            ->select('products.name', DB::raw('SUM(transactions.taxed_amt) as total_sales'))
            ->groupBy('products.name')
            ->orderByDesc('total_sales')
            ->limit(5)
            ->get();

        // ==== 4. TOP OUTLETS ====
        $topOutlets = Transaction::where('period', $period)
            ->invoices()
            ->join('outlets', 'transactions.outlet_id', '=', 'outlets.id')
            ->select('outlets.name', 'outlets.city', DB::raw('SUM(transactions.taxed_amt) as total_sales'))
            ->groupBy('outlets.name', 'outlets.city')
            ->orderByDesc('total_sales')
            ->limit(5)
            ->get();

        $monthName = Carbon::createFromFormat('Y-m', $period)->locale('id')->translatedFormat('F Y');

        return view('tv.dashboard', compact(
            'period', 'monthName', 'netSales', 'teamTarget', 'achievementPct', 'gap',
            'leaderboard', 'topProducts', 'topOutlets', 'arSummary', 'topArOutlets'
        ));
    }
}
