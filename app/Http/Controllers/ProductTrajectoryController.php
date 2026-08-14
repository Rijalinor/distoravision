<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\CsvExportable;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProductTrajectoryController extends Controller
{
    use CsvExportable;

    public function index(Request $request)
    {
        $period = $request->get('end_period', $request->get('period', Transaction::max('period') ?? date('Y-m')));
        $startPeriod = $request->get('start_period', $period);
        $periods = Transaction::select('period')->distinct()->orderByDesc('period')->pluck('period');

        $startDate = Carbon::parse($startPeriod.'-01');
        $endDate = Carbon::parse($period.'-01');
        if ($startDate->gt($endDate)) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        $lookbackMonths = min($startDate->diffInMonths($endDate) + 1, 6);
        $startDate = $endDate->copy()->subMonths($lookbackMonths - 1);
        $periodRange = [];
        for ($i = 0; $i < $lookbackMonths; $i++) {
            $periodRange[] = $startDate->copy()->addMonths($i)->format('Y-m');
        }

        $segment = $request->get('segment', 'all');
        $perPage = min(max((int) $request->get('per_page', 25), 1), 100);

        $rawQuery = Transaction::query()
            ->whereIn('transactions.period', $periodRange)
            ->join('products', 'transactions.product_id', '=', 'products.id')
            ->join('principals', 'products.principal_id', '=', 'principals.id');

        if ($request->filled('principal_id') && $request->get('principal_id') !== 'all') {
            $rawQuery->where('products.principal_id', $request->get('principal_id'));
        }

        $monthlySales = $rawQuery->select(
            'transactions.product_id',
            'products.name as product_name',
            'products.item_no as product_code',
            'principals.name as principal_name',
            'transactions.period',
            DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.taxed_amt ELSE 0 END) as sales_amount')
        )
            ->groupBy('transactions.product_id', 'products.name', 'products.item_no', 'principals.name', 'transactions.period')
            ->get()
            ->groupBy('product_id');

        $trajectories = [];
        $segments = ['Growing' => 0, 'Stable' => 0, 'Declining' => 0, 'New' => 0, 'Dead' => 0];

        foreach ($monthlySales as $productId => $monthlyData) {
            $product = $monthlyData->first();
            $activeMonths = $monthlyData->pluck('sales_amount', 'period');
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
            $slopePct = $avgSales > 0 ? ($slope / $avgSales) * 100 : 0;

            if ($monthCount <= 1 && $latestSales > 0) {
                $classification = 'New';
                $icon = 'NEW';
            } elseif ($monthCount <= 1 && $latestSales <= 0) {
                $classification = 'Dead';
                $icon = 'DEAD';
            } elseif ($latestSales <= 0 && $prevSales <= 0) {
                $classification = 'Dead';
                $icon = 'DEAD';
            } elseif ($slopePct > 10) {
                $classification = 'Growing';
                $icon = 'UP';
            } elseif ($slopePct < -10) {
                $classification = 'Declining';
                $icon = 'DOWN';
            } else {
                $classification = 'Stable';
                $icon = 'OK';
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

        if ($segment !== 'all') {
            $trajectories = array_values(array_filter($trajectories, fn ($t) => $t->classification === $segment));
        }

        usort($trajectories, function ($a, $b) {
            $order = ['Declining' => 0, 'Dead' => 1, 'New' => 2, 'Stable' => 3, 'Growing' => 4];
            $classCompare = ($order[$a->classification] ?? 5) <=> ($order[$b->classification] ?? 5);

            return $classCompare !== 0 ? $classCompare : $b->total_sales <=> $a->total_sales;
        });

        $totalProducts = array_sum($segments);
        $rangeLabel = $lookbackMonths.' bulan';
        $decliningValue = collect($trajectories)->where('classification', 'Declining')->sum('total_sales');
        $growingCount = $segments['Growing'];

        $aiNarrative = "Fakta: Dari {$totalProducts} SKU aktif dalam {$rangeLabel}, "
            ."{$segments['Growing']} SKU sedang TUMBUH, "
            ."{$segments['Stable']} STABIL, "
            ."{$segments['Declining']} MENURUN, "
            ."{$segments['New']} BARU, dan "
            ."{$segments['Dead']} MATI.\n"
            .($segments['Declining'] > 0
                ? 'Perhatian: '.$segments['Declining'].' SKU menunjukkan tren PENURUNAN dengan akumulasi penjualan Rp '.number_format($decliningValue, 0, ',', '.').". Awas risiko Dead-Stock di gudang.\n"
                : "Katalog produk stabil, tidak ada ancaman Dead-Stock yang signifikan.\n")
            .'Saran: hentikan/kurangi PO untuk produk Declining, dan amankan stok untuk '.$growingCount.' produk Growing agar tidak Stockout.';

        if ($request->get('export') === 'csv') {
            $headers = ['Produk', 'Kode Item', 'Principal', 'Klasifikasi', 'Slope %', 'Bulan Laku'];
            $salesHeaders = array_map(fn ($p) => 'Sales '.Carbon::parse($p.'-01')->format('M Y'), $periodRange);
            $headers = array_merge($headers, $salesHeaders, ['Sales Terakhir', 'Rata-rata', "Total {$rangeLabel}"]);

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

            return $this->streamCsv("ProductTrajectory_{$period}.csv", $headers, $rows);
        }

        $currentPage = LengthAwarePaginator::resolveCurrentPage();
        $paginatedTrajectories = new LengthAwarePaginator(
            array_slice($trajectories, ($currentPage - 1) * $perPage, $perPage),
            count($trajectories),
            $perPage,
            $currentPage,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('analytics.product-trajectory', compact(
            'period',
            'periods',
            'paginatedTrajectories',
            'segments',
            'totalProducts',
            'segment',
            'periodRange',
            'aiNarrative',
            'perPage',
            'lookbackMonths',
            'rangeLabel'
        ));
    }
}
