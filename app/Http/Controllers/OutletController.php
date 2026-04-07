<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OutletController extends Controller
{
    public function index(Request $request)
    {
        $period = $request->get('period', Transaction::max('period') ?? date('Y-m'));
        $periods = Transaction::select('period')->distinct()->orderByDesc('period')->pluck('period');
        $search = $request->get('search', '');
        $city = $request->get('city', '');

        $cities = Outlet::whereNotNull('city')->where('city', '!=', '')->distinct()->orderBy('city')->pluck('city');

        $query = Outlet::select('outlets.*')
            ->withCount([
                'transactions as trx_count' => fn($q) => $q->where('type', 'I')->withFilters(request()),
            ])
            ->withSum([
                'transactions as total_sales' => fn($q) => $q->where('type', 'I')->withFilters(request()),
            ], 'ar_amt');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%")
                  ->orWhere('city', 'like', "%{$search}%");
            });
        }

        if ($city) {
            $query->where('city', $city);
        }

        $outlets = $query->orderByDesc('total_sales')->paginate(20)->appends($request->query());

        return view('outlets.index', compact('outlets', 'period', 'periods', 'search', 'city', 'cities'));
    }

    public function show(Request $request, Outlet $outlet)
    {
        $period = $request->get('period', Transaction::max('period') ?? date('Y-m'));
        $periods = Transaction::select('period')->distinct()->orderByDesc('period')->pluck('period');

        $stats = [
            'total_sales' => Transaction::where('outlet_id', $outlet->id)->withFilters(request())->invoices()->sum('ar_amt'),
            'total_returns' => Transaction::where('outlet_id', $outlet->id)->withFilters(request())->returns()->sum(DB::raw('ABS(ar_amt)')),
            'product_count' => Transaction::where('outlet_id', $outlet->id)->withFilters(request())->distinct('product_id')->count('product_id'),
        ];

        $purchaseHistory = Transaction::where('transactions.outlet_id', $outlet->id)
            ->withFilters(request())
            ->join('products', 'transactions.product_id', '=', 'products.id')
            ->join('principals', 'products.principal_id', '=', 'principals.id')
            ->select('products.name as product_name', 'principals.name as principal_name',
                'transactions.type', 'transactions.qty_base', 'transactions.ar_amt', 'transactions.so_date')
            ->orderByDesc('transactions.so_date')
            ->limit(50)->get();

        return view('outlets.show', compact('outlet', 'period', 'periods', 'stats', 'purchaseHistory'));
    }
}
