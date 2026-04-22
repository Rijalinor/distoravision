<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\SalesmanTarget;
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
            ->groupBy('salesmen.id', 'salesmen.name')
            ->having('total_sales', '>', 0)
            ->orderByDesc('total_sales')
            ->get();

        // ==== Fetch AR (Piutang) ====
        $latestImport = \App\Models\ArImportLog::where('status', 'completed')->orderByDesc('report_date')->first();
        $arBalances = collect();
        if ($latestImport) {
            $arBalances = \App\Models\ArReceivable::where('ar_import_log_id', $latestImport->id)
                ->where('ar_balance', '>', 0)
                ->whereNotNull('salesman_name')
                ->groupBy('salesman_name')
                ->selectRaw('salesman_name, SUM(ar_balance) as total_ar')
                ->pluck('total_ar', 'salesman_name');
        }

        // Attach individual targets and AR to the leaderboard if available
        $salesmanTargets = SalesmanTarget::where('period', $period)->get()->keyBy('salesman_id');
        $leaderboard = $leaderboard->map(function ($s) use ($salesmanTargets, $teamTarget, $leaderboard, $arBalances) {
            $sumAll = max($leaderboard->sum('total_sales'), 1);
            $ratio = $s->total_sales / $sumAll;
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

        $monthName = \Carbon\Carbon::createFromFormat('Y-m', $period)->locale('id')->translatedFormat('F Y');

        return view('tv.dashboard', compact(
            'period', 'monthName', 'netSales', 'teamTarget', 'achievementPct', 'gap',
            'leaderboard', 'topProducts', 'topOutlets'
        ));
    }
}
