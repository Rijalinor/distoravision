<?php

namespace App\Http\Controllers;

use App\Models\ArImportLog;
use App\Models\ArReceivable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        if (!$latestImport) {
            return view('ar.dashboard', ['hasData' => false, 'latestImport' => null, 'tab' => 'overview']);
        }

        $tab = $request->input('tab', 'overview');
        $branch = $request->input('branch');
        $search = $request->input('search');

        // ── GLOBAL FILTERS ──────────────────────────────────────────
        $filters = [
            'start_date'  => $request->input('start_date'),
            'end_date'    => $request->input('end_date'),
            'salesman'    => $request->input('salesman'),
            'principal'   => $request->input('principal'),
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
        if ($branch) $scopedQuery->where('branch_sheet', $branch);

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
        $method = 'loadTab' . str_replace(' ', '', ucwords(str_replace('-', ' ', $tab)));
        if (method_exists($this, $method)) {
            $data = array_merge($data, $this->$method(clone $baseQuery, $request));
        }

        return view('ar.dashboard', $data);
    }

    // ── TAB: OVERVIEW (default) ──────────────────────────────────────
    protected function loadTabOverview($query, Request $request): array
    {
        // Giro summary
        $giroSummary = (clone $query)->whereNotNull('giro_no')->where('giro_no', '!=', '')
            ->where('giro_amount', '>', 0)
            ->selectRaw('COUNT(DISTINCT giro_no) as total_giros, SUM(giro_amount) as total_giro_amount')
            ->first();

        return ['giroSummary' => $giroSummary];
    }

    // ── TAB: AGING ───────────────────────────────────────────────────
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
            if ($bucket === 'Current') $dq->where('overdue_days', '<=', 0);
            elseif ($bucket === '1-30') $dq->whereBetween('overdue_days', [1, 30]);
            elseif ($bucket === '31-60') $dq->whereBetween('overdue_days', [31, 60]);
            elseif ($bucket === '61-90') $dq->whereBetween('overdue_days', [61, 90]);
            else $dq->where('overdue_days', '>', 90);
            $agingDetail = $dq->orderByDesc('ar_balance')->paginate(20)->appends($request->query());
        }

        return ['agingBuckets' => $agingBuckets, 'agingDetail' => $agingDetail, 'currentBucket' => $bucket];
    }

    // ── TAB: CREDIT RISK ─────────────────────────────────────────────
    protected function loadTabCreditRisk($query, Request $request): array
    {
        $creditRisk = (clone $query)->where('ar_balance', '>', 0)
            ->selectRaw('
                outlet_code, outlet_name, salesman_name,
                SUM(ar_balance) as total_balance, MAX(credit_limit) as credit_limit,
                CASE 
                    WHEN MAX(credit_limit) > 1 THEN ROUND(SUM(ar_balance) / MAX(credit_limit) * 100, 1)
                    ELSE 0 
                END as utilization_pct,
                MAX(overdue_days) as max_overdue, MAX(cm) as max_cm
            ')
            ->groupBy('outlet_code', 'outlet_name', 'salesman_name')
            ->orderByDesc(DB::raw('CASE WHEN MAX(credit_limit) > 1 THEN SUM(ar_balance) / MAX(credit_limit) ELSE 0 END'))
            ->paginate(20)->appends($request->query());

        $riskBuckets = (clone $query)->where('ar_balance', '>', 0)->where('credit_limit', '>', 0)
            ->selectRaw("
                CASE
                    WHEN SUM(ar_balance) <= MAX(credit_limit) * 0.5 THEN 'Low'
                    WHEN SUM(ar_balance) <= MAX(credit_limit) * 0.8 THEN 'Medium'
                    WHEN SUM(ar_balance) <= MAX(credit_limit) THEN 'High'
                    ELSE 'Over Limit'
                END as risk_level, COUNT(*) as count
            ")->groupBy('outlet_code')->get()->groupBy('risk_level')->map(fn($g) => $g->count());

        $riskLevels = ['Low' => 0, 'Medium' => 0, 'High' => 0, 'Over Limit' => 0];
        foreach ($riskBuckets as $level => $count) $riskLevels[$level] = $count;

        return ['creditRisk' => $creditRisk, 'riskLevels' => $riskLevels];
    }

    // ── TAB: TOP OUTLETS ─────────────────────────────────────────────
    protected function loadTabTopOutlets($query, Request $request): array
    {
        $topOutlets = (clone $query)->where('ar_balance', '>', 0)
            ->selectRaw('outlet_code, outlet_name, salesman_name, SUM(ar_balance) as total_balance, MAX(credit_limit) as credit_limit, MAX(overdue_days) as max_overdue, MAX(cm) as max_cm, COUNT(*) as invoice_count')
            ->groupBy('outlet_code', 'outlet_name', 'salesman_name')
            ->orderByDesc('total_balance')->paginate(20)->appends($request->query());

        return ['topOutlets' => $topOutlets];
    }

    // ── TAB: PAYMENT BEHAVIOR ────────────────────────────────────────
    protected function loadTabPayment($query, Request $request): array
    {
        $paymentSummary = (clone $query)->where('ar_amount', '>', 0)->selectRaw('
            SUM(ar_amount) as total_invoiced, SUM(ar_paid) as total_paid, SUM(ar_balance) as total_balance,
            COUNT(CASE WHEN ar_paid = 0 AND ar_balance > 0 THEN 1 END) as zero_pay_count,
            COUNT(CASE WHEN ar_paid > 0 AND ar_balance > 0 THEN 1 END) as partial_pay_count,
            COUNT(CASE WHEN ar_balance <= 0 THEN 1 END) as full_pay_count
        ')->first();

        $worstPayers = (clone $query)->where('ar_amount', '>', 0)->where('ar_balance', '>', 0)
            ->selectRaw('outlet_code, outlet_name, salesman_name, COUNT(*) as invoice_count, SUM(ar_amount) as total_invoiced, SUM(ar_paid) as total_paid, SUM(ar_balance) as total_balance, ROUND(SUM(ar_paid)/SUM(ar_amount)*100,1) as payment_pct, AVG(overdue_days) as avg_overdue, MAX(cm) as max_cm')
            ->groupBy('outlet_code', 'outlet_name', 'salesman_name')
            ->having('total_balance', '>', 0)->orderBy('payment_pct')
            ->paginate(20)->appends($request->query());

        return ['paymentSummary' => $paymentSummary, 'worstPayers' => $worstPayers];
    }

    // ── TAB: SALESMAN ────────────────────────────────────────────────
    protected function loadTabSalesman($query, Request $request): array
    {
        $salesmanAr = (clone $query)->where('ar_balance', '>', 0)
            ->selectRaw('salesman_code, salesman_name, SUM(ar_balance) as total_balance, COUNT(DISTINCT outlet_code) as outlet_count, MAX(overdue_days) as max_overdue, AVG(overdue_days) as avg_overdue, COUNT(*) as invoice_count, SUM(CASE WHEN cm >= 3 THEN 1 ELSE 0 END) as stubborn_invoices')
            ->groupBy('salesman_code', 'salesman_name')
            ->orderByDesc('total_balance')->get();

        return ['salesmanAr' => $salesmanAr];
    }

    // ── TAB: PRINCIPAL ───────────────────────────────────────────────
    protected function loadTabPrincipal($query, Request $request): array
    {
        $principalAr = (clone $query)->where('ar_balance', '>', 0)
            ->whereNotNull('principal_name')->where('principal_name', '!=', '')
            ->selectRaw('principal_name, SUM(ar_balance) as total_balance, COUNT(DISTINCT outlet_code) as outlet_count, COUNT(*) as invoice_count, AVG(overdue_days) as avg_overdue')
            ->groupBy('principal_name')
            ->orderByDesc('total_balance')->get();

        return ['principalAr' => $principalAr];
    }

    // ── TAB: GIRO ────────────────────────────────────────────────────
    protected function loadTabGiro($query, Request $request): array
    {
        $giroPerBank = (clone $query)->whereNotNull('giro_no')->where('giro_no', '!=', '')
            ->where('giro_amount', '>', 0)->whereNotNull('bank_name')
            ->selectRaw('bank_name, COUNT(DISTINCT giro_no) as giro_count, SUM(giro_amount) as total_amount')
            ->groupBy('bank_name')->orderByDesc('total_amount')->get();

        $giroList = (clone $query)->whereNotNull('giro_no')->where('giro_no', '!=', '')
            ->where('giro_amount', '>', 0)
            ->selectRaw('giro_no, outlet_code, outlet_name, salesman_name, bank_name, giro_amount, giro_due_date, ar_balance, pfi_sn')
            ->orderBy('giro_due_date')->paginate(20)->appends($request->query());

        return ['giroPerBank' => $giroPerBank, 'giroList' => $giroList];
    }

    // ── TAB: SUPERVISOR ──────────────────────────────────────────────
    protected function loadTabSupervisor($query, Request $request): array
    {
        $supervisorAr = (clone $query)->where('ar_balance', '>', 0)
            ->whereNotNull('supervisor')->where('supervisor', '!=', '')
            ->selectRaw('supervisor, COUNT(DISTINCT salesman_code) as salesman_count, COUNT(DISTINCT outlet_code) as outlet_count, SUM(ar_balance) as total_balance, AVG(overdue_days) as avg_overdue, MAX(overdue_days) as max_overdue, COUNT(*) as invoice_count')
            ->groupBy('supervisor')->orderByDesc('total_balance')->get();

        return ['supervisorAr' => $supervisorAr];
    }

    // ── TAB: TOP ANALYSIS ────────────────────────────────────────────
    protected function loadTabTop($query, Request $request): array
    {
        $topAnalysis = (clone $query)->where('ar_balance', '>', 0)->whereNotNull('top')->where('top', '>', 0)
            ->selectRaw('top as term_days, COUNT(*) as invoice_count, COUNT(DISTINCT outlet_code) as outlet_count, SUM(ar_balance) as total_balance, AVG(overdue_days) as avg_overdue, SUM(CASE WHEN overdue_days > 0 THEN 1 ELSE 0 END) as overdue_count')
            ->groupBy('top')->orderBy('top')->get();

        return ['topAnalysis' => $topAnalysis];
    }

    // ── TAB: DETAIL ──────────────────────────────────────────────────
    protected function loadTabDetail($query, Request $request): array
    {
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
        $details = $dq->orderByDesc('ar_balance')->paginate(25)->appends($request->query());

        return ['details' => $details];
    }
}
