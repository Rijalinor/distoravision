<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Salesman;
use App\Models\ArImportLog;
use App\Models\ArReceivable;
use App\Models\SalesmanTarget;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SalesmanDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        // Harus role salesman dan punya salesman_id
        if (!$user->isSalesman() || !$user->salesman_id) {
            abort(403, 'Dashboard ini hanya untuk akun Salesman.');
        }

        $salesman = Salesman::findOrFail($user->salesman_id);
        $period = $request->get('period', Transaction::max('period') ?? date('Y-m'));
        $periods = Transaction::select('period')->distinct()->orderByDesc('period')->pluck('period');
        $prevM = Carbon::parse($period . '-01')->subMonth()->format('Y-m');

        // ══════════════════════════════════════════════════════
        // 1. SALES KPIs
        // ══════════════════════════════════════════════════════
        $totalSales = Transaction::where('salesman_id', $salesman->id)
            ->where('period', $period)->invoices()->sum('taxed_amt');
        $totalReturns = Transaction::where('salesman_id', $salesman->id)
            ->where('period', $period)->returns()->sum(DB::raw('ABS(taxed_amt)'));
        $netSales = $totalSales - $totalReturns;
        $returnRate = $totalSales > 0 ? ($totalReturns / $totalSales) * 100 : 0;
        $invoiceCount = Transaction::where('salesman_id', $salesman->id)
            ->where('period', $period)->invoices()->count();
        $outletCount = Transaction::where('salesman_id', $salesman->id)
            ->where('period', $period)->invoices()->distinct('outlet_id')->count('outlet_id');

        // Previous period for MoM
        $prevSales = Transaction::where('salesman_id', $salesman->id)
            ->where('period', $prevM)->invoices()->sum('taxed_amt');
        $prevReturns = Transaction::where('salesman_id', $salesman->id)
            ->where('period', $prevM)->returns()->sum(DB::raw('ABS(taxed_amt)'));
        $prevNet = $prevSales - $prevReturns;
        $momGrowth = $prevNet > 0 ? (($netSales - $prevNet) / $prevNet) * 100 : 0;

        // ══════════════════════════════════════════════════════
        // 2. TARGET & RUN RATE
        // ══════════════════════════════════════════════════════
        $target = SalesmanTarget::where('salesman_id', $salesman->id)
            ->where('period', $period)->first();
        $targetValue = $target ? $target->target_amount : 0;

        // Auto-estimate if no target set
        if ($targetValue <= 0) {
            $past3M = Carbon::parse($period . '-01')->subMonths(3)->format('Y-m');
            $vPast = Transaction::where('salesman_id', $salesman->id)->invoices()
                ->whereBetween('period', [$past3M, $prevM])->sum('taxed_amt');
            $targetValue = ($vPast / 3) * 1.1;
        }

        $gap = max(0, $targetValue - $totalSales);
        $targetProgress = $targetValue > 0 ? ($totalSales / $targetValue) * 100 : 0;
        $daysInM = Carbon::parse($period . '-01')->daysInMonth;
        $workDays = ceil($daysInM * 0.86);
        $cwd = Carbon::now()->format('Y-m') === $period ? Carbon::now()->day : $daysInM;
        $remainingDays = max(1, $workDays - ceil($cwd * 0.86));
        $dailyRunRate = $gap / $remainingDays;

        // ══════════════════════════════════════════════════════
        // 3. WEEKLY TREND
        // ══════════════════════════════════════════════════════
        $weeklyTrend = Transaction::where('salesman_id', $salesman->id)
            ->where('period', $period)
            ->select('week', 'type', DB::raw('SUM(ABS(taxed_amt)) as total'))
            ->groupBy('week', 'type')->orderBy('week')->get()->groupBy('week');

        // ══════════════════════════════════════════════════════
        // 4. TOP PRODUCTS & OUTLETS
        // ══════════════════════════════════════════════════════
        $topProducts = Transaction::where('transactions.salesman_id', $salesman->id)
            ->where('transactions.period', $period)->invoices()
            ->join('products', 'transactions.product_id', '=', 'products.id')
            ->select('products.name', DB::raw('SUM(transactions.taxed_amt) as total_sales'),
                DB::raw('SUM(transactions.qty_base) as total_qty'))
            ->groupBy('products.name')->orderByDesc('total_sales')->limit(10)->get();

        $topOutlets = Transaction::where('transactions.salesman_id', $salesman->id)
            ->where('transactions.period', $period)->invoices()
            ->join('outlets', 'transactions.outlet_id', '=', 'outlets.id')
            ->select('outlets.name', 'outlets.city', DB::raw('SUM(transactions.taxed_amt) as total_sales'),
                DB::raw('COUNT(DISTINCT transactions.id) as trx_count'))
            ->groupBy('outlets.name', 'outlets.city')->orderByDesc('total_sales')->limit(10)->get();

        // ══════════════════════════════════════════════════════
        // 5. SLEEPING OUTLETS (Churn)
        // ══════════════════════════════════════════════════════
        $activeLast = Transaction::where('period', $prevM)->where('type', 'I')
            ->where('salesman_id', $salesman->id)->pluck('outlet_id')->unique();
        $activeThis = Transaction::where('period', $period)->where('type', 'I')
            ->where('salesman_id', $salesman->id)->pluck('outlet_id')->unique();
        $lostOutletIds = $activeLast->diff($activeThis);
        $sleepingCount = $lostOutletIds->count();
        $sleepingValue = Transaction::where('period', $prevM)->where('type', 'I')
            ->whereIn('outlet_id', $lostOutletIds)->sum('taxed_amt');

        $sleepingOutlets = collect();
        if ($sleepingCount > 0) {
            $sleepingOutlets = \App\Models\Outlet::whereIn('id', $lostOutletIds)
                ->select('outlets.*')
                ->selectSub(
                    Transaction::whereColumn('transactions.outlet_id', 'outlets.id')
                        ->where('period', $prevM)->where('type', 'I')
                        ->selectRaw('COALESCE(SUM(taxed_amt), 0)'),
                    'last_month_sales'
                )->orderByDesc('last_month_sales')->limit(10)->get();
        }

        // ══════════════════════════════════════════════════════
        // 6. AR PIUTANG
        // ══════════════════════════════════════════════════════
        $latestArImport = ArImportLog::where('status', 'completed')
            ->orderByDesc('report_date')->first();
        $arData = null;

        if ($latestArImport) {
            $arQuery = ArReceivable::where('ar_import_log_id', $latestArImport->id)
                ->where('salesman_name', $salesman->name)
                ->where('ar_balance', '>', 0);

            $arSummary = (clone $arQuery)->selectRaw('
                SUM(ar_balance) as total_outstanding,
                SUM(ar_amount) as total_invoiced,
                SUM(ar_paid) as total_paid,
                COUNT(*) as invoice_count,
                COUNT(DISTINCT outlet_code) as outlet_count,
                AVG(CASE WHEN overdue_days > 0 THEN overdue_days ELSE NULL END) as avg_overdue,
                MAX(overdue_days) as max_overdue,
                SUM(CASE WHEN overdue_days > 0 THEN ar_balance ELSE 0 END) as total_overdue
            ')->first();

            $arTopOutlets = (clone $arQuery)->selectRaw('
                outlet_code, outlet_name, SUM(ar_balance) as total_balance,
                MAX(overdue_days) as max_overdue, MAX(cm) as max_cm, COUNT(*) as inv_count
            ')->groupBy('outlet_code', 'outlet_name')
                ->orderByDesc(DB::raw('SUM(ar_balance)'))->limit(10)->get();

            $arTopOutletCodes = $arTopOutlets->pluck('outlet_code');
            $arTopInvoices = (clone $arQuery)->whereIn('outlet_code', $arTopOutletCodes)
                ->orderBy('overdue_days', 'desc')->get()->groupBy('outlet_code');

            // Piutang Kritis (> 60 hari)
            $arCriticalInvoices = (clone $arQuery)->where('overdue_days', '>=', 60)
                ->orderByDesc('overdue_days')
                ->limit(20)->get();

            if ($arSummary && $arSummary->total_outstanding > 0) {
                $arData = [
                    'import' => $latestArImport,
                    'summary' => $arSummary,
                    'topOutlets' => $arTopOutlets,
                    'topInvoices' => $arTopInvoices,
                    'criticalInvoices' => $arCriticalInvoices,
                ];
            }
        }

        // ══════════════════════════════════════════════════════
        // 7. RECENT TRANSACTIONS
        // ══════════════════════════════════════════════════════
        $recentTransactions = Transaction::where('transactions.salesman_id', $salesman->id)
            ->where('transactions.period', $period)
            ->join('outlets', 'transactions.outlet_id', '=', 'outlets.id')
            ->join('products', 'transactions.product_id', '=', 'products.id')
            ->select('transactions.so_date', 'transactions.type', 'outlets.name as outlet_name',
                'products.name as product_name', 'transactions.qty_base', 'transactions.taxed_amt')
            ->orderByDesc('transactions.so_date')
            ->limit(20)->get();

        // ══════════════════════════════════════════════════════
        // 8. AI GREETING
        // ══════════════════════════════════════════════════════
        $hour = now()->hour;
        $greeting = $hour < 12 ? 'Selamat Pagi' : ($hour < 17 ? 'Selamat Siang' : 'Selamat Malam');

        return view('salesman-dashboard', compact(
            'salesman', 'period', 'periods', 'greeting',
            'totalSales', 'totalReturns', 'netSales', 'returnRate',
            'invoiceCount', 'outletCount', 'momGrowth',
            'targetValue', 'gap', 'targetProgress', 'dailyRunRate', 'remainingDays',
            'weeklyTrend', 'topProducts', 'topOutlets',
            'sleepingCount', 'sleepingValue', 'sleepingOutlets',
            'arData', 'recentTransactions'
        ));
    }
}
