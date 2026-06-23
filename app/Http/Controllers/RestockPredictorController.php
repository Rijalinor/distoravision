<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\CsvExportable;
use App\Models\Principal;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class RestockPredictorController extends Controller implements HasMiddleware
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

    public function restockPredictor(Request $request)
    {
        $principals = Principal::orderBy('name')->pluck('name', 'id');
        $selectedPrincipal = $request->get('principal_id', 'all');
        $search = $request->get('search');

        $targetDate = Carbon::now();
        $sixMonthsAgo = $targetDate->copy()->subMonths(6)->format('Y-m-d');

        $principalFilter = '';
        $bindings = [$sixMonthsAgo];

        if ($selectedPrincipal !== 'all') {
            $principalFilter = ' AND pr.principal_id = ? ';
            $bindings[] = $selectedPrincipal;
        }

        // MySQL 8.0+ Window Function to calculate average days between purchases per outlet per product
        $sql = "
            SELECT 
                o.name as outlet_name,
                pr.name as product_name,
                p.name as principal_name,
                agg.avg_cycle_days,
                agg.avg_qty_per_order,
                agg.last_purchase_date,
                DATE_ADD(agg.last_purchase_date, INTERVAL ROUND(agg.avg_cycle_days) DAY) as next_purchase_date
            FROM (
                SELECT 
                    outlet_id, 
                    product_id, 
                    AVG(DATEDIFF(so_date, prev_date)) as avg_cycle_days,
                    AVG(daily_qty) as avg_qty_per_order,
                    MAX(so_date) as last_purchase_date,
                    COUNT(so_date) as purchase_count
                FROM (
                    SELECT 
                        t.outlet_id, 
                        t.product_id, 
                        t.so_date,
                        SUM(t.qty_base) as daily_qty,
                        LAG(t.so_date) OVER (PARTITION BY t.outlet_id, t.product_id ORDER BY t.so_date) as prev_date
                    FROM transactions t
                    JOIN products pr ON t.product_id = pr.id
                    WHERE t.so_date >= ? AND t.type = 'I'
                    $principalFilter
                    GROUP BY t.outlet_id, t.product_id, t.so_date
                ) AS sub
                WHERE prev_date IS NOT NULL AND so_date != prev_date
                GROUP BY outlet_id, product_id
                HAVING purchase_count > 1 AND avg_cycle_days > 5
            ) as agg
            JOIN outlets o ON agg.outlet_id = o.id
            JOIN products pr ON agg.product_id = pr.id
            JOIN principals p ON pr.principal_id = p.id
        ";

        if (! empty($search)) {
            $sql .= ' WHERE o.name LIKE ? OR pr.name LIKE ?';
            $bindings[] = "%{$search}%";
            $bindings[] = "%{$search}%";
        }

        $sql .= ' ORDER BY next_purchase_date ASC LIMIT 3000';

        $results = DB::select($sql, $bindings);

        $predictions = [];
        $groupedOutlets = [];

        foreach ($results as $row) {
            $nextDate = Carbon::parse($row->next_purchase_date);
            $diffDays = (int) $targetDate->diffInDays($nextDate, false);

            $row->diff_days = $diffDays;

            $predictions[] = $row;

            if (! isset($groupedOutlets[$row->outlet_name])) {
                $groupedOutlets[$row->outlet_name] = [
                    'outlet_name' => $row->outlet_name,
                    'items' => [],
                ];
            }

            $groupedOutlets[$row->outlet_name]['items'][] = $row;
        }

        $groupedOutlets = array_values($groupedOutlets);
        usort($groupedOutlets, function ($a, $b) {
            return count($b['items']) <=> count($a['items']); // Sort by total items
        });

        foreach ($groupedOutlets as &$g) {
            usort($g['items'], function ($a, $b) {
                return $a->avg_cycle_days <=> $b->avg_cycle_days; // Sort by cycle length
            });
        }
        unset($g);

        $totalAnalyzed = count($results);
        $totalOutlets = count($groupedOutlets);

        // --- CSV EXPORT ---
        if ($request->get('export') === 'csv') {
            $rows = array_map(function ($p) {
                return [
                    $p->outlet_name,
                    $p->product_name,
                    $p->principal_name,
                    round($p->avg_cycle_days),
                    round($p->avg_qty_per_order),
                    $p->last_purchase_date,
                    $p->next_purchase_date,
                ];
            }, $predictions);

            return $this->streamCsv(
                "PolaSiklusToko_{$targetDate->format('Ymd')}.csv",
                ['Toko', 'Produk', 'Principal', 'Siklus Rata-Rata (Hari)', 'Rata-Rata Volume Beli', 'Terakhir Beli', 'Estimasi Pesan Berikutnya'],
                $rows
            );
        }

        $perPage = 30;
        $page = Paginator::resolveCurrentPage() ?: 1;
        $groupedCollection = collect($groupedOutlets);
        $paginatedPredictions = new LengthAwarePaginator(
            $groupedCollection->forPage($page, $perPage),
            $groupedCollection->count(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath(), 'query' => $request->query()]
        );

        $aiNarrative = "🔍 Fakta: AI memonitor {$totalAnalyzed} pola beli-ulang dari {$totalOutlets} toko.\n💡 Insight: Tampilan ini murni menampilkan pola perilaku belanja tiap toko (Siklus Hari & Rata-rata Volume). Gunakan data ini untuk memahami karakter pesanan outlet.";

        return view('analytics.restock-predictor', compact(
            'paginatedPredictions', 'principals', 'selectedPrincipal', 'search',
            'totalAnalyzed', 'totalOutlets', 'aiNarrative'
        ));
    }
}
