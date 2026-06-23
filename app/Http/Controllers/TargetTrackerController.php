<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\CsvExportable;
use App\Models\Salesman;
use App\Models\SalesmanTarget;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class TargetTrackerController extends Controller implements HasMiddleware
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

    public function targetTracker(Request $request)
    {
        $period = $request->get('period', Transaction::max('period') ?? date('Y-m'));
        $periods = Transaction::select('period')->distinct()->orderByDesc('period')->pluck('period');

        // MTD Current Performance
        $salesmenPerformances = Transaction::withFilters(request())
            ->invoices()
            ->join('salesmen', 'transactions.salesman_id', '=', 'salesmen.id')
            ->select(
                'salesmen.id as salesman_id',
                'salesmen.name as salesman_name',
                DB::raw('SUM(transactions.taxed_amt) as total_revenue')
            )
            ->groupBy('salesmen.id', 'salesmen.name')
            ->orderByDesc('total_revenue')
            ->get();

        // 3-Month Historical Period Calculation
        $currentCarbon = Carbon::createFromFormat('Y-m', $period);
        $pastPeriods = [];
        for ($i = 1; $i <= 3; $i++) {
            $pastPeriods[] = (clone $currentCarbon)->subMonths($i)->format('Y-m');
        }

        // Fetch Historical 3-Month Sales per Salesman
        $historicalSalesQuery = DB::table('transactions')
            ->whereIn('transactions.period', $pastPeriods)
            ->where('transactions.type', 'I')
            ->select('transactions.salesman_id', DB::raw('SUM(transactions.taxed_amt) as hist_revenue'))
            ->groupBy('transactions.salesman_id');

        // Manually apply Principal Filter (but ignore Date filters to protect the 3-Month logic)
        if ($request->has('principal_id') && ! empty($request->get('principal_id')) && $request->get('principal_id') !== 'all') {
            $historicalSalesQuery->join('products', 'transactions.product_id', '=', 'products.id')
                ->where('products.principal_id', $request->get('principal_id'));
        }

        $historicalSales = $historicalSalesQuery->get()->keyBy('salesman_id');

        $totalHistoricalSales = $historicalSales->sum('hist_revenue');
        // Prevent div by zero if there's absolutely no historical data
        if ($totalHistoricalSales <= 0) {
            $totalHistoricalSales = 1;
        }

        // The user inputs a TOTAL TEAM TARGET
        $teamTarget = $request->get('base_target', 10000000000); // 10 Billion as default team target

        // Simulated day of month
        $isCurrentMonth = ($period == date('Y-m'));
        $workingDays = 26;
        $currentDay = $isCurrentMonth ? (int) date('j') : 26;
        $remainingDays = $workingDays - $currentDay;
        if ($remainingDays <= 0) {
            $remainingDays = 1;
        }

        // Fetch existing SAVED targets from DB
        $savedTargets = SalesmanTarget::where('period', $period)->get()->keyBy('salesman_id');

        $tracking = $salesmenPerformances->map(function ($item) use ($teamTarget, $remainingDays, $historicalSales, $totalHistoricalSales, $savedTargets) {
            // Find historical contribution
            $histSales = $historicalSales->get($item->salesman_id)->hist_revenue ?? 0;
            $contributionRatio = $histSales / $totalHistoricalSales;

            // Priority: Use SAVED target from DB. Fallback to recommendation calculation.
            if ($savedTargets->has($item->salesman_id)) {
                $item->target = $savedTargets->get($item->salesman_id)->target_amount;
                $item->is_custom = true;
            } else {
                $item->target = $contributionRatio * $teamTarget;
                $item->is_custom = false;
            }

            $item->historical_ratio = $contributionRatio * 100; // For UI info
            $item->shortfall = $item->target - $item->total_revenue;
            if ($item->shortfall < 0) {
                $item->shortfall = 0;
            }

            $item->progress = $item->target > 0 ? ($item->total_revenue / $item->target) * 100 : 100;
            // Removed 100% cap to show real performance (e.g. 102%)

            $item->required_run_rate = $item->shortfall / $remainingDays;

            return $item;
        });

        // Re-sort based on their real progress
        $tracking = $tracking->sortByDesc('progress')->values();

        // --- DISTORA AI NARRATIVE GENERATOR ---
        $underperformers = $tracking->filter(fn ($t) => $t->progress < 80)->count();
        $totalSalesMTD = $tracking->sum('total_revenue');
        $totalGap = $teamTarget - $totalSalesMTD;
        $isAchieved = $totalGap <= 0;

        if ($isAchieved) {
            $aiNarrative = '🏆 Fakta: Target Global Perusahaan TELAH TERCAPAI! Akumulasi sales Rp '.number_format($totalSalesMTD, 0, ',', '.').".\n".
                           "🎉 Selamat: Seluruh tim telah bekerja keras melampaui target kolektif.\n".
                           "💡 Saran Eksekusi: Manfaatkan sisa $remainingDays hari untuk mendelegasikan stok ke outlet premium dan kunci PO untuk stok bulan depan!";
        } else {
            $runRate = $totalGap / max(1, $remainingDays);
            $aiNarrative = "🔍 Fakta: Sisa hari kerja aktif tinggal $remainingDays hari. Seluruh tim butuh berlari dengan pace kolektif Rp ".number_format($runRate, 0, ',', '.')." / hari.\n".
                           ($underperformers > 0
                            ? "⚠️ Peringatan: Ada $underperformers Salesman yang progressnya masih lampu merah (<80%).\n"
                            : "✅ Progress: Luar biasa! Seluruh salesman sudah berada di jalur yang benar (>80%).\n").
                           '💡 Saran Eksekusi: '.($underperformers > 0 ? 'Bantu tim yang masih berdarah-darah untuk mendobrak sales!' : 'Jaga momentum dan pastikan semua kiriman terproses tepat waktu!');
        }

        // --- CSV EXPORT ---
        if ($request->get('export') === 'csv') {
            $rows = $tracking->map(fn ($t) => [
                $t->salesman_name,
                $t->target,
                $t->total_revenue,
                round($t->progress, 2),
                $t->shortfall,
                $t->required_run_rate,
                round($t->historical_ratio, 2),
            ])->toArray();

            return $this->streamCsv(
                "TargetTracker_{$period}.csv",
                ['Salesman', 'Target', 'Sales MTD', 'Progress %', 'Shortfall', 'Run Rate/Hari', 'Kontribusi Historis %'],
                $rows
            );
        }

        return view('analytics.target-tracker', compact('period', 'periods', 'tracking', 'teamTarget', 'remainingDays', 'currentDay', 'workingDays', 'isCurrentMonth', 'aiNarrative'));
    }

    /**
     * Batch save salesman targets for a specific period
     */
    public function saveTargets(Request $request)
    {
        $validated = $request->validate([
            'period' => ['required', 'date_format:Y-m'],
            'targets' => ['required', 'array'],
            'targets.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        $period = $validated['period'];
        $targets = $validated['targets']; // [salesman_id => amount]
        $validSalesmanIds = Salesman::whereIn('id', array_keys($targets))
            ->pluck('id')
            ->all();

        foreach ($targets as $salesmanId => $amount) {
            if ($amount === null || $amount < 0) {
                continue;
            }
            if (! in_array((int) $salesmanId, $validSalesmanIds, true)) {
                continue;
            }

            SalesmanTarget::updateOrCreate(
                ['salesman_id' => $salesmanId, 'period' => $period],
                ['target_amount' => $amount]
            );
        }

        return back()->with('success', 'Target berhasil disimpan ke database!');
    }
}
