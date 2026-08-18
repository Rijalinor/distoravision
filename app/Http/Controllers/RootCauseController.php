<?php

namespace App\Http\Controllers;

use App\Models\ArImportLog;
use App\Models\ArReceivable;
use App\Models\Principal;
use App\Models\SalesPerStock;
use App\Models\Transaction;
use App\Support\AnalyticsCache;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RootCauseController extends Controller
{
    public function index(Request $request)
    {
        $periods = Transaction::select('period')->distinct()->orderByDesc('period')->pluck('period');
        $latestPeriod = $periods->first() ?? date('Y-m');

        $startPeriod = $request->get('start_period', $request->get('period', $latestPeriod));
        $endPeriod = $request->get('end_period', $request->get('period', $latestPeriod));

        $startDate = Carbon::parse($startPeriod.'-01');
        $endDate = Carbon::parse($endPeriod.'-01');
        if ($startDate->gt($endDate)) {
            [$startDate, $endDate] = [$endDate, $startDate];
            [$startPeriod, $endPeriod] = [$endPeriod, $startPeriod];
        }

        $monthSpan = $startDate->diffInMonths($endDate) + 1;
        $prevStartPeriod = $startDate->copy()->subMonths($monthSpan)->format('Y-m');
        $prevEndPeriod = $endDate->copy()->subMonths($monthSpan)->format('Y-m');

        $prevRequest = new Request([
            'start_period' => $prevStartPeriod,
            'end_period' => $prevEndPeriod,
            'principal_id' => $request->get('principal_id', 'all'),
        ]);

        $cacheKey = AnalyticsCache::key('root_cause', [
            auth()->id(),
            auth()->user()->role,
            $startPeriod,
            $endPeriod,
            $request->get('principal_id', 'all'),
        ]);

        $data = cache()->remember($cacheKey, 1800, function () use ($request, $prevRequest, $periods, $startPeriod, $endPeriod, $prevStartPeriod, $prevEndPeriod, $monthSpan) {
            $principals = auth()->user()->isSupervisor()
                ? auth()->user()->principals()->orderBy('name')->get()
                : Principal::orderBy('name')->get();

            $currentKpis = $this->kpis($request);
            $previousKpis = $this->kpis($prevRequest);

            $movement = $this->movement($currentKpis, $previousKpis);

            $drivers = [
                'products' => $this->dimensionDrivers($request, $prevRequest, 'product')->take(8),
                'outlets' => $this->dimensionDrivers($request, $prevRequest, 'outlet')->take(8),
                'salesmen' => $this->dimensionDrivers($request, $prevRequest, 'salesman')->take(8),
                'principals' => $this->dimensionDrivers($request, $prevRequest, 'principal')->take(8),
            ];

            $returnDrivers = $this->returnDrivers($request, $prevRequest)->take(8);
            $stockSignals = $this->stockSignals($drivers['products'], $endPeriod);
            $arSignals = $this->arSignals($drivers['outlets']);

            $topDrivers = collect($drivers)->flatten(1)->sortByDesc('abs_delta')->take(5)->values();
            $aiNarrative = $this->narrative($movement, $topDrivers, $returnDrivers, $stockSignals, $arSignals, $startPeriod, $endPeriod, $prevStartPeriod, $prevEndPeriod);
            $rangeLabel = $monthSpan.' bulan';

            return compact(
                'periods',
                'principals',
                'startPeriod',
                'endPeriod',
                'prevStartPeriod',
                'prevEndPeriod',
                'rangeLabel',
                'currentKpis',
                'previousKpis',
                'movement',
                'drivers',
                'returnDrivers',
                'stockSignals',
                'arSignals',
                'topDrivers',
                'aiNarrative'
            );
        });

        return view('analytics.root-cause', $data);
    }

    private function kpis(Request $request): object
    {
        $row = Transaction::withFilters($request)
            ->selectRaw('
                COALESCE(SUM(CASE WHEN type = "I" THEN taxed_amt ELSE 0 END), 0) as gross_sales,
                COALESCE(SUM(CASE WHEN type = "R" THEN ABS(taxed_amt) ELSE 0 END), 0) as returns,
                COUNT(CASE WHEN type = "I" THEN 1 END) as invoice_count,
                COUNT(CASE WHEN type = "R" THEN 1 END) as return_count,
                COUNT(DISTINCT CASE WHEN type = "I" THEN outlet_id END) as active_outlets,
                COUNT(DISTINCT CASE WHEN type = "I" THEN product_id END) as active_skus
            ')
            ->first();

        $grossSales = (float) $row->gross_sales;
        $returns = (float) $row->returns;

        return (object) [
            'gross_sales' => $grossSales,
            'returns' => $returns,
            'net_sales' => $grossSales - $returns,
            'invoice_count' => (int) $row->invoice_count,
            'return_count' => (int) $row->return_count,
            'active_outlets' => (int) $row->active_outlets,
            'active_skus' => (int) $row->active_skus,
            'return_rate' => $grossSales > 0 ? ($returns / $grossSales) * 100 : 0,
        ];
    }

    private function movement(object $current, object $previous): array
    {
        return [
            'gross_sales' => $this->delta($current->gross_sales, $previous->gross_sales),
            'returns' => $this->delta($current->returns, $previous->returns),
            'net_sales' => $this->delta($current->net_sales, $previous->net_sales),
            'invoice_count' => $this->delta($current->invoice_count, $previous->invoice_count),
            'active_outlets' => $this->delta($current->active_outlets, $previous->active_outlets),
            'active_skus' => $this->delta($current->active_skus, $previous->active_skus),
            'return_rate' => $this->delta($current->return_rate, $previous->return_rate),
        ];
    }

    private function delta(float|int $current, float|int $previous): array
    {
        $delta = $current - $previous;

        return [
            'current' => $current,
            'previous' => $previous,
            'delta' => $delta,
            'abs_delta' => abs($delta),
            'pct' => $previous != 0 ? ($delta / abs($previous)) * 100 : null,
        ];
    }

    private function dimensionDrivers(Request $currentRequest, Request $previousRequest, string $dimension): Collection
    {
        $current = $this->dimensionRows($currentRequest, $dimension);
        $previous = $this->dimensionRows($previousRequest, $dimension)->keyBy('key');

        return $current->map(function ($row) use ($previous) {
            $prev = $previous->get($row->key);
            $prevNet = $prev ? (float) $prev->net_sales : 0;
            $delta = (float) $row->net_sales - $prevNet;

            return (object) [
                'key' => $row->key,
                'name' => $row->name,
                'meta' => $row->meta,
                'code' => $row->code,
                'current' => (float) $row->net_sales,
                'previous' => $prevNet,
                'delta' => $delta,
                'abs_delta' => abs($delta),
                'gross_sales' => (float) $row->gross_sales,
                'returns' => (float) $row->returns,
                'invoice_count' => (int) $row->invoice_count,
            ];
        })->sortByDesc('abs_delta')->values();
    }

    private function dimensionRows(Request $request, string $dimension): Collection
    {
        $query = Transaction::withFilters($request);

        match ($dimension) {
            'product' => $query
                ->join('products', 'transactions.product_id', '=', 'products.id')
                ->leftJoin('principals', 'products.principal_id', '=', 'principals.id')
                ->selectRaw('products.id as row_key, products.name as row_name, products.item_no as row_code, COALESCE(principals.name, "-") as row_meta'),
            'outlet' => $query
                ->join('outlets', 'transactions.outlet_id', '=', 'outlets.id')
                ->selectRaw('outlets.id as row_key, outlets.name as row_name, outlets.code as row_code, COALESCE(outlets.city, "-") as row_meta'),
            'salesman' => $query
                ->leftJoin('salesmen', 'transactions.salesman_id', '=', 'salesmen.id')
                ->selectRaw('COALESCE(salesmen.id, 0) as row_key, COALESCE(salesmen.name, "Tanpa Salesman") as row_name, COALESCE(salesmen.sales_code, "-") as row_code, "-" as row_meta'),
            'principal' => $query
                ->join('products', 'transactions.product_id', '=', 'products.id')
                ->leftJoin('principals', 'products.principal_id', '=', 'principals.id')
                ->selectRaw('COALESCE(principals.id, 0) as row_key, COALESCE(principals.name, "Tanpa Principal") as row_name, "-" as row_code, "-" as row_meta'),
        };

        return $query
            ->selectRaw('
                COALESCE(SUM(CASE WHEN transactions.type = "I" THEN transactions.taxed_amt ELSE 0 END), 0) as gross_sales,
                COALESCE(SUM(CASE WHEN transactions.type = "R" THEN ABS(transactions.taxed_amt) ELSE 0 END), 0) as returns,
                COALESCE(SUM(CASE WHEN transactions.type = "I" THEN transactions.taxed_amt WHEN transactions.type = "R" THEN -ABS(transactions.taxed_amt) ELSE 0 END), 0) as net_sales,
                COUNT(CASE WHEN transactions.type = "I" THEN 1 END) as invoice_count
            ')
            ->groupBy('row_key', 'row_name', 'row_code', 'row_meta')
            ->havingRaw('ABS(net_sales) > 0 OR invoice_count > 0')
            ->get()
            ->map(fn ($row) => (object) [
                'key' => (string) $row->row_key,
                'name' => $row->row_name,
                'code' => $row->row_code,
                'meta' => $row->row_meta,
                'gross_sales' => $row->gross_sales,
                'returns' => $row->returns,
                'net_sales' => $row->net_sales,
                'invoice_count' => $row->invoice_count,
            ]);
    }

    private function returnDrivers(Request $currentRequest, Request $previousRequest): Collection
    {
        $current = $this->returnRows($currentRequest);
        $previous = $this->returnRows($previousRequest)->keyBy('key');

        return $current->map(function ($row) use ($previous) {
            $prev = $previous->get($row->key);
            $prevReturn = $prev ? (float) $prev->returns : 0;
            $delta = (float) $row->returns - $prevReturn;

            return (object) [
                'key' => $row->key,
                'name' => $row->name,
                'meta' => $row->meta,
                'current' => (float) $row->returns,
                'previous' => $prevReturn,
                'delta' => $delta,
                'abs_delta' => abs($delta),
            ];
        })->filter(fn ($row) => $row->current > 0 || $row->previous > 0)
            ->sortByDesc('delta')
            ->values();
    }

    private function returnRows(Request $request): Collection
    {
        return Transaction::withFilters($request)
            ->join('products', 'transactions.product_id', '=', 'products.id')
            ->leftJoin('principals', 'products.principal_id', '=', 'principals.id')
            ->where('transactions.type', 'R')
            ->selectRaw('products.id as row_key, products.name as row_name, COALESCE(principals.name, "-") as row_meta, COALESCE(SUM(ABS(transactions.taxed_amt)), 0) as returns')
            ->groupBy('row_key', 'row_name', 'row_meta')
            ->get()
            ->map(fn ($row) => (object) [
                'key' => (string) $row->row_key,
                'name' => $row->row_name,
                'meta' => $row->row_meta,
                'returns' => $row->returns,
            ]);
    }

    private function stockSignals(Collection $productDrivers, string $period): Collection
    {
        $codes = $productDrivers->where('delta', '<', 0)->pluck('code')->filter()->values();

        if ($codes->isEmpty()) {
            return collect();
        }

        return SalesPerStock::where('period', $period)
            ->whereIn('item_no', $codes)
            ->where(function (Builder $query) {
                $query->where('swc', '<=', 2)
                    ->orWhere('on_hand_base', '<=', 0);
            })
            ->select('item_no', 'item_name', 'warehouse_name', 'on_hand_base', 'swc', 'stock_value_on_hand')
            ->orderBy('swc')
            ->limit(8)
            ->get();
    }

    private function arSignals(Collection $outletDrivers): Collection
    {
        $codes = $outletDrivers->where('delta', '<', 0)->pluck('code')->filter()->values();
        $latestImport = ArImportLog::where('status', 'completed')->orderByDesc('report_date')->first();

        if ($codes->isEmpty() || ! $latestImport) {
            return collect();
        }

        return ArReceivable::where('ar_import_log_id', $latestImport->id)
            ->overdue()
            ->whereIn('outlet_code', $codes)
            ->select('outlet_code', 'outlet_name', 'salesman_name', DB::raw('SUM(ar_balance) as ar_balance'), DB::raw('MAX(overdue_days) as max_overdue_days'))
            ->groupBy('outlet_code', 'outlet_name', 'salesman_name')
            ->orderByDesc('ar_balance')
            ->limit(8)
            ->get();
    }

    private function narrative(array $movement, Collection $topDrivers, Collection $returnDrivers, Collection $stockSignals, Collection $arSignals, string $startPeriod, string $endPeriod, string $prevStartPeriod, string $prevEndPeriod): string
    {
        $net = $movement['net_sales'];
        $direction = $net['delta'] >= 0 ? 'naik' : 'turun';
        $rangeText = Carbon::parse($startPeriod.'-01')->translatedFormat('M Y').' - '.Carbon::parse($endPeriod.'-01')->translatedFormat('M Y');
        $prevText = Carbon::parse($prevStartPeriod.'-01')->translatedFormat('M Y').' - '.Carbon::parse($prevEndPeriod.'-01')->translatedFormat('M Y');

        $mainDriver = $topDrivers->first();
        $mainDriverText = $mainDriver
            ? ' Driver terbesar: '.$mainDriver->name.' ('.($mainDriver->delta >= 0 ? '+' : '-').'Rp '.number_format(abs($mainDriver->delta), 0, ',', '.').').'
            : ' Belum ada driver dimensi yang cukup kuat.';

        $returnText = $returnDrivers->first() && $returnDrivers->first()->delta > 0
            ? ' Retur naik paling besar di '.$returnDrivers->first()->name.' (+Rp '.number_format($returnDrivers->first()->delta, 0, ',', '.').').'
            : ' Retur tidak menjadi tekanan utama pada range ini.';

        $signalText = ($stockSignals->count() || $arSignals->count())
            ? ' Ada sinyal lanjutan yang perlu dicek: '.$stockSignals->count().' stok berisiko dan '.$arSignals->count().' outlet dengan AR overdue.'
            : ' Belum ada sinyal stok/AR yang jelas pada driver penurunan utama.';

        return 'Fakta: Net Sales '.$rangeText.' '.$direction.' Rp '.number_format(abs($net['delta']), 0, ',', '.').' dibanding '.$prevText.'.'.$mainDriverText.$returnText.$signalText;
    }
}
