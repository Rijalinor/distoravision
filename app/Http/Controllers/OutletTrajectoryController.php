<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\CsvExportable;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class OutletTrajectoryController extends Controller implements HasMiddleware
{
    use CsvExportable;

    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware(function ($request, $next) {
                abort_if(auth()->check() && auth()->user()->isSalesman(), 403, 'Akses ditolak. Salesman tidak dapat melihat menu Analisis ini.');

                return $next($request);
            }),
        ];
    }

    public function outletTrajectory(Request $request)
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

        // Fetch monthly sales per outlet for the 6-month window
        $rawQuery = Transaction::query()
            ->whereIn('transactions.period', $periodRange)
            ->join('outlets', 'transactions.outlet_id', '=', 'outlets.id');

        // Apply principal filter if present
        if ($request->has('principal_id') && ! empty($request->get('principal_id')) && $request->get('principal_id') !== 'all') {
            $rawQuery->whereHas('product', function ($q) use ($request) {
                $q->where('principal_id', $request->get('principal_id'));
            });
        }

        $monthlySales = $rawQuery->select(
            'transactions.outlet_id',
            'outlets.name as outlet_name',
            'outlets.city',
            'outlets.code as outlet_code',
            'transactions.period',
            DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.taxed_amt WHEN transactions.type = "R" THEN -ABS(transactions.taxed_amt) ELSE 0 END) as net_sales')
        )
            ->groupBy('transactions.outlet_id', 'outlets.name', 'outlets.city', 'outlets.code', 'transactions.period')
            ->get()
            ->groupBy('outlet_id');

        $trajectories = [];
        $segments = ['Growing' => 0, 'Stable' => 0, 'Declining' => 0, 'New' => 0, 'Dead' => 0];

        foreach ($monthlySales as $outletId => $monthlyData) {
            $outlet = $monthlyData->first();
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
                'outlet_id' => $outletId,
                'outlet_name' => $outlet->outlet_name,
                'outlet_code' => $outlet->outlet_code,
                'city' => $outlet->city,
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

        $totalOutlets = array_sum($segments);

        // --- DISTORA AI NARRATIVE GENERATOR ---
        $decliningValue = collect($trajectories)->where('classification', 'Declining')->sum('total_sales');
        $aiNarrative = "🔍 Fakta: Dari {$totalOutlets} outlet yang terdata, ".
            "{$segments['Growing']} outlet sedang TUMBUH 📈, ".
            "{$segments['Stable']} STABIL ➡️, ".
            "{$segments['Declining']} MENURUN 📉, ".
            "{$segments['New']} BARU 🆕, dan ".
            "{$segments['Dead']} MATI 💀.\n".
            ($segments['Declining'] > 0
                ? '⚠️ Perhatian: '.$segments['Declining'].' outlet sedang dalam tren PENURUNAN dengan total kontribusi Rp '.number_format($decliningValue, 0, ',', '.').". Ini adalah toko yang BELUM mati tapi sedang menuju ke sana — selamatkan sebelum terlambat!\n"
                : "✅ Semua outlet dalam kondisi sehat, tidak ada yang menunjukkan tren penurunan.\n").
            '💡 Saran: Prioritaskan kunjungan ke outlet Declining. Tanya langsung: kenapa order berkurang? Kompetitor masuk? Stok kita tidak cocok? Toko sepi pembeli?';

        // --- CSV EXPORT ---
        if ($request->get('export') === 'csv') {
            $headers = ['Outlet', 'Kode', 'Kota', 'Klasifikasi', 'Slope %', 'Bulan Aktif'];
            $salesHeaders = array_map(fn ($p) => 'Sales '.Carbon::parse($p.'-01')->format('M Y'), $periodRange);
            $headers = array_merge($headers, $salesHeaders, ['Sales Terakhir', 'Rata-rata', 'Total 6 Bln']);

            $rows = array_map(function ($t) use ($periodRange) {
                $row = [
                    $t->outlet_name,
                    $t->outlet_code,
                    $t->city,
                    $t->classification,
                    $t->slope_pct,
                    $t->active_months,
                ];
                $salesData = array_map(fn ($p) => $t->series[$p] ?? 0, $periodRange);

                return array_merge($row, $salesData, [$t->latest_sales, $t->avg_sales, $t->total_sales]);
            }, $trajectories);

            return $this->streamCsv(
                "OutletTrajectory_{$period}.csv",
                $headers,
                $rows
            );
        }

        return view('analytics.outlet-trajectory', compact(
            'period', 'periods', 'trajectories', 'segments', 'totalOutlets',
            'segment', 'periodRange', 'aiNarrative'
        ));
    }
}
