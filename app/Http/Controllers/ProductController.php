<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ProductController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware(function ($request, $next) {
                abort_if(auth()->check() && auth()->user()->isSalesman(), 403, 'Akses ditolak. Salesman tidak dapat melihat data Produk.');

                return $next($request);
            }),
        ];
    }

    public function index(Request $request)
    {
        $period = $request->get('period', Transaction::max('period') ?? date('Y-m'));
        $periods = Transaction::select('period')->distinct()->orderByDesc('period')->pluck('period');
        $search = $request->get('search', '');

        $query = Product::select('products.*')
            ->join('principals', 'products.principal_id', '=', 'principals.id')
            ->addSelect('principals.name as principal_name')
            ->selectSub(
                Transaction::whereColumn('transactions.product_id', 'products.id')
                    ->where('type', 'I')->withFilters(request())
                    ->selectRaw('COALESCE(SUM(taxed_amt), 0)'),
                'total_sales'
            )
            ->selectSub(
                Transaction::whereColumn('transactions.product_id', 'products.id')
                    ->where('type', 'R')->withFilters(request())
                    ->selectRaw('COALESCE(SUM(ABS(taxed_amt)), 0)'),
                'total_returns'
            )
            ->selectSub(
                Transaction::whereColumn('transactions.product_id', 'products.id')
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
