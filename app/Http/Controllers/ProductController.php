<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $period = $request->get('period', Transaction::max('period') ?? date('Y-m'));
        $periods = Transaction::select('period')->distinct()->orderByDesc('period')->pluck('period');
        $search = $request->get('search', '');

        $query = Product::select('products.*')
            ->join('principals', 'products.principal_id', '=', 'principals.id')
            ->addSelect('principals.name as principal_name')
            ->selectSub(
                \App\Models\Transaction::whereColumn('transactions.product_id', 'products.id')
                    ->where('type', 'I')->withFilters(request())
                    ->selectRaw('COALESCE(SUM(ar_amt), 0)'),
                'total_sales'
            )
            ->selectSub(
                \App\Models\Transaction::whereColumn('transactions.product_id', 'products.id')
                    ->where('type', 'R')->withFilters(request())
                    ->selectRaw('COALESCE(SUM(ABS(ar_amt)), 0)'),
                'total_returns'
            )
            ->selectSub(
                \App\Models\Transaction::whereColumn('transactions.product_id', 'products.id')
                    ->where('type', 'I')->withFilters(request())
                    ->selectRaw('COALESCE(SUM(qty_base), 0)'),
                'total_qty'
            );

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('products.name', 'like', "%{$search}%")
                  ->orWhere('products.item_no', 'like', "%{$search}%");
            });
        }

        $products = $query->orderByDesc('total_sales')->paginate(20)->appends($request->query());

        return view('products.index', compact('products', 'period', 'periods', 'search'));
    }
}
