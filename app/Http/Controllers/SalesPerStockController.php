<?php

namespace App\Http\Controllers;

use App\Models\SalesPerStock;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesPerStockController extends Controller
{
    public function dashboard(Request $request)
    {
        $periods = SalesPerStock::select('period')->distinct()->orderByDesc('period')->pluck('period');

        if ($periods->isEmpty()) {
            return view('sales-per.stock-dashboard', ['hasData' => false, 'periods' => collect()]);
        }

        $period = $request->get('period', $periods->first());
        $selectedPrincipal = $request->get('principal');
        $selectedWarehouse = $request->get('warehouse');
        $periodLabel = Carbon::parse($period.'-01')->translatedFormat('F Y');

        $baseQuery = SalesPerStock::where('period', $period);
        if ($selectedPrincipal && $selectedPrincipal !== 'all') {
            $baseQuery->where('principal_name', $selectedPrincipal);
        }
        if ($selectedWarehouse && $selectedWarehouse !== 'all') {
            $baseQuery->where('warehouse_name', $selectedWarehouse);
        }

        // ══════════════════════════════════════════════════════
        // 1. OVERALL KPIs
        // ══════════════════════════════════════════════════════
        $totalSKU = (clone $baseQuery)->count();
        $totalStockValue = (clone $baseQuery)->sum('stock_value_on_hand');
        $totalOnHand = (clone $baseQuery)->sum('on_hand_base');
        $avgSWC = (clone $baseQuery)->where('swc', '>', 0)->avg('swc');

        // Critical counts
        $criticalLow = (clone $baseQuery)->where('swc', '<=', 2)->where('swc', '>', 0)->count();
        $slowMovingCount = (clone $baseQuery)->where(function ($q) {
            $q->where('swc', '>', 8)->orWhere('swc', 0);
        })->where('stock_value_on_hand', '>', 0)->count();

        // ══════════════════════════════════════════════════════
        // 1.b TREND ANALYSIS (MoM)
        // ══════════════════════════════════════════════════════
        $prevPeriod = Carbon::parse($period.'-01')->subMonth()->format('Y-m');
        $prevBaseQuery = SalesPerStock::where('period', $prevPeriod);
        if ($selectedPrincipal && $selectedPrincipal !== 'all') {
            $prevBaseQuery->where('principal_name', $selectedPrincipal);
        }
        if ($selectedWarehouse && $selectedWarehouse !== 'all') {
            $prevBaseQuery->where('warehouse_name', $selectedWarehouse);
        }

        $hasPrevPeriod = (clone $prevBaseQuery)->exists();
        $prevTotalSKU = $hasPrevPeriod ? (clone $prevBaseQuery)->count() : 0;
        $prevTotalStockValue = $hasPrevPeriod ? (clone $prevBaseQuery)->sum('stock_value_on_hand') : 0;
        $prevCriticalLow = $hasPrevPeriod ? (clone $prevBaseQuery)->where('swc', '<=', 2)->where('swc', '>', 0)->count() : 0;
        $prevSlowMovingCount = $hasPrevPeriod ? (clone $prevBaseQuery)->where(function ($q) {
            $q->where('swc', '>', 8)->orWhere('swc', 0);
        })->where('stock_value_on_hand', '>', 0)->count() : 0;

        $calculateTrend = function ($current, $previous, $lowerIsBetter = false) use ($hasPrevPeriod) {
            if (! $hasPrevPeriod) {
                return null;
            }
            if ($previous == 0) {
                return $current > 0 ? ['pct' => 100, 'dir' => 'up', 'is_good' => ! $lowerIsBetter] : ['pct' => 0, 'dir' => 'flat', 'is_good' => true];
            }

            $diff = $current - $previous;
            $pct = abs($diff / $previous * 100);
            $dir = $diff > 0 ? 'up' : ($diff < 0 ? 'down' : 'flat');

            $isGood = true;
            if ($dir === 'up') {
                $isGood = ! $lowerIsBetter;
            }
            if ($dir === 'down') {
                $isGood = $lowerIsBetter;
            }

            return ['pct' => round($pct, 1), 'dir' => $dir, 'is_good' => $isGood];
        };

        $trends = [
            'sku' => $calculateTrend($totalSKU, $prevTotalSKU),
            'value' => $calculateTrend($totalStockValue, $prevTotalStockValue),
            'critical' => $calculateTrend($criticalLow, $prevCriticalLow, true),
            'slow' => $calculateTrend($slowMovingCount, $prevSlowMovingCount, true),
        ];

        // ══════════════════════════════════════════════════════
        // 2. STOCK BY PRINCIPAL
        // ══════════════════════════════════════════════════════
        $stockByPrincipal = SalesPerStock::where('period', $period)
            ->when($selectedWarehouse && $selectedWarehouse !== 'all', fn ($q) => $q->where('warehouse_name', $selectedWarehouse))
            ->select(
                'principal_name',
                DB::raw('COUNT(*) as sku_count'),
                DB::raw('SUM(on_hand_base) as total_on_hand'),
                DB::raw('SUM(stock_value_on_hand) as total_value'),
                DB::raw('AVG(CASE WHEN swc > 0 THEN swc END) as avg_swc'),
                DB::raw('SUM(CASE WHEN swc <= 2 AND swc > 0 THEN 1 ELSE 0 END) as critical_count'),
                DB::raw('SUM(CASE WHEN (swc > 8 OR swc = 0) AND stock_value_on_hand > 0 THEN 1 ELSE 0 END) as slow_count')
            )
            ->groupBy('principal_name')
            ->orderByDesc('total_value')
            ->get();

        // ══════════════════════════════════════════════════════
        // 3. CRITICAL STOCK (SWC <= 2 weeks) — hampir habis
        // ══════════════════════════════════════════════════════
        $criticalStockItems = (clone $baseQuery)
            ->where('swc', '<=', 2)->where('swc', '>', 0)
            ->orderBy('swc')
            ->limit(20)
            ->get();

        // ══════════════════════════════════════════════════════
        // 4. SLOW MOVING / MODAL TERTAHAN (SWC > 8 weeks or SWC = 0)
        // ══════════════════════════════════════════════════════
        $slowMovingItems = (clone $baseQuery)
            ->where(function ($q) {
                $q->where('swc', '>', 8)->orWhere('swc', 0);
            })
            ->where('stock_value_on_hand', '>', 0)
            ->orderByDesc('stock_value_on_hand')
            ->limit(20)
            ->get();

        // ══════════════════════════════════════════════════════
        // 6. SWC DISTRIBUTION (for chart)
        // ══════════════════════════════════════════════════════
        $swcDistribution = [
            '0 (No Sales)' => (clone $baseQuery)->where('swc', 0)->count(),
            '1-2 Minggu' => (clone $baseQuery)->whereBetween('swc', [1, 2])->count(),
            '3-4 Minggu' => (clone $baseQuery)->whereBetween('swc', [3, 4])->count(),
            '5-8 Minggu' => (clone $baseQuery)->whereBetween('swc', [5, 8])->count(),
            '9-12 Minggu' => (clone $baseQuery)->whereBetween('swc', [9, 12])->count(),
            '> 12 Minggu' => (clone $baseQuery)->where('swc', '>', 12)->count(),
        ];

        // ══════════════════════════════════════════════════════
        // 7. PARETO CAPITAL ALLOCATION (80/20 Rule)
        // ══════════════════════════════════════════════════════
        $fastMovingValue = (clone $baseQuery)->where('swc', '>', 0)->where('swc', '<=', 8)->sum('stock_value_on_hand');
        $slowMovingValue = (clone $baseQuery)->where(function ($q) {
            $q->where('swc', '>', 8)->orWhere('swc', 0);
        })->sum('stock_value_on_hand');

        $fastPct = $totalStockValue > 0 ? ($fastMovingValue / $totalStockValue) * 100 : 0;

        $paretoCapital = [
            'fast_value' => $fastMovingValue,
            'slow_value' => $slowMovingValue,
            'fast_pct' => $fastPct,
            'is_healthy' => $fastPct >= 80,
        ];
        // ══════════════════════════════════════════════════════
        // 8. TOP FAST MOVING (Highest WAS)
        // ══════════════════════════════════════════════════════
        $fastMovingItems = (clone $baseQuery)
            ->where('was', '>', 0)
            ->orderByDesc('was')
            ->limit(20)
            ->get();

        // Dropdowns
        $principalList = SalesPerStock::where('period', $period)
            ->select('principal_name')->distinct()->orderBy('principal_name')->pluck('principal_name');

        $warehouseList = SalesPerStock::where('period', $period)
            ->select('warehouse_name')->distinct()->orderBy('warehouse_name')->pluck('warehouse_name');

        // Warehouse label for header
        $warehouseLabel = ($selectedWarehouse && $selectedWarehouse !== 'all')
            ? $selectedWarehouse
            : 'Semua Gudang';

        return view('sales-per.stock-dashboard', compact(
            'periods', 'period', 'periodLabel', 'selectedPrincipal', 'principalList',
            'selectedWarehouse', 'warehouseList', 'warehouseLabel',
            'totalSKU', 'totalStockValue', 'totalOnHand', 'avgSWC',
            'criticalLow', 'slowMovingCount',
            'stockByPrincipal', 'criticalStockItems', 'slowMovingItems', 'fastMovingItems',
            'swcDistribution', 'paretoCapital', 'trends', 'hasPrevPeriod'
        ) + ['hasData' => true]);
    }

    public function loadTabKritis(Request $request)
    {
        $period = $request->query('period', date('Y-m'));
        $selectedPrincipal = $request->query('principal', 'all');
        $selectedWarehouse = $request->query('warehouse', 'all');
        $search = $request->query('search', '');

        $query = SalesPerStock::where('period', $period)
            ->where('swc', '<=', 2)->where('swc', '>', 0);

        if ($selectedPrincipal !== 'all') {
            $query->where('principal_name', $selectedPrincipal);
        }
        if ($selectedWarehouse !== 'all') {
            $query->where('warehouse_name', $selectedWarehouse);
        }
        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('item_name', 'like', "%{$search}%")
                    ->orWhere('item_no', 'like', "%{$search}%");
            });
        }

        $items = $query->orderBy('swc', 'asc')->paginate(20);

        return view('sales-per.partials.kritis-table', compact('items'))->render();
    }

    public function loadTabTertahan(Request $request)
    {
        if (auth()->user()->isSalesman()) {
            abort(403, 'Akses ditolak. Anda tidak diperkenankan mengakses halaman ini.');
        }
        $period = $request->query('period', date('Y-m'));
        $selectedPrincipal = $request->query('principal', 'all');
        $selectedWarehouse = $request->query('warehouse', 'all');
        $search = $request->query('search', '');

        $query = SalesPerStock::where('period', $period)
            ->where(function ($q) {
                $q->where('swc', '>', 8)->orWhere('swc', 0);
            })->where('stock_value_on_hand', '>', 0);

        if ($selectedPrincipal !== 'all') {
            $query->where('principal_name', $selectedPrincipal);
        }
        if ($selectedWarehouse !== 'all') {
            $query->where('warehouse_name', $selectedWarehouse);
        }
        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('item_name', 'like', "%{$search}%")
                    ->orWhere('item_no', 'like', "%{$search}%");
            });
        }

        $items = $query->orderByDesc('stock_value_on_hand')->paginate(20);

        return view('sales-per.partials.tertahan-table', compact('items'))->render();
    }

    public function loadTabSemua(Request $request)
    {
        $period = $request->query('period', date('Y-m'));
        $selectedPrincipal = $request->query('principal', 'all');
        $selectedWarehouse = $request->query('warehouse', 'all');
        $search = $request->query('search', '');

        $query = SalesPerStock::where('period', $period);

        if ($selectedPrincipal !== 'all') {
            $query->where('principal_name', $selectedPrincipal);
        }
        if ($selectedWarehouse !== 'all') {
            $query->where('warehouse_name', $selectedWarehouse);
        }
        if (! empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('item_name', 'like', "%{$search}%")
                    ->orWhere('item_no', 'like', "%{$search}%");
            });
        }

        $items = $query->orderByDesc('stock_value_on_hand')->paginate(50);

        return view('sales-per.partials.semua-table', compact('items'))->render();
    }
}
