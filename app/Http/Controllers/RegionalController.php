<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RegionalController extends Controller
{
    public function index(Request $request)
    {
        $period = $request->get('period', Transaction::max('period') ?? date('Y-m'));
        $periods = Transaction::select('period')->distinct()->orderByDesc('period')->pluck('period');

        $cities = Transaction::withFilters(request())
            ->join('outlets', 'transactions.outlet_id', '=', 'outlets.id')
            ->select(
                DB::raw('LEFT(outlets.code, 3) as region_code'),
                DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.taxed_amt ELSE 0 END) as total_sales'),
                DB::raw('SUM(CASE WHEN transactions.type = "R" THEN ABS(transactions.taxed_amt) ELSE 0 END) as total_returns'),
                DB::raw('COUNT(DISTINCT CASE WHEN transactions.type = "I" THEN transactions.outlet_id END) as outlet_count'),
                DB::raw('COUNT(DISTINCT CASE WHEN transactions.type = "I" THEN transactions.salesman_id END) as salesman_count'),
                DB::raw('COUNT(CASE WHEN transactions.type = "I" THEN 1 END) as trx_count')
            )
            ->whereNotNull('outlets.code')
            ->whereRaw('LENGTH(outlets.code) >= 3')
            ->groupBy('region_code')
            ->orderByDesc('total_sales')
            ->get();

        return view('regional.index', compact('cities', 'period', 'periods'));
    }
}

