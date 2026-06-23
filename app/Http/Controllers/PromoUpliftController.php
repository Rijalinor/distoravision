<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\CsvExportable;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class PromoUpliftController extends Controller implements HasMiddleware
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

    public function promoUplift(Request $request)
    {
        $period = $request->get('period', Transaction::max('period') ?? date('Y-m'));
        $periods = Transaction::select('period')->distinct()->orderByDesc('period')->pluck('period');

        // Promo Uplift needs multi-month data to compare baseline vs promo months.
        // If no explicit period filter is set, auto-inject the full available range.
        if (! $request->has('start_period') && ! $request->has('end_period') && $periods->isNotEmpty()) {
            $request->merge([
                'start_period' => $periods->last(),
                'end_period' => $periods->first(),
            ]);
        }

        // Pull monthly data per product with filters applied
        $data = Transaction::withFilters($request)
            ->invoices()
            ->where('transactions.gross', '>', 0)
            ->join('products', 'transactions.product_id', '=', 'products.id')
            ->join('principals', 'products.principal_id', '=', 'principals.id')
            ->select(
                'transactions.product_id',
                'products.name as product_name',
                'principals.name as principal_name',
                'transactions.period',
                DB::raw('SUM(transactions.qty_base) as total_qty'),
                DB::raw('SUM(transactions.gross) as total_gross'),
                DB::raw('SUM(transactions.disc_total) as total_discount'),
                DB::raw('SUM(transactions.cogs) as total_cogs'),
                DB::raw('ROUND((SUM(transactions.disc_total) / SUM(transactions.gross)) * 100, 2) as discount_pct')
            )
            ->groupBy('transactions.product_id', 'products.name', 'principals.name', 'transactions.period')
            ->havingRaw('SUM(transactions.qty_base) > 0')
            ->get();

        // Group by product
        $grouped = [];
        foreach ($data as $row) {
            if (! isset($grouped[$row->product_id])) {
                $grouped[$row->product_id] = [
                    'name' => $row->product_name,
                    'principal' => str_replace('PT. ', '', $row->principal_name),
                    'periods' => [],
                ];
            }
            $grouped[$row->product_id]['periods'][$row->period] = $row;
        }

        $results = [];
        $successCount = 0;
        $failCount = 0;
        $totalSubsidy = 0;
        $anomalyCount = 0;

        foreach ($grouped as $pid => $prod) {
            $periodData = $prod['periods'];
            if (count($periodData) < 2) {
                continue;
            }

            // Sort periods by discount_pct to find baseline (lowest) and promo (highest)
            $sorted = collect($periodData)->sortBy('discount_pct')->values();
            $baseline = $sorted->first();
            $promo = $sorted->last();

            // Skip if discount difference is too small to be meaningful
            if (($promo->discount_pct - $baseline->discount_pct) < 3) {
                continue;
            }
            // Skip very low volume noise
            if ($promo->total_qty < 10 || $baseline->total_qty < 10) {
                continue;
            }

            $upliftQty = $promo->total_qty - $baseline->total_qty;
            $upliftPct = $baseline->total_qty > 0 ? (($promo->total_qty - $baseline->total_qty) / $baseline->total_qty) * 100 : 0;

            $profitNormal = ($baseline->total_gross - $baseline->total_discount) - $baseline->total_cogs;
            $profitPromo = ($promo->total_gross - $promo->total_discount) - $promo->total_cogs;
            $profitDiff = $profitPromo - $profitNormal;

            $isSuccess = $profitDiff > 0;
            if ($isSuccess) {
                $successCount++;
            } else {
                $failCount++;
            }
            $totalSubsidy += $promo->total_discount;

            // --- ANOMALY DETECTION ---
            $anomalyFlags = [];

            // 1. STOCKOUT: Discount goes UP but volume drops >= 30%
            if ($promo->discount_pct > $baseline->discount_pct && $upliftPct <= -30) {
                $anomalyFlags[] = 'STOCKOUT';
                $anomalyCount++;
            }

            // 2. FORWARD BUYING: Check if T-1 (month before promo) had qty spike without discount increase
            $promoMonth = Carbon::parse($promo->period.'-01');
            $t1Period = $promoMonth->copy()->subMonth()->format('Y-m');
            if (isset($periodData[$t1Period])) {
                $t1 = $periodData[$t1Period];
                $t1QtyChange = $baseline->total_qty > 0 ? (($t1->total_qty - $baseline->total_qty) / $baseline->total_qty) * 100 : 0;
                $t1DiscDiff = $t1->discount_pct - $baseline->discount_pct;
                // If T-1 volume spiked >= 40% without meaningful discount increase (<3pp)
                if ($t1QtyChange >= 40 && $t1DiscDiff < 3) {
                    $anomalyFlags[] = 'FORWARD BUY';
                    $anomalyCount++;
                }
            }

            $results[] = [
                'product_name' => $prod['name'],
                'principal_name' => $prod['principal'],
                'baseline_period' => $baseline->period,
                'baseline_disc_pct' => $baseline->discount_pct,
                'baseline_qty' => (int) $baseline->total_qty,
                'baseline_profit' => $profitNormal,
                'promo_period' => $promo->period,
                'promo_disc_pct' => $promo->discount_pct,
                'promo_qty' => (int) $promo->total_qty,
                'promo_subsidy' => (float) $promo->total_discount,
                'promo_profit' => $profitPromo,
                'uplift_qty' => $upliftQty,
                'uplift_pct' => $upliftPct,
                'profit_diff' => $profitDiff,
                'is_success' => $isSuccess,
                'anomaly_flags' => $anomalyFlags,
            ];
        }

        // Sort by profit_diff descending (best ROI first)
        usort($results, function ($a, $b) {
            return $b['profit_diff'] <=> $a['profit_diff'];
        });

        // Chart data: top 15 by profit_diff
        $chartData = array_slice($results, 0, 15);

        // --- DISTORA AI NARRATIVE GENERATOR ---
        $totalAnalyzed = count($results);
        $aiNarrative = "🔍 Fakta: Mesin DistoraVision berhasil membedah $totalAnalyzed produk yang mengalami pergeseran diskon signifikan antar periode.\n"
            ."✅ Hasil: $successCount promo SUKSES menghasilkan laba tambahan. $failCount promo GAGAL (Rugi Bandar).\n"
            .($anomalyCount > 0
                ? "⚠️ Anomali: Terdeteksi $anomalyCount kejadian mencurigakan (barang kosong pabrik atau toko menimbun). Periksa flag merah di tabel!\n"
                : '')
            .'💡 Saran Eksekusi: Ulangi promo yang SUKSES bulan depan. Untuk yang GAGAL, bekukan program hingga stok dan strategi dimatangkan!';

        // --- CSV EXPORT ---
        if ($request->get('export') === 'csv') {
            $rows = array_map(fn ($r) => [
                $r['product_name'],
                $r['principal_name'],
                $r['baseline_period'],
                $r['baseline_disc_pct'],
                $r['baseline_qty'],
                $r['promo_period'],
                $r['promo_disc_pct'],
                $r['promo_qty'],
                round($r['uplift_pct'], 1),
                $r['promo_subsidy'],
                $r['profit_diff'],
                $r['is_success'] ? 'SUKSES' : 'GAGAL',
                implode(', ', $r['anomaly_flags']),
            ], $results);

            return $this->streamCsv(
                "PromoUplift_{$period}.csv",
                ['Produk', 'Principal', 'Bln Normal', 'Disc Normal %', 'Qty Normal', 'Bln Promo', 'Disc Promo %', 'Qty Promo', 'Uplift %', 'Subsidi', 'Selisih Laba', 'Status', 'Flag'],
                $rows
            );
        }

        return view('analytics.promo-uplift', compact(
            'period', 'periods', 'results', 'chartData',
            'successCount', 'failCount', 'totalSubsidy', 'anomalyCount',
            'aiNarrative'
        ));
    }
}
