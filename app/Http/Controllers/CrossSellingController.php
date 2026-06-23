<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\CsvExportable;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CrossSellingController extends Controller implements HasMiddleware
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

    public function crossSelling(Request $request)
    {
        $period = $request->get('period', Transaction::max('period') ?? date('Y-m'));
        $periods = Transaction::select('period')->distinct()->orderByDesc('period')->pluck('period');

        $pairs = Transaction::withFilters(request())
            ->invoices()
            ->join('products', 'transactions.product_id', '=', 'products.id')
            ->select('transactions.outlet_id', 'products.name as product_name')
            ->distinct()
            ->get();

        $baskets = $pairs->groupBy('outlet_id');

        $matrix = [];
        $itemBasketCounts = [];

        foreach ($baskets as $outletId => $itemsInBasket) {
            $items = $itemsInBasket->pluck('product_name')->toArray();
            foreach ($items as $p1) {
                if (! isset($itemBasketCounts[$p1])) {
                    $itemBasketCounts[$p1] = 0;
                }
                $itemBasketCounts[$p1]++;

                if (! isset($matrix[$p1])) {
                    $matrix[$p1] = [];
                }

                foreach ($items as $p2) {
                    if ($p1 != $p2) {
                        if (! isset($matrix[$p1][$p2])) {
                            $matrix[$p1][$p2] = 0;
                        }
                        $matrix[$p1][$p2]++;
                    }
                }
            }
        }

        $affinities = [];
        foreach ($matrix as $source => $targets) {
            // Only care if the source product has a meaningful amount of buyers (e.g., > 2)
            if ($itemBasketCounts[$source] < 3) {
                continue;
            }

            arsort($targets);
            $topAssociated = array_slice($targets, 0, 5, true);
            $affinities[] = [
                'item' => $source,
                'total_baskets' => $itemBasketCounts[$source],
                'associations' => $topAssociated,
            ];
        }

        // Sort heavily demanded products first
        usort($affinities, function ($a, $b) {
            return $b['total_baskets'] <=> $a['total_baskets'];
        });

        // --- DISTORA AI NARRATIVE GENERATOR ---
        $topAsso = count($affinities) > 0 ? $affinities[0] : null;
        $aiNarrative = '🔍 Fakta: Mesin Basket Analysis mendeteksi afinitas keranjang. '.($topAsso ? "Produk {$topAsso['item']} paling sering diborong bersaman dengan item lain di {$topAsso['total_baskets']} toko berbeda." : 'Belum ada pola keranjang terbentuk yang signifikan.')."\n".
                       '💡 Saran Eksekusi: Jadikan produk teratas ini sebagai "Lokomotif". Gabungkan/bundling secara paksa produk Dead-Stock (Gerbong) bersama produk Lokomotif ini untuk mempercepat penetrasi cuci gudang!';

        $affinities = array_slice($affinities, 0, 100);

        // --- CSV EXPORT ---
        if ($request->get('export') === 'csv') {
            $rows = [];
            foreach ($affinities as $a) {
                foreach ($a['associations'] as $target => $count) {
                    $pct = $a['total_baskets'] > 0 ? round(($count / $a['total_baskets']) * 100, 1) : 0;
                    $rows[] = [$a['item'], $a['total_baskets'], $target, $count, $pct];
                }
            }

            return $this->streamCsv(
                "CrossSelling_{$period}.csv",
                ['Produk Utama', 'Total Toko', 'Produk Terkait', 'Jumlah Toko', 'Afinitas %'],
                $rows
            );
        }

        return view('analytics.cross-selling', compact('period', 'periods', 'affinities', 'aiNarrative'));
    }
}
