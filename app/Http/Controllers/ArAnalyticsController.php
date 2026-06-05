<?php

namespace App\Http\Controllers;

use App\Models\ArImportLog;
use App\Models\ArReceivable;
use Illuminate\Http\Request;

class ArAnalyticsController extends Controller
{
    /**
     * Main dashboard with tab-based navigation.
     */
    public function dashboard(Request $request)
    {
        $latestImport = ArImportLog::where('status', 'completed')
            ->orderByDesc('report_date')
            ->first();

        if (! $latestImport) {
            return view('ar.dashboard', ['hasData' => false, 'latestImport' => null, 'tab' => 'aging']);
        }

        $tab = $request->input('tab', 'aging');
        $branch = $request->input('branch');
        $search = $request->input('search');

        // ── GLOBAL FILTERS ──────────────────────────────────────────
        $filters = [
            'start_date' => $request->input('start_date'),
            'end_date' => $request->input('end_date'),
            'salesman' => $request->input('salesman'),
            'principal' => $request->input('principal'),
        ];

        // Base query scoped to latest import
        $baseQuery = ArReceivable::where('ar_import_log_id', $latestImport->id);
        if ($branch) {
            $baseQuery->where('branch_sheet', $branch);
        }
        if ($filters['start_date']) {
            $baseQuery->whereDate('doc_date', '>=', $filters['start_date']);
        }
        if ($filters['end_date']) {
            $baseQuery->whereDate('doc_date', '<=', $filters['end_date']);
        }
        if ($filters['salesman']) {
            $baseQuery->where('salesman_name', $filters['salesman']);
        }
        if ($filters['principal']) {
            $baseQuery->where('principal_name', $filters['principal']);
        }

        // Filter options (from full dataset, unfiltered)
        $scopedQuery = ArReceivable::where('ar_import_log_id', $latestImport->id);
        if ($branch) {
            $scopedQuery->where('branch_sheet', $branch);
        }

        $branches = ArReceivable::where('ar_import_log_id', $latestImport->id)
            ->distinct()->pluck('branch_sheet')->sort()->values();
        $salesmanList = (clone $scopedQuery)->whereNotNull('salesman_name')
            ->where('salesman_name', '!=', '')
            ->distinct()->pluck('salesman_name')->sort()->values();
        $principalList = (clone $scopedQuery)->whereNotNull('principal_name')
            ->where('principal_name', '!=', '')
            ->distinct()->pluck('principal_name')->sort()->values();
        $dateRange = (clone $scopedQuery)->selectRaw('MIN(doc_date) as min_date, MAX(doc_date) as max_date')->first();

        // KPI (always loaded — shown on every tab)
        $kpi = (clone $baseQuery)->selectRaw('
            SUM(ar_balance) as total_outstanding,
            SUM(ar_amount) as total_ar_amount,
            SUM(ar_paid) as total_ar_paid,
            SUM(CASE WHEN overdue_days > 0 AND ar_balance > 0 THEN ar_balance ELSE 0 END) as total_overdue,
            COUNT(DISTINCT CASE WHEN ar_balance > 0 THEN outlet_code END) as outlet_count,
            COUNT(CASE WHEN ar_balance > 0 THEN 1 END) as invoice_count,
            AVG(CASE WHEN overdue_days > 0 AND ar_balance > 0 THEN overdue_days ELSE NULL END) as avg_overdue,
            MAX(overdue_days) as max_overdue,
            SUM(CASE WHEN credit_limit > 0 AND ar_balance > credit_limit THEN 1 ELSE 0 END) as over_limit_count,
            COUNT(CASE WHEN cm >= 3 AND ar_balance > 0 THEN 1 END) as stubborn_count
        ')->first();

        $activeFilterCount = collect($filters)->filter()->count() + ($branch ? 1 : 0);

        $data = [
            'hasData' => true,
            'latestImport' => $latestImport,
            'tab' => $tab,
            'kpi' => $kpi,
            'branches' => $branches,
            'currentBranch' => $branch,
            'search' => $search,
            'filters' => $filters,
            'salesmanList' => $salesmanList,
            'principalList' => $principalList,
            'dateRange' => $dateRange,
            'activeFilterCount' => $activeFilterCount,
        ];

        // Load tab-specific data
        $method = 'loadTab'.str_replace(' ', '', ucwords(str_replace('-', ' ', $tab)));
        if (method_exists($this, $method)) {
            $data = array_merge($data, $this->$method(clone $baseQuery, $request));
        }

        return view('ar.dashboard', $data);
    }

    // ── TAB: AGING & RINGKASAN (default) ─────────────────────────────
    protected function loadTabAging($query, Request $request): array
    {
        $agingData = (clone $query)->where('ar_balance', '>', 0)
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

        $bucketOrder = ['Current', '1-30', '31-60', '61-90', '>90'];
        $agingBuckets = [];
        foreach ($bucketOrder as $b) {
            $agingBuckets[$b] = [
                'count' => $agingData[$b]->count ?? 0,
                'total' => (float) ($agingData[$b]->total ?? 0),
            ];
        }

        // Detail list for current bucket filter
        $bucket = $request->input('bucket');
        $agingDetail = null;
        if ($bucket) {
            $dq = (clone $query)->where('ar_balance', '>', 0);
            if ($bucket === 'Current') {
                $dq->where('overdue_days', '<=', 0);
            } elseif ($bucket === '1-30') {
                $dq->whereBetween('overdue_days', [1, 30]);
            } elseif ($bucket === '31-60') {
                $dq->whereBetween('overdue_days', [31, 60]);
            } elseif ($bucket === '61-90') {
                $dq->whereBetween('overdue_days', [61, 90]);
            } else {
                $dq->where('overdue_days', '>', 90);
            }
            $agingDetail = $dq->orderByDesc('ar_balance')->paginate(20)->appends($request->query());
        }

        return ['agingBuckets' => $agingBuckets, 'agingDetail' => $agingDetail, 'currentBucket' => $bucket];
    }

    // ── TAB: EVALUASI PENAGIHAN ──────────────────────────────────────
    protected function loadTabEvaluasi($query, Request $request): array
    {
        // 1. Salesman Performance
        $salesmanAr = (clone $query)->where('ar_balance', '>', 0)
            ->selectRaw('salesman_code, salesman_name, SUM(ar_balance) as total_balance, COUNT(DISTINCT outlet_code) as outlet_count, MAX(overdue_days) as max_overdue, AVG(overdue_days) as avg_overdue, COUNT(*) as invoice_count, SUM(CASE WHEN cm >= 3 THEN 1 ELSE 0 END) as stubborn_invoices')
            ->groupBy('salesman_code', 'salesman_name')
            ->orderByDesc('total_balance')->get();

        // 2. Problematic Outlets (Top AR, Worst Payers, Over Limit)
        $worstOutlets = (clone $query)->where('ar_amount', '>', 0)->where('ar_balance', '>', 0)
            ->selectRaw('outlet_code, outlet_name, salesman_name, COUNT(*) as invoice_count, SUM(ar_amount) as total_invoiced, SUM(ar_paid) as total_paid, SUM(ar_balance) as total_balance, ROUND(SUM(ar_paid)/SUM(ar_amount)*100,1) as payment_pct, MAX(overdue_days) as max_overdue, MAX(cm) as max_cm, MAX(credit_limit) as credit_limit')
            ->groupBy('outlet_code', 'outlet_name', 'salesman_name')
            ->having('total_balance', '>', 0)->orderByDesc('total_balance')
            ->paginate(20)->appends($request->query());

        return ['salesmanAr' => $salesmanAr, 'worstOutlets' => $worstOutlets];
    }

    // ── TAB: PRIORITAS PENINDAKAN ────────────────────────────────────
    protected function loadTabPrioritas($query, Request $request): array
    {
        $dq = (clone $query)->where('ar_balance', '>', 0)
            ->where(function ($q) {
                $q->where('overdue_days', '>', 60)
                    ->orWhere('cm', '>=', 3);
            });

        $search = $request->input('search');
        if ($search) {
            $dq->where(function ($q) use ($search) {
                $q->where('outlet_name', 'like', "%{$search}%")
                    ->orWhere('outlet_code', 'like', "%{$search}%")
                    ->orWhere('pfi_sn', 'like', "%{$search}%")
                    ->orWhere('salesman_name', 'like', "%{$search}%");
            });
        }

        $urgentInvoices = $dq->selectRaw('outlet_code, outlet_name, salesman_name, pfi_sn, ar_balance, overdue_days, cm, due_date, principal_name')
            ->orderByDesc('ar_balance')
            ->paginate(20)->appends($request->query());

        // Kpi for this tab
        $kpiPrioritas = (clone $query)->where('ar_balance', '>', 0)
            ->where(function ($q) {
                $q->where('overdue_days', '>', 60)
                    ->orWhere('cm', '>=', 3);
            })
            ->selectRaw('COUNT(*) as total_invoices, SUM(ar_balance) as total_amount')
            ->first();

        return ['urgentInvoices' => $urgentInvoices, 'kpiPrioritas' => $kpiPrioritas];
    }

    // ── TAB: DATA GIRO & INVOICE ─────────────────────────────────────
    protected function loadTabData($query, Request $request): array
    {
        // 1. Giro
        $giroPerBank = (clone $query)->whereNotNull('giro_no')->where('giro_no', '!=', '')
            ->where('giro_amount', '>', 0)->whereNotNull('bank_name')
            ->selectRaw('bank_name, COUNT(DISTINCT giro_no) as giro_count, SUM(giro_amount) as total_amount')
            ->groupBy('bank_name')->orderByDesc('total_amount')->get();

        $giroList = (clone $query)->whereNotNull('giro_no')->where('giro_no', '!=', '')
            ->where('giro_amount', '>', 0)
            ->selectRaw('giro_no, outlet_code, outlet_name, salesman_name, bank_name, giro_amount, giro_due_date, ar_balance, pfi_sn')
            ->orderBy('giro_due_date')->paginate(10, ['*'], 'giro_page')->appends($request->query());

        // 2. Invoice Details
        $dq = (clone $query)->where('ar_balance', '>', 0);
        $search = $request->input('search');
        if ($search) {
            $dq->where(function ($q) use ($search) {
                $q->where('outlet_name', 'like', "%{$search}%")
                    ->orWhere('outlet_code', 'like', "%{$search}%")
                    ->orWhere('pfi_sn', 'like', "%{$search}%")
                    ->orWhere('salesman_name', 'like', "%{$search}%");
            });
        }
        $details = $dq->orderByDesc('ar_balance')->paginate(20, ['*'], 'detail_page')->appends($request->query());

        return [
            'giroPerBank' => $giroPerBank,
            'giroList' => $giroList,
            'details' => $details,
        ];
    }

    // ── TAB: DSO TRACKING ──────────────────────────────────────────
    protected function loadTabDso($query, Request $request): array
    {
        // DSO per Salesman — avg days from invoice to payment
        $dsoPerSalesman = (clone $query)->where('ar_balance', '>', 0)
            ->whereNotNull('salesman_name')
            ->where('salesman_name', '!=', '')
            ->selectRaw('
                salesman_name,
                salesman_code,
                COUNT(*) as invoice_count,
                COUNT(DISTINCT outlet_code) as outlet_count,
                SUM(ar_balance) as total_outstanding,
                AVG(overdue_days) as avg_dso,
                MAX(overdue_days) as max_dso,
                SUM(CASE WHEN overdue_days > 30 THEN ar_balance ELSE 0 END) as overdue_30_value,
                SUM(CASE WHEN overdue_days > 60 THEN ar_balance ELSE 0 END) as overdue_60_value
            ')
            ->groupBy('salesman_name', 'salesman_code')
            ->orderByDesc('avg_dso')
            ->get();

        // Global DSO KPIs
        $dsoKpi = (clone $query)->where('ar_balance', '>', 0)
            ->selectRaw('
                AVG(overdue_days) as global_avg_dso,
                AVG(CASE WHEN overdue_days > 0 THEN overdue_days ELSE NULL END) as avg_overdue_dso,
                SUM(ar_balance) as total_outstanding,
                SUM(ar_amount) as total_ar_value,
                COUNT(DISTINCT outlet_code) as total_outlets,
                COUNT(*) as total_invoices
            ')
            ->first();

        // DSO per Outlet — identify worst payers by payment speed
        $dsoPerOutlet = (clone $query)->where('ar_balance', '>', 0)
            ->selectRaw('
                outlet_code,
                outlet_name,
                salesman_name,
                COUNT(*) as invoice_count,
                SUM(ar_balance) as total_outstanding,
                SUM(ar_amount) as total_ar_amount,
                SUM(ar_paid) as total_ar_paid,
                AVG(overdue_days) as avg_dso,
                MAX(overdue_days) as max_dso,
                MAX(cm) as max_cm
            ')
            ->groupBy('outlet_code', 'outlet_name', 'salesman_name')
            ->orderByDesc('avg_dso')
            ->paginate(20, ['*'], 'dso_page')
            ->appends($request->query());

        // Enrich outlet data with payment rate
        $dsoPerOutlet->getCollection()->transform(function ($item) {
            $item->payment_rate = $item->total_ar_amount > 0
                ? ($item->total_ar_paid / $item->total_ar_amount) * 100
                : 0;

            // Risk classification based on DSO
            if ($item->avg_dso > 60) {
                $item->risk_level = 'Kritis';
                $item->risk_color = 'badge-red';
            } elseif ($item->avg_dso > 30) {
                $item->risk_level = 'Waspada';
                $item->risk_color = 'badge-yellow';
            } else {
                $item->risk_level = 'Normal';
                $item->risk_color = 'badge-green';
            }

            return $item;
        });

        // DSO Distribution — how many invoices fall in each DSO range
        $dsoDistribution = (clone $query)->where('ar_balance', '>', 0)
            ->selectRaw("
                CASE
                    WHEN overdue_days <= 0 THEN 'Current'
                    WHEN overdue_days BETWEEN 1 AND 15 THEN '1-15 hr'
                    WHEN overdue_days BETWEEN 16 AND 30 THEN '16-30 hr'
                    WHEN overdue_days BETWEEN 31 AND 45 THEN '31-45 hr'
                    WHEN overdue_days BETWEEN 46 AND 60 THEN '46-60 hr'
                    WHEN overdue_days BETWEEN 61 AND 90 THEN '61-90 hr'
                    ELSE '>90 hr'
                END as dso_range,
                COUNT(*) as count,
                SUM(ar_balance) as total_value
            ")
            ->groupByRaw("
                CASE
                    WHEN overdue_days <= 0 THEN 'Current'
                    WHEN overdue_days BETWEEN 1 AND 15 THEN '1-15 hr'
                    WHEN overdue_days BETWEEN 16 AND 30 THEN '16-30 hr'
                    WHEN overdue_days BETWEEN 31 AND 45 THEN '31-45 hr'
                    WHEN overdue_days BETWEEN 46 AND 60 THEN '46-60 hr'
                    WHEN overdue_days BETWEEN 61 AND 90 THEN '61-90 hr'
                    ELSE '>90 hr'
                END
            ")
            ->get()
            ->keyBy('dso_range');

        $dsoRangeOrder = ['Current', '1-15 hr', '16-30 hr', '31-45 hr', '46-60 hr', '61-90 hr', '>90 hr'];
        $dsoChartLabels = [];
        $dsoChartCounts = [];
        $dsoChartValues = [];
        foreach ($dsoRangeOrder as $range) {
            $dsoChartLabels[] = $range;
            $dsoChartCounts[] = (int) ($dsoDistribution[$range]->count ?? 0);
            $dsoChartValues[] = (float) ($dsoDistribution[$range]->total_value ?? 0);
        }

        return [
            'dsoKpi' => $dsoKpi,
            'dsoPerSalesman' => $dsoPerSalesman,
            'dsoPerOutlet' => $dsoPerOutlet,
            'dsoChartLabels' => $dsoChartLabels,
            'dsoChartCounts' => $dsoChartCounts,
            'dsoChartValues' => $dsoChartValues,
        ];
    }
}
