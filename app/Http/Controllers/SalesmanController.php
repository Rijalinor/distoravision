<?php

namespace App\Http\Controllers;

use App\Models\Salesman;
use App\Models\Transaction;
use App\Models\ArImportLog;
use App\Models\ArReceivable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesmanController extends Controller
{
    public function index(Request $request)
    {
        $period = $request->get('period', Transaction::max('period') ?? date('Y-m'));
        $periods = Transaction::select('period')->distinct()->orderByDesc('period')->pluck('period');

        $salesmen = Salesman::select('salesmen.*')
            ->withCount([
                'transactions as invoice_count' => fn($q) => $q->where('type', 'I')->withFilters(request()),
                'transactions as return_count' => fn($q) => $q->where('type', 'R')->withFilters(request()),
            ])
            ->selectSub(
                Transaction::whereColumn('transactions.salesman_id', 'salesmen.id')
                    ->where('type', 'I')->withFilters(request())
                    ->selectRaw('COALESCE(SUM(taxed_amt), 0)'),
                'total_sales'
            )
            ->withSum([
                'transactions as total_returns' => fn($q) => $q->where('type', 'R')->withFilters(request()),
            ], 'taxed_amt')
            ->whereHas('transactions', fn($q) => $q->withFilters(request()))
            ->orderByDesc('total_sales')
            ->paginate(20)
            ->appends(['period' => $period]);

        // Convert returns to absolute
        $salesmen->getCollection()->transform(function ($s) {
            $s->total_returns = abs($s->total_returns ?? 0);
            return $s;
        });

        return view('salesmen.index', compact('salesmen', 'period', 'periods'));
    }

    public function show(Request $request, Salesman $salesman)
    {
        $period = $request->get('period', Transaction::max('period') ?? date('Y-m'));
        $periods = Transaction::select('period')->distinct()->orderByDesc('period')->pluck('period');

        $stats = [
            'total_sales' => Transaction::where('salesman_id', $salesman->id)->withFilters(request())->invoices()->sum('taxed_amt'),
            'total_returns' => Transaction::where('salesman_id', $salesman->id)->withFilters(request())->returns()->sum(DB::raw('ABS(taxed_amt)')),
            'outlet_count' => Transaction::where('salesman_id', $salesman->id)->withFilters(request())->distinct('outlet_id')->count('outlet_id'),
            'trx_count' => Transaction::where('salesman_id', $salesman->id)->withFilters(request())->invoices()->count(),
        ];

        $topProducts = Transaction::where('transactions.salesman_id', $salesman->id)
            ->withFilters(request())->invoices()
            ->join('products', 'transactions.product_id', '=', 'products.id')
            ->select('products.name', DB::raw('SUM(transactions.taxed_amt) as total'), DB::raw('SUM(transactions.qty_base) as qty'))
            ->groupBy('products.name')->orderByDesc('total')->limit(10)->get();

        $topOutlets = Transaction::where('transactions.salesman_id', $salesman->id)
            ->withFilters(request())->invoices()
            ->join('outlets', 'transactions.outlet_id', '=', 'outlets.id')
            ->select('outlets.name', 'outlets.city', DB::raw('SUM(transactions.taxed_amt) as total'))
            ->groupBy('outlets.name', 'outlets.city')->orderByDesc('total')->limit(10)->get();

        $weeklyData = Transaction::where('salesman_id', $salesman->id)->withFilters(request())
            ->select('week', 'type', DB::raw('SUM(ABS(taxed_amt)) as total'))
            ->groupBy('week', 'type')->orderBy('week')->get()->groupBy('week');

        $weeklyData = Transaction::where('salesman_id', $salesman->id)->withFilters(request())
            ->select('week', 'type', DB::raw('SUM(ABS(taxed_amt)) as total'))
            ->groupBy('week', 'type')->orderBy('week')->get()->groupBy('week');

        // --- SALESMAN 360 APPRAISAL LOGIC ---
        // 1. Personal Return Rate
        $returnRate = $stats['total_sales'] > 0 ? ($stats['total_returns'] / $stats['total_sales']) * 100 : 0;

        // 2. Personal Sleeper (Churned) Outlets
        $prevM = \Carbon\Carbon::parse($period . '-01')->subMonth()->format('Y-m');
        $activeLast = Transaction::where('period', $prevM)->where('type', 'I')->where('salesman_id', $salesman->id)->pluck('outlet_id')->unique();
        $activeThis = Transaction::where('period', $period)->where('type', 'I')->where('salesman_id', $salesman->id)->pluck('outlet_id')->unique();
        $lostOutletsKeys = $activeLast->diff($activeThis);
        $lostOutletsCount = $lostOutletsKeys->count();
        $lostOutletsValue = Transaction::where('period', $prevM)->where('type', 'I')->whereIn('outlet_id', $lostOutletsKeys)->sum('taxed_amt');

        // 3. Personal Target & Run-Rate (Assumes Target = 3-month avg + 10% stretch)
        $past3MStart = \Carbon\Carbon::parse($period . '-01')->subMonths(3)->format('Y-m');
        $vPast = Transaction::where('salesman_id', $salesman->id)->invoices()
            ->whereBetween('period', [$past3MStart, $prevM])->sum('taxed_amt');
        
        $personalTarget = ($vPast / 3) * 1.1; 
        $shortfall = $personalTarget - $stats['total_sales'];
        $shortfall = $shortfall > 0 ? $shortfall : 0;
        
        $daysInM = \Carbon\Carbon::parse($period . '-01')->daysInMonth;
        $workDays = ceil($daysInM * 0.86); // ~26 days
        $cwd = \Carbon\Carbon::now()->format('Y-m') === $period ? \Carbon\Carbon::now()->day : $daysInM;
        $remainingDays = max(1, $workDays - ceil($cwd * 0.86));
        $dailyRunRateRequired = $shortfall / $remainingDays;
        
        $targetProgress = $personalTarget > 0 ? ($stats['total_sales'] / $personalTarget) * 100 : 0;

        
        // ── AR (PIUTANG) SECTION ──────────────────────────────────────
        $latestArImport = ArImportLog::where('status', 'completed')->orderByDesc('report_date')->first();
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
                SUM(CASE WHEN overdue_days > 0 THEN ar_balance ELSE 0 END) as total_overdue,
                SUM(CASE WHEN cm >= 3 THEN 1 ELSE 0 END) as stubborn_count
            ')->first();

            $arTopOutlets = (clone $arQuery)->selectRaw('
                outlet_code, outlet_name, SUM(ar_balance) as total_balance,
                MAX(overdue_days) as max_overdue, MAX(cm) as max_cm, COUNT(*) as inv_count
            ')->groupBy('outlet_code', 'outlet_name')
                ->orderByDesc(DB::raw('SUM(ar_balance)'))->get();

            $topOutletCodes = $arTopOutlets->pluck('outlet_code');
            $topInvoices = (clone $arQuery)->whereIn('outlet_code', $topOutletCodes)
                ->orderByDesc('ar_balance')->get()->groupBy('outlet_code');

            if ($arSummary && $arSummary->total_outstanding > 0) {
                $arData = [
                    'import' => $latestArImport,
                    'summary' => $arSummary,
                    'topOutlets' => $arTopOutlets,
                    'topInvoices' => $topInvoices,
                ];
            }
        }

        return view('salesmen.show', compact(
            'salesman', 'period', 'periods', 'stats', 'topProducts', 'topOutlets', 'weeklyData',
            'returnRate', 'lostOutletsCount', 'lostOutletsValue', 'personalTarget', 'shortfall', 'dailyRunRateRequired', 'targetProgress',
            'arData'
        ));
    }
}

