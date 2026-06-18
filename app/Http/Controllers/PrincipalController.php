<?php

namespace App\Http\Controllers;

use App\Models\Principal;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class PrincipalController extends Controller implements HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            new Middleware(function ($request, $next) {
                abort_if(auth()->check() && auth()->user()->isSalesman(), 403, 'Akses ditolak. Salesman tidak dapat melihat data Principal.');

                return $next($request);
            }),
        ];
    }

    public function index(Request $request)
    {
        $period = $request->get('period', Transaction::max('period') ?? date('Y-m'));
        $periods = Transaction::select('period')->distinct()->orderByDesc('period')->pluck('period');

        $principals = Principal::select('principals.*')
            ->selectSub(
                Transaction::join('products', 'transactions.product_id', '=', 'products.id')
                    ->whereColumn('products.principal_id', 'principals.id')
                    ->where('transactions.type', 'I')
                    ->withFilters(request())
                    ->selectRaw('COALESCE(SUM(transactions.taxed_amt), 0)'),
                'total_sales'
            )
            ->selectSub(
                Transaction::join('products', 'transactions.product_id', '=', 'products.id')
                    ->whereColumn('products.principal_id', 'principals.id')
                    ->where('transactions.type', 'R')
                    ->withFilters(request())
                    ->selectRaw('COALESCE(SUM(ABS(transactions.taxed_amt)), 0)'),
                'total_returns'
            )
            ->selectSub(
                Transaction::join('products', 'transactions.product_id', '=', 'products.id')
                    ->whereColumn('products.principal_id', 'principals.id')
                    ->where('transactions.type', 'I')
                    ->withFilters(request())
                    ->selectRaw('COUNT(DISTINCT transactions.outlet_id)'),
                'outlet_reach'
            )
            ->orderByDesc('total_sales')
            ->get();

        return view('principals.index', compact('principals', 'period', 'periods'));
    }

    public function show(Request $request, Principal $principal)
    {
        if (auth()->user()->isSupervisor() && ! auth()->user()->principals->contains($principal->id)) {
            abort(403, 'Akses ditolak. Anda tidak memiliki akses ke Principal ini.');
        }

        $period = $request->get('period', Transaction::max('period') ?? date('Y-m'));
        $periods = Transaction::select('period')->distinct()->orderByDesc('period')->pluck('period');

        $stats = [
            'total_sales' => Transaction::whereHas('product', fn ($q) => $q->where('principal_id', $principal->id))
                ->withFilters(request())->invoices()->sum('taxed_amt'),
            'total_returns' => Transaction::whereHas('product', fn ($q) => $q->where('principal_id', $principal->id))
                ->withFilters(request())->returns()->sum(DB::raw('ABS(taxed_amt)')),
            'product_count' => $principal->products()->count(),
            'outlet_reach' => Transaction::whereHas('product', fn ($q) => $q->where('principal_id', $principal->id))
                ->withFilters(request())->distinct('outlet_id')->count('outlet_id'),
        ];

        $topProducts = Transaction::withFilters(request())->invoices()
            ->join('products', 'transactions.product_id', '=', 'products.id')
            ->where('products.principal_id', $principal->id)
            ->select('products.name', DB::raw('SUM(transactions.taxed_amt) as total'), DB::raw('SUM(transactions.qty_base) as qty'))
            ->groupBy('products.name')->orderByDesc('total')->limit(15)->get();

        $returnedProducts = Transaction::withFilters(request())->returns()
            ->join('products', 'transactions.product_id', '=', 'products.id')
            ->where('products.principal_id', $principal->id)
            ->select('products.name', DB::raw('SUM(ABS(transactions.taxed_amt)) as total'), DB::raw('SUM(ABS(transactions.qty_base)) as qty'))
            ->groupBy('products.name')->orderByDesc('total')->limit(10)->get();

        return view('principals.show', compact('principal', 'period', 'periods', 'stats', 'topProducts', 'returnedProducts'));
    }
}
