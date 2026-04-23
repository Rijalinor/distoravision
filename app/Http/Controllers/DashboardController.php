<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\Salesman;
use App\Models\Outlet;
use App\Models\Principal;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // Auto-redirect salesman ke dashboard pribadinya
        if (auth()->user()->isSalesman() && auth()->user()->salesman_id) {
            return redirect()->route('salesman.dashboard', $request->query());
        }

        $latestPeriod = Transaction::max('period') ?? date('Y-m');

        // Derive the active display period from the filter params.
        // The filter form posts start_period/end_period, not 'period'.
        // We use end_period as the "current" reference period for narratives & MoM.
        $startPeriod = $request->get('start_period', $request->get('period', $latestPeriod));
        $endPeriod   = $request->get('end_period',   $request->get('period', $latestPeriod));
        $period      = $endPeriod; // canonical display period (used by AI narrative, prev-period calc, etc.)

        $periods = Transaction::select('period')->distinct()->orderByDesc('period')->pluck('period');
        
        $dateStart = \Carbon\Carbon::parse($startPeriod . '-01');
        $dateEnd = \Carbon\Carbon::parse($endPeriod . '-01');
        $monthsDiff = $dateStart->diffInMonths($dateEnd) + 1; // Range duration in months

        // Previous equivalent range
        $prevStartPeriod = $dateStart->copy()->subMonths($monthsDiff)->format('Y-m');
        $prevEndPeriod = $dateEnd->copy()->subMonths($monthsDiff)->format('Y-m');

        // Create a mock request to pass to the trait for the PREVIOUS period comparison
        $prevRequest = new \Illuminate\Http\Request();
        $prevRequest->merge([
            'start_period' => $prevStartPeriod,
            'end_period' => $prevEndPeriod,
            'principal_id' => $request->get('principal_id')
        ]);

        // Principal Options for Filter Dropdown
        $principals = Principal::orderBy('name')->get();

        // KPI Cards (Current Period Range)
        $totalSales = Transaction::withFilters($request)->invoices()->sum('taxed_amt');
        $totalReturns = Transaction::withFilters($request)->returns()->sum(DB::raw('ABS(taxed_amt)'));
        $totalCogs = Transaction::withFilters($request)->invoices()->sum('cogs');
        $netSales = $totalSales - $totalReturns;
        $margin = $netSales > 0 ? (($netSales - $totalCogs) / $netSales) * 100 : 0;
        $returnRate = $totalSales > 0 ? ($totalReturns / $totalSales) * 100 : 0;
        
        // Prev Period KPIs for MoM Growth (Using the mock request)
        $prevSales = Transaction::withFilters($prevRequest)->invoices()->sum('taxed_amt');
        $prevReturns = Transaction::withFilters($prevRequest)->returns()->sum(DB::raw('ABS(taxed_amt)'));
        $prevNetSales = $prevSales - $prevReturns;

        // MoM Percentage Calculations
        $momSales = $prevSales > 0 ? (($totalSales - $prevSales) / $prevSales) * 100 : 0;
        $momReturns = $prevReturns > 0 ? (($totalReturns - $prevReturns) / $prevReturns) * 100 : 0;
        $momNetSales = $prevNetSales > 0 ? (($netSales - $prevNetSales) / $prevNetSales) * 100 : 0;

        $invoiceCount = Transaction::withFilters($request)->invoices()->count();
        $returnCount = Transaction::withFilters($request)->returns()->count();

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

        // --- DISTORA AI NARRATIVE GENERATOR ---
        $aiStatus = $momNetSales >= 0 ? "mengalami pertumbuhan positif" : "mengalami penurunan";
        $aiDir = $momNetSales >= 0 ? "naik" : "turun";
        
        $topRegion = Transaction::withFilters($request)
            ->join('outlets', 'transactions.outlet_id', '=', 'outlets.id')
            ->select(DB::raw('LEFT(outlets.code, 3) as region_code'), DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.taxed_amt ELSE 0 END) as total_sales'))
            ->whereNotNull('outlets.code')->groupBy('region_code')->orderByDesc('total_sales')->first();
        $regionText = $topRegion && $topRegion->total_sales > 0 ? " Penopang utama omset adalah wilayah " . strtoupper($topRegion->region_code) . "." : "";

        $prevM = \Carbon\Carbon::parse($period . '-01')->subMonth()->format('Y-m');
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
        $sleeperText = $sleepingCount > 0 ? " Harap waspadakan terdapat $sleepingCount Toko yang bulan lalu aktif namun bulan ini lenyap (berhenti order)." : "";

        $aiNarrative = "Berdasarkan data bulan " . \Carbon\Carbon::parse($period.'-01')->translatedFormat('F Y') . ", kinerja $aiStatus ($aiDir " . number_format(abs($momNetSales), 1) . "%).$regionText$sleeperText Segera ambil langkah taktis!";

        return view('dashboard', compact(
            'period', 'periods', 'totalSales', 'totalReturns', 'totalCogs',
            'netSales', 'margin', 'returnRate', 'invoiceCount', 'returnCount',
            'weeklyTrend', 'topProducts', 'topOutlets', 'principalBreakdown',
            'momSales', 'momReturns', 'momNetSales', 'aiNarrative'
        ));
    }
}
