<?php

namespace App\Http\Controllers;

use App\Models\ArImportLog;
use App\Models\ArReceivable;
use App\Models\Principal;
use App\Models\Salesman;
use App\Models\SalesmanTarget;
use App\Models\SalesPerStock;
use App\Models\Transaction;
use App\Support\AnalyticsCache;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // Auto-redirect salesman ke dashboard pribadinya
        if (auth()->user()->isSalesman()) {
            return redirect()->route('salesman.dashboard', $request->query());
        }

        $periods = Transaction::select('period')->distinct()->orderByDesc('period')->pluck('period');

        $latestPeriod = $periods->first() ?? date('Y-m');

        // Derive the active display period from the filter params.
        // The filter form posts start_period/end_period, not 'period'.
        // We use end_period as the "current" reference period for narratives & MoM.
        $startPeriod = $request->get('start_period', $request->get('period', $latestPeriod));
        $endPeriod = $request->get('end_period', $request->get('period', $latestPeriod));
        $period = $endPeriod; // canonical display period (used by AI narrative, prev-period calc, etc.)

        $dateStart = Carbon::parse($startPeriod.'-01');
        $dateEnd = Carbon::parse($endPeriod.'-01');
        $monthsDiff = $dateStart->diffInMonths($dateEnd) + 1; // Range duration in months

        // Previous equivalent range
        $prevStartPeriod = $dateStart->copy()->subMonths($monthsDiff)->format('Y-m');
        $prevEndPeriod = $dateEnd->copy()->subMonths($monthsDiff)->format('Y-m');

        // Create a mock request to pass to the trait for the PREVIOUS period comparison
        $prevRequest = new Request;
        $prevRequest->merge([
            'start_period' => $prevStartPeriod,
            'end_period' => $prevEndPeriod,
            'principal_id' => $request->get('principal_id'),
        ]);

        $userId = auth()->id();
        $isSupervisor = auth()->user()->isSupervisor();
        $principalId = $request->get('principal_id', 'all');
        $cacheKey = AnalyticsCache::key('dashboard_data', [$userId, $isSupervisor, $startPeriod, $endPeriod, $principalId]);

        $data = cache()->remember($cacheKey, 3600, function () use ($request, $prevRequest, $startPeriod, $endPeriod, $period, $periods) {
            // Principal Options for Filter Dropdown
            if (auth()->user()->isSupervisor()) {
                $principals = auth()->user()->principals()->orderBy('name')->get();
            } else {
                $principals = Principal::orderBy('name')->get();
            }

            // KPI Cards (Current Period Range)
            $kpis = Transaction::withFilters($request)
                ->selectRaw('
                    COALESCE(SUM(CASE WHEN type = "I" THEN taxed_amt ELSE 0 END), 0) as total_sales,
                    COALESCE(SUM(CASE WHEN type = "R" THEN ABS(taxed_amt) ELSE 0 END), 0) as total_returns,
                    COALESCE(SUM(CASE WHEN type = "I" THEN cogs ELSE 0 END), 0) as invoice_cogs,
                    COALESCE(SUM(CASE WHEN type = "R" THEN ABS(cogs) ELSE 0 END), 0) as return_cogs,
                    COUNT(CASE WHEN type = "I" THEN 1 END) as invoice_count,
                    COUNT(CASE WHEN type = "R" THEN 1 END) as return_count
                ')
                ->first();

            $totalSales = (float) $kpis->total_sales;
            $totalReturns = (float) $kpis->total_returns;
            $invoiceCogs = (float) $kpis->invoice_cogs;
            $returnCogs = (float) $kpis->return_cogs;
            $invoiceCount = (int) $kpis->invoice_count;
            $returnCount = (int) $kpis->return_count;
            $totalCogs = $invoiceCogs - $returnCogs; // Net COGS: offset returned goods back to warehouse
            $netSales = $totalSales - $totalReturns;
            $margin = $netSales > 0 ? (($netSales - $totalCogs) / $netSales) * 100 : 0;
            $returnRate = $totalSales > 0 ? ($totalReturns / $totalSales) * 100 : 0;

            // Prev Period KPIs for MoM Growth (Using the mock request)
            $prevKpis = Transaction::withFilters($prevRequest)
                ->selectRaw('
                    COALESCE(SUM(CASE WHEN type = "I" THEN taxed_amt ELSE 0 END), 0) as total_sales,
                    COALESCE(SUM(CASE WHEN type = "R" THEN ABS(taxed_amt) ELSE 0 END), 0) as total_returns
                ')
                ->first();

            $prevSales = (float) $prevKpis->total_sales;
            $prevReturns = (float) $prevKpis->total_returns;
            $prevNetSales = $prevSales - $prevReturns;

            // MoM Percentage Calculations
            $momSales = $prevSales > 0 ? (($totalSales - $prevSales) / $prevSales) * 100 : 0;
            $momReturns = $prevReturns > 0 ? (($totalReturns - $prevReturns) / $prevReturns) * 100 : 0;
            $momNetSales = $prevNetSales > 0 ? (($netSales - $prevNetSales) / $prevNetSales) * 100 : 0;

            // Weekly Trend
            $weeklyTrend = Transaction::withFilters($request)
                ->select('week', 'type', DB::raw('SUM(ABS(taxed_amt)) as total'))
                ->groupBy('week', 'type')
                ->orderBy('week')
                ->get()
                ->groupBy('week');

            // Top 10 Products by revenue
            $topProducts = Transaction::withFilters($request)->invoices()
                ->join('products', 'transactions.product_id', '=', 'products.id')
                ->select('products.name', DB::raw('SUM(transactions.taxed_amt) as total_sales'), DB::raw('SUM(transactions.qty_base) as total_qty'))
                ->groupBy('products.name')
                ->orderByDesc('total_sales')
                ->limit(10)
                ->get();

            // Top 10 Outlets by revenue
            $topOutlets = Transaction::withFilters($request)->invoices()
                ->join('outlets', 'transactions.outlet_id', '=', 'outlets.id')
                ->select('outlets.name', 'outlets.city', DB::raw('SUM(transactions.taxed_amt) as total_sales'))
                ->groupBy('outlets.name', 'outlets.city')
                ->orderByDesc('total_sales')
                ->limit(10)
                ->get();

            // Principal breakdown
            $principalBreakdown = Transaction::withFilters($request)->invoices()
                ->join('products', 'transactions.product_id', '=', 'products.id')
                ->join('principals', 'products.principal_id', '=', 'principals.id')
                ->select('principals.name', DB::raw('SUM(transactions.taxed_amt) as total_sales'))
                ->groupBy('principals.name')
                ->orderByDesc('total_sales')
                ->get();

            // 1. System Alerts
            $criticalStockCount = SalesPerStock::where('period', $period)->where('swc', '<=', 2)->where('swc', '>', 0)->count();
            $overstockCount = SalesPerStock::where('period', $period)->where('swc', '>=', 12)->count();

            $latestArImport = ArImportLog::where('status', 'completed')->orderByDesc('report_date')->first();
            $overdueArAmount = 0;
            if ($latestArImport) {
                $overdueArAmount = ArReceivable::where('ar_import_log_id', $latestArImport->id)
                    ->where('overdue_days', '>', 0)
                    ->where('ar_balance', '>', 0)
                    ->sum('ar_balance');
            }

            // 2. Global Target vs Achievement (using Net Sales for accurate progress)
            $globalTarget = SalesmanTarget::where('period', $period)->sum('target_amount');
            $globalTarget = $globalTarget > 0 ? $globalTarget : null;
            $globalProgress = $globalTarget ? ($netSales / $globalTarget) * 100 : null;

            // 3. Today's Sales
            $latestSoDate = Transaction::withFilters($request)->invoices()->max('so_date');
            $todayStr = Carbon::today()->format('Y-m-d');
            $displayDate = $latestSoDate && Carbon::parse($latestSoDate)->isToday() ? $todayStr : ($latestSoDate ?? $todayStr);

            $todaySales = Transaction::withFilters($request)
                ->invoices()
                ->whereDate('so_date', $displayDate)
                ->sum('taxed_amt');

            // --- DISTORA AI NARRATIVE GENERATOR ---
            $aiStatus = $momNetSales >= 0 ? 'mengalami pertumbuhan positif' : 'mengalami penurunan';
            $aiDir = $momNetSales >= 0 ? 'naik' : 'turun';

            $topRegion = Transaction::withFilters($request)
                ->join('outlets', 'transactions.outlet_id', '=', 'outlets.id')
                ->select(DB::raw('SUBSTR(outlets.code, 1, 3) as region_code'), DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.taxed_amt ELSE 0 END) as total_sales'))
                ->whereNotNull('outlets.code')->groupBy('region_code')->orderByDesc('total_sales')->first();
            $regionText = $topRegion && $topRegion->total_sales > 0 ? ' Penopang utama omset adalah wilayah '.strtoupper($topRegion->region_code).'.' : '';

            $prevM = Carbon::parse($period.'-01')->subMonth()->format('Y-m');
            $activeLast = Transaction::where('period', $prevM)->where('type', 'I');
            if ($request->has('principal_id') && $request->principal_id != 'all') {
                $activeLast->join('products', 'transactions.product_id', '=', 'products.id')
                    ->where('products.principal_id', $request->principal_id);
            }
            $activeLastMonth = $activeLast->pluck('transactions.outlet_id')->unique();

            $activeThis = Transaction::where('period', $period)->where('type', 'I');
            if ($request->has('principal_id') && $request->principal_id != 'all') {
                $activeThis->join('products', 'transactions.product_id', '=', 'products.id')
                    ->where('products.principal_id', $request->principal_id);
            }
            $activeThisMonth = $activeThis->pluck('transactions.outlet_id')->unique();

            $sleepingCount = $activeLastMonth->diff($activeThisMonth)->count();
            $sleeperText = $sleepingCount > 0 ? " Harap waspadakan terdapat $sleepingCount Toko yang bulan lalu aktif namun bulan ini lenyap (berhenti order)." : '';

            $aiNarrative = 'Berdasarkan data bulan '.Carbon::parse($period.'-01')->translatedFormat('F Y').", kinerja $aiStatus ($aiDir ".number_format(abs($momNetSales), 1)."%).$regionText$sleeperText Segera ambil langkah taktis!";

            return compact(
                'period', 'periods', 'totalSales', 'totalReturns', 'totalCogs',
                'netSales', 'margin', 'returnRate', 'invoiceCount', 'returnCount',
                'weeklyTrend', 'topProducts', 'topOutlets', 'principalBreakdown',
                'momSales', 'momReturns', 'momNetSales', 'aiNarrative',
                'criticalStockCount', 'overstockCount', 'overdueArAmount',
                'globalTarget', 'globalProgress', 'todaySales', 'displayDate', 'principals'
            );
        });

        return view('dashboard', $data);
    }
}
