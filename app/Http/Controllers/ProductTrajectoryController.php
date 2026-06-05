<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\CsvExportable;
use App\Models\Product;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductTrajectoryController extends Controller
{
    use CsvExportable;

    /**
     * Product Growth Trajectory Analysis
     * Classifies each product as Growing 📈, Stable ➡️, Declining 📉, New 🆕, or Dead 💀
     * based on 6-month sales trend using linear regression slope.
     */
    public function index(Request $request)
    {
        $period = $request->get('period', Transaction::max('period') ?? date('Y-m'));
        $periods = Transaction::select('period')->distinct()->orderByDesc('period')->pluck('period');

        // Look back 6 months from the selected period
        $endDate = Carbon::parse($period.'-01');
        $lookbackMonths = 6;
        $startDate = $endDate->copy()->subMonths($lookbackMonths - 1);
        $periodRange = [];
        for ($i = 0; $i < $lookbackMonths; $i++) {
            $periodRange[] = $startDate->copy()->addMonths($i)->format('Y-m');
        }

        // Filter segment
        $segment = $request->get('segment', 'all');

        // Fetch monthly sales per product for the 6-month window
        $rawQuery = Transaction::query()
            ->whereIn('transactions.period', $periodRange)
            ->join('products', 'transactions.product_id', '=', 'products.id')
            ->join('principals', 'products.principal_id', '=', 'principals.id');

        // Apply principal filter if present
        if ($request->has('principal_id') && ! empty($request->get('principal_id')) && $request->get('principal_id') !== 'all') {
            $rawQuery->where('products.principal_id', $request->get('principal_id'));
        }

        $monthlySales = $rawQuery->select(
            'transactions.product_id',
            'products.name as product_name',
            'products.item_no as product_code',
            'principals.name as principal_name',
            'transactions.period',
            DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.taxed_amt WHEN transactions.type = "R" THEN -ABS(transactions.taxed_amt) ELSE 0 END) as net_sales')
        )
            ->groupBy('transactions.product_id', 'products.name', 'products.item_no', 'principals.name', 'transactions.period')
            ->get()
            ->groupBy('product_id');

        $trajectories = [];
        $segments = ['Growing' => 0, 'Stable' => 0, 'Declining' => 0, 'New' => 0, 'Dead' => 0];

        foreach ($monthlySales as $productId => $monthlyData) {
            $product = $monthlyData->first();
            $activeMonths = $monthlyData->pluck('net_sales', 'period');

            // Build 6-month series (fill zeroes for missing months)
            $series = [];
            foreach ($periodRange as $p) {
                $series[$p] = (float) ($activeMonths[$p] ?? 0);
            }

            $values = array_values($series);
            $n = count($values);
            $monthCount = count(array_filter($values, fn ($v) => $v > 0));
            $totalSales = array_sum($values);
            $latestSales = end($values);
            $prevSales = $values[$n - 2] ?? 0;

            // Calculate linear regression slope to determine trend direction
            $sumX = 0;
            $sumY = 0;
            $sumXY = 0;
            $sumX2 = 0;
            for ($i = 0; $i < $n; $i++) {
                $sumX += $i;
                $sumY += $values[$i];
                $sumXY += $i * $values[$i];
                $sumX2 += $i * $i;
            }
            $denominator = ($n * $sumX2) - ($sumX * $sumX);
            $slope = $denominator > 0 ? (($n * $sumXY) - ($sumX * $sumY)) / $denominator : 0;
            $avgSales = $totalSales / max($monthCount, 1);

            // Normalize slope as percentage of average sales
            $slopePct = $avgSales > 0 ? ($slope / $avgSales) * 100 : 0;

            // Classify
            if ($monthCount <= 1 && $latestSales > 0) {
                $classification = 'New';
                $icon = '🆕';
            } elseif ($monthCount <= 1 && $latestSales <= 0) {
                $classification = 'Dead';
                $icon = '💀';
            } elseif ($latestSales <= 0 && $prevSales <= 0) {
                $classification = 'Dead';
                $icon = '💀';
            } elseif ($slopePct > 10) {
                $classification = 'Growing';
                $icon = '📈';
            } elseif ($slopePct < -10) {
                $classification = 'Declining';
                $icon = '📉';
            } else {
                $classification = 'Stable';
                $icon = '➡️';
            }

            $segments[$classification]++;

            $trajectories[] = (object) [
                'product_id' => $productId,
                'product_name' => $product->product_name,
                'product_code' => $product->product_code,
                'principal_name' => str_replace('PT. ', '', $product->principal_name),
                'classification' => $classification,
                'icon' => $icon,
                'total_sales' => $totalSales,
                'latest_sales' => $latestSales,
                'avg_sales' => $avgSales,
                'active_months' => $monthCount,
                'slope_pct' => round($slopePct, 1),
                'series' => $series,
            ];
        }

        // Filter by segment
        if ($segment !== 'all') {
            $trajectories = array_values(array_filter($trajectories, fn ($t) => $t->classification === $segment));
        }

        // Sort: Declining first (most urgent), then by total sales
        usort($trajectories, function ($a, $b) {
            $order = ['Declining' => 0, 'Dead' => 1, 'New' => 2, 'Stable' => 3, 'Growing' => 4];
            $classCompare = ($order[$a->classification] ?? 5) <=> ($order[$b->classification] ?? 5);
            if ($classCompare !== 0) {
                return $classCompare;
            }

            return $b->total_sales <=> $a->total_sales;
        });

        $totalProducts = array_sum($segments);

        // --- DISTORA AI NARRATIVE GENERATOR ---
        $decliningValue = collect($trajectories)->where('classification', 'Declining')->sum('total_sales');
        $growingCount = $segments['Growing'];

        $aiNarrative = "🔍 Fakta: Dari {$totalProducts} SKU aktif, ".
            "{$segments['Growing']} SKU sedang TUMBUH 📈, ".
            "{$segments['Stable']} STABIL ➡️, ".
            "{$segments['Declining']} MENURUN 📉, ".
            "{$segments['New']} BARU 🆕, dan ".
            "{$segments['Dead']} MATI 💀.\n".
            ($segments['Declining'] > 0
                ? '⚠️ Perhatian: '.$segments['Declining'].' SKU menunjukkan tren PENURUNAN dengan akumulasi penjualan Rp '.number_format($decliningValue, 0, ',', '.').". Awas resiko Dead-Stock di gudang!\n"
                : "✅ Katalog produk stabil, tidak ada ancaman Dead-Stock yang signifikan.\n").
            '💡 Saran: Segera hentikan PO pembelian ke Pabrik untuk produk yang Declining, dan fokus amankan stok untuk '.$growingCount.' produk yang sedang Growing agar tidak terjadi Stockout.';

        // --- CSV EXPORT ---
        if ($request->get('export') === 'csv') {
            $headers = ['Produk', 'Kode Item', 'Principal', 'Klasifikasi', 'Slope %', 'Bulan Laku'];
            $salesHeaders = array_map(fn ($p) => 'Sales '.Carbon::parse($p.'-01')->format('M Y'), $periodRange);
            $headers = array_merge($headers, $salesHeaders, ['Sales Terakhir', 'Rata-rata', 'Total 6 Bln']);

            $rows = array_map(function ($t) use ($periodRange) {
                $row = [
                    $t->product_name,
                    $t->product_code,
                    $t->principal_name,
                    $t->classification,
                    $t->slope_pct,
                    $t->active_months,
                ];
                $salesData = array_map(fn ($p) => $t->series[$p] ?? 0, $periodRange);

                return array_merge($row, $salesData, [$t->latest_sales, $t->avg_sales, $t->total_sales]);
            }, $trajectories);

            return $this->streamCsv(
                "ProductTrajectory_{$period}.csv",
                $headers,
                $rows
            );
        }

        return view('analytics.product-trajectory', compact(
            'period', 'periods', 'trajectories', 'segments', 'totalProducts',
            'segment', 'periodRange', 'aiNarrative'
        ));
    }
}
