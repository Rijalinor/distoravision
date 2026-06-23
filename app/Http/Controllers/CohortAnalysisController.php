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
        // 1. Get first transaction month for each outlet
        $cohorts = DB::table('transactions')
            ->select('outlet_id', DB::raw('MIN(period) as cohort_month'))
            ->groupBy('outlet_id')
            ->get()
            ->keyBy('outlet_id');

        // 2. Get distinct transactions per outlet per period
        $allTxns = DB::table('transactions')
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
