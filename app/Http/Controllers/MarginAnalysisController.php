<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\CsvExportable;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class MarginAnalysisController extends Controller implements HasMiddleware
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

    public function marginAnalysis(Request $request)
    {
        $period = $request->get('period', Transaction::max('period') ?? date('Y-m'));
        $periods = Transaction::select('period')->distinct()->orderByDesc('period')->pluck('period');

        // Global KPIs
        $kpis = Transaction::withFilters(request())
            ->selectRaw('
                SUM(CASE WHEN type = "I" THEN taxed_amt WHEN type = "R" THEN -ABS(taxed_amt) ELSE 0 END) as total_revenue,
                SUM(CASE WHEN type = "I" THEN cogs WHEN type = "R" THEN -ABS(cogs) ELSE 0 END) as total_cogs
            ')->first();

        $totalRevenue = $kpis->total_revenue ?? 0;
        $totalCogs = $kpis->total_cogs ?? 0;
        $totalGrossProfit = $totalRevenue - $totalCogs;
        $blendedMargin = $totalRevenue > 0 ? ($totalGrossProfit / $totalRevenue) * 100 : 0;

        // Principal Margins
        $principalMargins = Transaction::withFilters(request())
            ->join('products', 'transactions.product_id', '=', 'products.id')
            ->join('principals', 'products.principal_id', '=', 'principals.id')
            ->select(
                'principals.name as principal_name',
                DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.taxed_amt WHEN transactions.type = "R" THEN -ABS(transactions.taxed_amt) ELSE 0 END) as revenue'),
                DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.cogs WHEN transactions.type = "R" THEN -ABS(transactions.cogs) ELSE 0 END) as cogs')
            )
            ->groupBy('principals.name')
            ->having('revenue', '>', 0)
            ->get()
            ->map(function ($item) {
                $item->gross_profit = $item->revenue - $item->cogs;
                $item->margin_percent = $item->revenue > 0 ? ($item->gross_profit / $item->revenue) * 100 : 0;
                $item->principal_name = str_replace('PT. ', '', $item->principal_name);

                return $item;
            })
            ->sortByDesc('margin_percent')->values();

        // Top Profitable Products
        $productMargins = Transaction::withFilters(request())
            ->join('products', 'transactions.product_id', '=', 'products.id')
            ->join('principals', 'products.principal_id', '=', 'principals.id')
            ->select(
                'products.name as product_name',
                'principals.name as principal_name',
                DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.taxed_amt WHEN transactions.type = "R" THEN -ABS(transactions.taxed_amt) ELSE 0 END) as revenue'),
                DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.cogs WHEN transactions.type = "R" THEN -ABS(transactions.cogs) ELSE 0 END) as cogs')
            )
            ->groupBy('products.name', 'principals.name')
            ->having('revenue', '>', 0)
            ->get()
            ->map(function ($item) {
                $item->gross_profit = $item->revenue - $item->cogs;
                $item->margin_percent = $item->revenue > 0 ? ($item->gross_profit / $item->revenue) * 100 : 0;

                return $item;
            })
            ->sortByDesc('gross_profit')
            ->take(50)->values();

        // --- DISTORA AI NARRATIVE GENERATOR ---
        $bottomProd = $productMargins->last() ? trim($productMargins->last()->product_name) : 'none';
        $aiNarrative = '🔍 Fakta: Blended Laba Kotor stabil di angka '.number_format($blendedMargin, 2).'% dengan cuan bersih tunai Rp '.number_format($totalGrossProfit, 0, ',', '.').".\n".
                       "💡 Saran Eksekusi: Ada produk beresiko di jajaran paling bawah (contoh: $bottomProd) yang marginnya dimakan diskon atau HPP bengkak. Kaji ulang harga dasar. Jangan sampai lelah jualan tapi hasilnya bakar duit.";

        // --- CSV EXPORT ---
        if ($request->get('export') === 'csv') {
            $rows = $productMargins->map(fn ($p) => [
                $p->product_name,
                str_replace('PT. ', '', $p->principal_name),
                $p->revenue,
                $p->cogs,
                $p->gross_profit,
                round($p->margin_percent, 2),
            ])->toArray();

            return $this->streamCsv(
                "Margin_{$period}.csv",
                ['Produk', 'Principal', 'Revenue', 'COGS', 'Gross Profit', 'Margin %'],
                $rows
            );
        }

        return view('analytics.margin', compact(
            'period', 'periods', 'totalRevenue', 'totalCogs', 'totalGrossProfit', 'blendedMargin',
            'principalMargins', 'productMargins', 'aiNarrative'
        ));
    }
}
