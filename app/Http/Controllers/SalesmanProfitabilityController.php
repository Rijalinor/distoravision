<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\CsvExportable;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class SalesmanProfitabilityController extends Controller implements HasMiddleware
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

    public function salesmanProfitability(Request $request)
    {
        $period = $request->get('period', Transaction::max('period') ?? date('Y-m'));
        $periods = Transaction::select('period')->distinct()->orderByDesc('period')->pluck('period');

        $startPeriod = $request->get('start_period', $request->get('period', $period));
        $endPeriod = $request->get('end_period', $request->get('period', $period));

        $salesmen = Transaction::withFilters($request)
            ->join('salesmen', 'transactions.salesman_id', '=', 'salesmen.id')
            ->select(
                'salesmen.id as salesman_id',
                'salesmen.name as salesman_name',
                DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.taxed_amt ELSE 0 END) as gross_sales'),
                DB::raw('SUM(CASE WHEN transactions.type = "R" THEN ABS(transactions.taxed_amt) ELSE 0 END) as total_returns'),
                DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.cogs WHEN transactions.type = "R" THEN -ABS(transactions.cogs) ELSE 0 END) as net_cogs'),
                DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.disc_total ELSE 0 END) as total_discount'),
                DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.gross ELSE 0 END) as total_gross'),
                DB::raw('COUNT(DISTINCT CASE WHEN transactions.type = "I" THEN transactions.outlet_id END) as outlet_count'),
                DB::raw('COUNT(DISTINCT CASE WHEN transactions.type = "I" THEN transactions.so_no END) as invoice_count')
            )
            ->groupBy('salesmen.id', 'salesmen.name')
            ->having('gross_sales', '>', 0)
            ->get()
            ->map(function ($s) {
                $s->net_sales = $s->gross_sales - $s->total_returns;
                $s->gross_profit = $s->net_sales - $s->net_cogs;
                $s->margin_percent = $s->net_sales > 0 ? ($s->gross_profit / $s->net_sales) * 100 : 0;
                $s->discount_depth = $s->total_gross > 0 ? ($s->total_discount / $s->total_gross) * 100 : 0;
                $s->return_rate = $s->gross_sales > 0 ? ($s->total_returns / $s->gross_sales) * 100 : 0;
                $s->avg_per_outlet = $s->outlet_count > 0 ? $s->net_sales / $s->outlet_count : 0;
                $s->avg_per_invoice = $s->invoice_count > 0 ? $s->net_sales / $s->invoice_count : 0;

                return $s;
            });

        // Overall KPIs
        $totalNetSales = $salesmen->sum('net_sales');
        $totalGrossProfit = $salesmen->sum('gross_profit');
        $avgMargin = $totalNetSales > 0 ? ($totalGrossProfit / $totalNetSales) * 100 : 0;

        // Assign contribution % and profitability rank
        $salesmen = $salesmen->map(function ($s) use ($totalNetSales, $totalGrossProfit) {
            $s->revenue_contribution = $totalNetSales > 0 ? ($s->net_sales / $totalNetSales) * 100 : 0;
            $s->profit_contribution = $totalGrossProfit > 0 ? ($s->gross_profit / $totalGrossProfit) * 100 : 0;

            // Efficiency Score: Profit contribution vs Revenue contribution
            // > 1 means they bring more profit than their revenue share (efficient)
            // < 1 means they burn margin relative to their sales volume (inefficient)
            $s->efficiency_ratio = $s->revenue_contribution > 0
                ? $s->profit_contribution / $s->revenue_contribution
                : 0;

            return $s;
        });

        // Sort options
        $sortBy = $request->get('sort', 'gross_profit');
        $salesmen = $salesmen->sortByDesc($sortBy)->values();

        // Rank by profit
        $profitRanked = $salesmen->sortByDesc('gross_profit')->values();
        $revenueRanked = $salesmen->sortByDesc('net_sales')->values();

        $salesmen = $salesmen->map(function ($s) use ($profitRanked, $revenueRanked) {
            $s->profit_rank = $profitRanked->search(fn ($x) => $x->salesman_id === $s->salesman_id) + 1;
            $s->revenue_rank = $revenueRanked->search(fn ($x) => $x->salesman_id === $s->salesman_id) + 1;
            $s->rank_shift = $s->revenue_rank - $s->profit_rank; // Positive = promoted when ranked by profit

            return $s;
        });

        // Detect "Discount Kings" — high revenue but low margin
        $discountKings = $salesmen->filter(fn ($s) => $s->efficiency_ratio < 0.8 && $s->net_sales > 0)->count();

        // --- DISTORA AI NARRATIVE GENERATOR ---
        $topProfit = $salesmen->sortByDesc('gross_profit')->first();
        $worstMargin = $salesmen->sortBy('margin_percent')->first();
        $aiNarrative = '🔍 Fakta: Ranking berubah drastis saat dilihat dari kacamata LABA, bukan omset. '.
            ($topProfit ? "Kontributor laba #1 adalah {$topProfit->salesman_name} dengan cuan Rp ".number_format($topProfit->gross_profit, 0, ',', '.').'.' : '')."\n".
            ($discountKings > 0
                ? "⚠️ Peringatan: Ada {$discountKings} salesman yang jualan banyak tapi marginnya HABIS dimakan diskon & return (efisiensi < 0.8x). Mereka adalah 'Raja Diskon' yang perlu diawasi!\n"
                : "✅ Bagus: Semua salesman memiliki efisiensi margin yang sehat.\n").
            ($worstMargin ? "💡 Saran: Evaluasi {$worstMargin->salesman_name} yang margin-nya hanya ".number_format($worstMargin->margin_percent, 1).'%. Apakah outlet-nya memang marjinal atau dia terlalu agresif beri diskon?' : '');

        // --- CSV EXPORT ---
        if ($request->get('export') === 'csv') {
            $rows = $salesmen->map(fn ($s) => [
                $s->salesman_name,
                $s->net_sales,
                $s->gross_profit,
                round($s->margin_percent, 2),
                round($s->discount_depth, 2),
                round($s->return_rate, 2),
                $s->outlet_count,
                $s->invoice_count,
                round($s->efficiency_ratio, 2),
                $s->profit_rank,
            ])->toArray();

            return $this->streamCsv(
                "SalesmanProfitability_{$period}.csv",
                ['Salesman', 'Net Sales', 'Gross Profit', 'Margin %', 'Discount Depth %', 'Return Rate %', 'Jumlah Outlet', 'Jumlah Invoice', 'Efisiensi Ratio', 'Rank Laba'],
                $rows
            );
        }

        return view('analytics.salesman-profitability', compact(
            'period', 'periods', 'salesmen', 'totalNetSales', 'totalGrossProfit',
            'avgMargin', 'discountKings', 'sortBy', 'aiNarrative'
        ));
    }
}
