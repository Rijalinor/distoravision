<?php

namespace App\Http\Controllers;

use App\Models\AccountingPeriod;
use App\Models\ArImportLog;
use App\Models\ArReceivable;
use App\Models\ClosingSnapshot;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PeriodController extends Controller
{
    /**
     * List all accounting periods.
     */
    public function index()
    {
        // Auto-seed periods from existing data
        $this->seedPeriodsFromData();

        $periods = AccountingPeriod::with(['closedByUser', 'snapshot'])
            ->orderByDesc('year')
            ->orderByDesc('month')
            ->paginate(12);

        return view('periods.index', compact('periods'));
    }

    /**
     * Execute month-end closing.
     */
    public function close(Request $request, AccountingPeriod $period)
    {
        if ($period->isClosed()) {
            return back()->with('error', 'Periode ini sudah ditutup sebelumnya.');
        }

        $request->validate([
            'notes' => 'nullable|string|max:500',
        ]);

        DB::transaction(function () use ($period, $request) {
            $periodStr = $period->format_period; // YYYY-MM

            // ══════════════════════════════════════════════════════════
            // SALES SNAPSHOT
            // ══════════════════════════════════════════════════════════
            $salesQuery = Transaction::withoutGlobalScopes()->where('period', $periodStr);

            $totalSales = (clone $salesQuery)->where('type', 'I')->sum('taxed_amt');
            $totalReturns = (clone $salesQuery)->where('type', 'R')->sum(DB::raw('ABS(taxed_amt)'));
            $totalCogs = (clone $salesQuery)->where('type', 'I')->sum('cogs');
            $netSales = $totalSales - $totalReturns;
            $invoiceCount = (clone $salesQuery)->where('type', 'I')->count();
            $returnCount = (clone $salesQuery)->where('type', 'R')->count();
            $returnRate = $totalSales > 0 ? ($totalReturns / $totalSales) * 100 : 0;
            $margin = $netSales > 0 ? (($netSales - $totalCogs) / $netSales) * 100 : 0;

            // Top 10 Products
            $topProducts = (clone $salesQuery)->where('type', 'I')
                ->join('products', 'transactions.product_id', '=', 'products.id')
                ->select('products.name', DB::raw('SUM(transactions.taxed_amt) as total_sales'), DB::raw('SUM(transactions.qty_base) as total_qty'))
                ->groupBy('products.name')
                ->orderByDesc('total_sales')
                ->limit(10)->get()->toArray();

            // Top 10 Outlets
            $topOutlets = (clone $salesQuery)->where('type', 'I')
                ->join('outlets', 'transactions.outlet_id', '=', 'outlets.id')
                ->select('outlets.name', 'outlets.city', DB::raw('SUM(transactions.taxed_amt) as total_sales'))
                ->groupBy('outlets.name', 'outlets.city')
                ->orderByDesc('total_sales')
                ->limit(10)->get()->toArray();

            // Principal breakdown
            $principalBreakdown = (clone $salesQuery)->where('type', 'I')
                ->join('products', 'transactions.product_id', '=', 'products.id')
                ->join('principals', 'products.principal_id', '=', 'principals.id')
                ->select('principals.name', DB::raw('SUM(transactions.taxed_amt) as total_sales'))
                ->groupBy('principals.name')
                ->orderByDesc('total_sales')->get()->toArray();

            // Salesman sales data
            $salesmanSalesData = (clone $salesQuery)->where('type', 'I')
                ->join('salesmen', 'transactions.salesman_id', '=', 'salesmen.id')
                ->select('salesmen.name', DB::raw('SUM(transactions.taxed_amt) as total_sales'), DB::raw('COUNT(*) as invoice_count'))
                ->groupBy('salesmen.name')
                ->orderByDesc('total_sales')->get()->toArray();

            // ══════════════════════════════════════════════════════════
            // AR SNAPSHOT (from latest completed import in this month)
            // ══════════════════════════════════════════════════════════
            $startDate = Carbon::createFromDate($period->year, $period->month, 1)->startOfMonth();
            $endDate = $startDate->copy()->endOfMonth();

            $latestArImport = ArImportLog::where('status', 'completed')
                ->whereBetween('report_date', [$startDate, $endDate])
                ->orderByDesc('report_date')
                ->first();

            $arData = [
                'total_outstanding' => 0, 'total_overdue' => 0,
                'total_ar_amount' => 0, 'total_ar_paid' => 0,
                'ar_outlet_count' => 0, 'ar_invoice_count' => 0,
                'avg_overdue_days' => 0, 'max_overdue_days' => 0,
                'aging_data' => null, 'salesman_ar_data' => null,
            ];

            if ($latestArImport) {
                $arQuery = ArReceivable::withoutGlobalScopes()
                    ->where('ar_import_log_id', $latestArImport->id);

                $arKpi = (clone $arQuery)->selectRaw('
                    SUM(ar_balance) as total_outstanding,
                    SUM(CASE WHEN overdue_days > 0 AND ar_balance > 0 THEN ar_balance ELSE 0 END) as total_overdue,
                    SUM(ar_amount) as total_ar_amount,
                    SUM(ar_paid) as total_ar_paid,
                    COUNT(DISTINCT CASE WHEN ar_balance > 0 THEN outlet_code END) as ar_outlet_count,
                    COUNT(CASE WHEN ar_balance > 0 THEN 1 END) as ar_invoice_count,
                    AVG(CASE WHEN overdue_days > 0 AND ar_balance > 0 THEN overdue_days ELSE NULL END) as avg_overdue_days,
                    MAX(overdue_days) as max_overdue_days
                ')->first();

                $arData['total_outstanding'] = (float) ($arKpi->total_outstanding ?? 0);
                $arData['total_overdue'] = (float) ($arKpi->total_overdue ?? 0);
                $arData['total_ar_amount'] = (float) ($arKpi->total_ar_amount ?? 0);
                $arData['total_ar_paid'] = (float) ($arKpi->total_ar_paid ?? 0);
                $arData['ar_outlet_count'] = (int) ($arKpi->ar_outlet_count ?? 0);
                $arData['ar_invoice_count'] = (int) ($arKpi->ar_invoice_count ?? 0);
                $arData['avg_overdue_days'] = (int) round($arKpi->avg_overdue_days ?? 0);
                $arData['max_overdue_days'] = (int) ($arKpi->max_overdue_days ?? 0);

                // Aging buckets
                $agingRaw = (clone $arQuery)->where('ar_balance', '>', 0)
                    ->selectRaw("
                        CASE
                            WHEN overdue_days <= 0 THEN 'Current'
                            WHEN overdue_days BETWEEN 1 AND 30 THEN '1-30'
                            WHEN overdue_days BETWEEN 31 AND 60 THEN '31-60'
                            WHEN overdue_days BETWEEN 61 AND 90 THEN '61-90'
                            ELSE '>90'
                        END as bucket,
                        COUNT(*) as count, SUM(ar_balance) as total
                    ")->groupBy('bucket')->get()->keyBy('bucket');

                $agingBuckets = [];
                foreach (['Current', '1-30', '31-60', '61-90', '>90'] as $b) {
                    $agingBuckets[$b] = [
                        'count' => (int) ($agingRaw[$b]->count ?? 0),
                        'total' => (float) ($agingRaw[$b]->total ?? 0),
                    ];
                }
                $arData['aging_data'] = $agingBuckets;

                // Salesman AR data
                $arData['salesman_ar_data'] = (clone $arQuery)->where('ar_balance', '>', 0)
                    ->selectRaw('salesman_name, SUM(ar_balance) as total_balance, COUNT(DISTINCT outlet_code) as outlet_count, MAX(overdue_days) as max_overdue, COUNT(*) as invoice_count')
                    ->groupBy('salesman_name')
                    ->orderByDesc('total_balance')->get()->toArray();
            }

            // ══════════════════════════════════════════════════════════
            // SAVE SNAPSHOT
            // ══════════════════════════════════════════════════════════
            ClosingSnapshot::updateOrCreate(
                ['accounting_period_id' => $period->id],
                array_merge([
                    'total_sales' => $totalSales,
                    'total_returns' => $totalReturns,
                    'net_sales' => $netSales,
                    'total_cogs' => $totalCogs,
                    'invoice_count' => $invoiceCount,
                    'return_count' => $returnCount,
                    'return_rate' => $returnRate,
                    'margin' => $margin,
                    'top_products' => $topProducts,
                    'top_outlets' => $topOutlets,
                    'principal_breakdown' => $principalBreakdown,
                    'salesman_sales_data' => $salesmanSalesData,
                    'snapshot_at' => now(),
                ], $arData)
            );

            // ══════════════════════════════════════════════════════════
            // CLOSE PERIOD & AUTO-CREATE NEXT
            // ══════════════════════════════════════════════════════════
            $period->update([
                'status' => 'closed',
                'closed_at' => now(),
                'closed_by' => auth()->id(),
                'notes' => $request->notes,
            ]);

            // Auto-create next month's period
            $nextMonth = Carbon::createFromDate($period->year, $period->month, 1)->addMonth();
            AccountingPeriod::firstOrCreate(
                ['year' => $nextMonth->year, 'month' => $nextMonth->month],
                ['status' => 'open']
            );
        });

        activity()
            ->causedBy(auth()->user())
            ->performedOn($period)
            ->withProperties(['period' => $period->label])
            ->log('melakukan tutup buku periode ' . $period->label);

        return redirect()->route('periods.index')
            ->with('success', "Tutup buku periode {$period->label} berhasil! Snapshot data telah disimpan.");
    }

    /**
     * View snapshot detail for a closed period.
     */
    public function show(AccountingPeriod $period)
    {
        $period->load(['snapshot', 'closedByUser']);

        if (!$period->snapshot) {
            return redirect()->route('periods.index')
                ->with('error', 'Snapshot belum tersedia untuk periode ini.');
        }

        return view('periods.show', compact('period'));
    }

    /**
     * Reopen a closed period.
     */
    public function reopen(AccountingPeriod $period)
    {
        if ($period->isOpen()) {
            return back()->with('error', 'Periode ini sudah dalam status terbuka.');
        }

        $period->update([
            'status' => 'open',
            'closed_at' => null,
            'closed_by' => null,
            'notes' => null,
        ]);

        activity()
            ->causedBy(auth()->user())
            ->performedOn($period)
            ->withProperties(['period' => $period->label])
            ->log('membuka kembali periode ' . $period->label);

        return redirect()->route('periods.index')
            ->with('success', "Periode {$period->label} berhasil dibuka kembali.");
    }

    /**
     * Auto-seed periods from existing Transaction & ArImportLog data.
     */
    private function seedPeriodsFromData(): void
    {
        // From transactions
        $salesPeriods = Transaction::withoutGlobalScopes()
            ->select('period')->distinct()->pluck('period');

        foreach ($salesPeriods as $p) {
            if ($p && preg_match('/^\d{4}-\d{2}$/', $p)) {
                [$year, $month] = explode('-', $p);
                AccountingPeriod::firstOrCreate(
                    ['year' => (int) $year, 'month' => (int) $month],
                    ['status' => 'open']
                );
            }
        }

        // From AR import logs
        $arDates = ArImportLog::select(DB::raw('DISTINCT DATE_FORMAT(report_date, "%Y-%m") as period'))
            ->whereNotNull('report_date')
            ->pluck('period');

        foreach ($arDates as $p) {
            if ($p && preg_match('/^\d{4}-\d{2}$/', $p)) {
                [$year, $month] = explode('-', $p);
                AccountingPeriod::firstOrCreate(
                    ['year' => (int) $year, 'month' => (int) $month],
                    ['status' => 'open']
                );
            }
        }
    }
}
