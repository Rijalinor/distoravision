<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class CohortAnalysisController extends Controller implements HasMiddleware
{
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

    public function cohortAnalysis(Request $request)
    {
        $baseQuery = Transaction::query()->invoices();

        if ($request->filled('start_period')) {
            $baseQuery->where('period', '>=', $request->get('start_period'));
        }
        if ($request->filled('end_period')) {
            $baseQuery->where('period', '<=', $request->get('end_period'));
        }
        if ($request->filled('period')) {
            $baseQuery->where('period', $request->get('period'));
        }
        if ($request->filled('principal_id') && $request->get('principal_id') !== 'all') {
            $baseQuery->whereHas('product', fn ($q) => $q->where('principal_id', $request->get('principal_id')));
        }

        // 1. Get first transaction month for each outlet
        $cohorts = (clone $baseQuery)
            ->select('outlet_id', DB::raw('MIN(period) as cohort_month'))
            ->groupBy('outlet_id')
            ->get()
            ->keyBy('outlet_id');

        // 2. Get distinct transactions per outlet per period
        $allTxns = (clone $baseQuery)
            ->select('outlet_id', 'period')
            ->distinct()
            ->orderBy('period')
            ->get();

        $matrix = [];
        $periods = [];

        foreach ($allTxns as $txn) {
            $period = $txn->period;
            if (! in_array($period, $periods)) {
                $periods[] = $period;
            }

            $cohortMonth = $cohorts[$txn->outlet_id]->cohort_month ?? null;
            if (! $cohortMonth) {
                continue;
            }

            if (! isset($matrix[$cohortMonth])) {
                $matrix[$cohortMonth] = [];
            }
            if (! isset($matrix[$cohortMonth][$period])) {
                $matrix[$cohortMonth][$period] = 0;
            }

            $matrix[$cohortMonth][$period]++;
        }

        sort($periods);
        ksort($matrix);

        return view('analytics.cohort', compact('matrix', 'periods'));
    }
}
