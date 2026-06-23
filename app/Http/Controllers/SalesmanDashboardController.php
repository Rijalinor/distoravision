<?php

namespace App\Http\Controllers;

use App\Models\ArImportLog;
use App\Models\ArReceivable;
use App\Models\Outlet;
use App\Models\Salesman;
use App\Models\SalesmanTarget;
use App\Models\SalesPerTransaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesmanDashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        // Harus role salesman dan punya salesman_id
        if (! $user->isSalesman() || ! $user->salesman_id) {
            abort(403, 'Dashboard ini hanya untuk akun Salesman.');
        }

        $salesman = Salesman::findOrFail($user->salesman_id);
        $period = $request->get('period', SalesPerTransaction::max('period') ?? date('Y-m'));
        $periods = SalesPerTransaction::select('period')->distinct()->orderByDesc('period')->pluck('period');
        $prevM = Carbon::parse($period.'-01')->subMonth()->format('Y-m');

        // ══════════════════════════════════════════════════════
        // 1. SALES KPIs
        // ══════════════════════════════════════════════════════
        $totalSales = SalesPerTransaction::where('sales_code', $salesman->sales_code)
            ->where('period', $period)->invoices()->sum('subtotal');
        $totalReturns = SalesPerTransaction::where('sales_code', $salesman->sales_code)
            ->where('period', $period)->returns()->sum('subtotal');
        $netSales = $totalSales - $totalReturns;
        $returnRate = $totalSales > 0 ? ($totalReturns / $totalSales) * 100 : 0;
        $invoiceCount = SalesPerTransaction::where('sales_code', $salesman->sales_code)
            ->where('period', $period)->invoices()->distinct('so_no')->count('so_no');
        $outletCount = SalesPerTransaction::where('sales_code', $salesman->sales_code)
            ->where('period', $period)->invoices()->distinct('outlet_code')->count('outlet_code');

        // Previous period for MoM
        $prevSales = SalesPerTransaction::where('sales_code', $salesman->sales_code)
            ->where('period', $prevM)->invoices()->sum('subtotal');
        $prevReturns = SalesPerTransaction::where('sales_code', $salesman->sales_code)
            ->where('period', $prevM)->returns()->sum('subtotal');
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
            $past3M = Carbon::parse($period.'-01')->subMonths(3)->format('Y-m');
            $historicalMonths = SalesPerTransaction::where('sales_code', $salesman->sales_code)->invoices()
                ->whereBetween('period', [$past3M, $prevM])
                ->distinct('period')->pluck('period');
            $vPast = SalesPerTransaction::where('sales_code', $salesman->sales_code)->invoices()
                ->whereBetween('period', [$past3M, $prevM])->sum('subtotal');
            $monthCount = max($historicalMonths->count(), 1); // Avoid division by zero for new salesmen
            $targetValue = ($vPast / $monthCount) * 1.1;
        }

        $gap = max(0, $targetValue - $totalSales);
        $targetProgress = $targetValue > 0 ? ($totalSales / $targetValue) * 100 : 0;
        $workDays = 26; // Consistent working days assumption across all dashboards
        $isCurrentMonth = Carbon::now()->format('Y-m') === $period;
        $currentDay = $isCurrentMonth ? (int) date('j') : $workDays;
        $remainingDays = max(1, $workDays - $currentDay);
        $dailyRunRate = $gap / $remainingDays;

        // ══════════════════════════════════════════════════════
        // 3. WEEKLY TREND
        // ══════════════════════════════════════════════════════
        $weeklyPerformanceRaw = SalesPerTransaction::where('sales_code', $salesman->sales_code)
            ->where('period', $period)
            ->select('so_date', 'type', DB::raw('SUM(subtotal) as total'))
            ->groupBy('so_date', 'type')
            ->get();

        $weeklyTrend = collect();

        foreach ($weeklyPerformanceRaw as $row) {
            $day = (int) date('j', strtotime($row->so_date));
            $week = (int) floor(($day - 1) / 7) + 1;
            $week = min(5, $week);

            if (! $weeklyTrend->has($week)) {
                $weeklyTrend->put($week, collect());
            }

            $existing = $weeklyTrend->get($week)->firstWhere('type', $row->type);
            if ($existing) {
                $existing->total += (float) $row->total;
            } else {
                $weeklyTrend->get($week)->push((object) [
                    'week' => $week,
                    'type' => $row->type,
                    'total' => (float) $row->total,
                ]);
            }
        }

        $weeklyTrend = $weeklyTrend->sortKeys();

        // ══════════════════════════════════════════════════════
        // 4. TOP PRODUCTS & OUTLETS
        // ══════════════════════════════════════════════════════
        $topProducts = SalesPerTransaction::where('sales_code', $salesman->sales_code)
            ->where('period', $period)->invoices()
            ->select('item_name as name', DB::raw('SUM(subtotal) as total_sales'),
                DB::raw('SUM(qty) as total_qty'))
            ->groupBy('item_name')->orderByDesc('total_sales')->limit(10)->get();

        $topOutlets = SalesPerTransaction::where('sales_per_transactions.sales_code', $salesman->sales_code)
            ->where('sales_per_transactions.period', $period)->invoices()
            ->leftJoin('outlets', 'sales_per_transactions.outlet_code', '=', 'outlets.code')
            ->select('sales_per_transactions.outlet_name as name', 'outlets.city', DB::raw('SUM(sales_per_transactions.subtotal) as total_sales'),
                DB::raw('COUNT(DISTINCT sales_per_transactions.so_no) as trx_count'))
            ->groupBy('sales_per_transactions.outlet_name', 'outlets.city')
            ->orderByDesc('total_sales')->limit(10)->get();

        // ══════════════════════════════════════════════════════
        // 5. SLEEPING OUTLETS (Churn)
        // ══════════════════════════════════════════════════════
        $activeLast = SalesPerTransaction::where('period', $prevM)->where('type', 'I')
            ->where('sales_code', $salesman->sales_code)->pluck('outlet_code')->unique();
        $activeThis = SalesPerTransaction::where('period', $period)->where('type', 'I')
            ->where('sales_code', $salesman->sales_code)->pluck('outlet_code')->unique();
        $lostOutletCodes = $activeLast->diff($activeThis);
        $sleepingCount = $lostOutletCodes->count();
        $sleepingValue = SalesPerTransaction::where('period', $prevM)->where('type', 'I')
            ->where('sales_code', $salesman->sales_code)
            ->whereIn('outlet_code', $lostOutletCodes)->sum('subtotal');

        $sleepingOutlets = collect();
        if ($sleepingCount > 0) {
            $sleepingOutlets = Outlet::whereIn('code', $lostOutletCodes)
                ->select('outlets.*')
                ->selectSub(
                    SalesPerTransaction::whereColumn('sales_per_transactions.outlet_code', 'outlets.code')
                        ->where('sales_code', $salesman->sales_code)
                        ->where('period', $prevM)->where('type', 'I')
                        ->selectRaw('COALESCE(SUM(subtotal), 0)'),
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
        // 7. RECENT TRANSACTIONS (Grouped by Invoice)
        // ══════════════════════════════════════════════════════
        $rawRecent = SalesPerTransaction::where('sales_code', $salesman->sales_code)
            ->where('period', $period)
            ->select('id', 'so_date', 'type', 'outlet_name', 'so_no', 'pfi_no', 'item_no', 'item_name', 'qty', 'subtotal')
            ->orderByDesc('so_date')
            ->orderByDesc('id')
            ->get();

        $recentInvoicesMap = [];
        foreach ($rawRecent as $trx) {
            $key = $trx->so_no ?: ($trx->pfi_no ?: 'TRX-'.$trx->id);

            if (! isset($recentInvoicesMap[$key])) {
                $recentInvoicesMap[$key] = (object) [
                    'so_no' => $trx->so_no ?: $trx->pfi_no ?: '-',
                    'so_date' => $trx->so_date,
                    'type' => $trx->type,
                    'outlet_name' => $trx->outlet_name,
                    'total_qty' => 0,
                    'total_value' => 0,
                    'items' => [],
                ];
            }

            $recentInvoicesMap[$key]->total_qty += abs($trx->qty);
            $recentInvoicesMap[$key]->total_value += abs($trx->subtotal);
            $recentInvoicesMap[$key]->items[] = (object) [
                'item_name' => $trx->item_name,
                'item_no' => $trx->item_no,
                'qty' => abs($trx->qty),
                'subtotal' => abs($trx->subtotal),
            ];
        }

        $recentInvoices = collect(array_values($recentInvoicesMap))->take(20);

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
            'arData', 'recentInvoices'
        ));
    }
}
