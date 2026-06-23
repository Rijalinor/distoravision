<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Traits\CsvExportable;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class ParetoController extends Controller implements HasMiddleware
{
    use CsvExportable;

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

    /**
     * Display Pareto Analysis (80/20 Rule) for Products and Outlets
     */
    public function pareto(Request $request)
    {
        $period = $request->get('period', Transaction::max('period') ?? date('Y-m'));
        $periods = Transaction::select('period')->distinct()->orderByDesc('period')->pluck('period');

        $type = $request->get('type', 'product'); // 'product' or 'outlet'

        if ($type === 'product') {
            $data = Transaction::withFilters(request())->invoices()
                ->join('products', 'transactions.product_id', '=', 'products.id')
                ->select('products.name', DB::raw('SUM(transactions.taxed_amt) as total_sales'))
                ->groupBy('products.name')
                ->having('total_sales', '>', 0)
                ->orderByDesc('total_sales')
                ->get();
        } else {
            $data = Transaction::withFilters(request())->invoices()
                ->join('outlets', 'transactions.outlet_id', '=', 'outlets.id')
                ->select('outlets.name', DB::raw('SUM(transactions.taxed_amt) as total_sales'))
                ->groupBy('outlets.name')
                ->having('total_sales', '>', 0)
                ->orderByDesc('total_sales')
                ->get();
        }

        // Calculate Cumulative %
        $totalRevenue = $data->sum('total_sales');

        $cumulative = 0;
        $paretoData = [];
        $classA = [];
        $classB = [];
        $classC = [];

        foreach ($data as $item) {
            $sales = (float) $item->total_sales;
            $percent = $totalRevenue > 0 ? ($sales / $totalRevenue) * 100 : 0;
            $cumulative += $percent;

            $itemData = [
                'name' => $item->name,
                'sales' => $sales,
                'percent' => $percent,
                'cumulative' => $cumulative,
            ];

            $paretoData[] = $itemData;

            if ($cumulative <= 80) {
                $classA[] = $itemData;
            } elseif ($cumulative <= 95) {
                $classB[] = $itemData;
            } else {
                $classC[] = $itemData;
            }
        }

        // Take top 50 for the chart so it doesn't get too heavy
        $chartData = array_slice($paretoData, 0, 50);

        // Pagination for the table
        $page = $request->get('page', 1);
        $perPage = 100; // Match the screenshot's expectation
        $offset = ($page * $perPage) - $perPage;

        $paginatedItems = array_slice($paretoData, $offset, $perPage);
        $paretoPaginator = new LengthAwarePaginator(
            $paginatedItems,
            count($paretoData),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        // --- DISTORA AI NARRATIVE GENERATOR ---
        $countA = count($classA);
        $pctA = $countA > 0 && count($data) > 0 ? ($countA / count($data)) * 100 : 0;

        $entityName = $type === 'product' ? 'Produk/SKU' : 'Outlet/Toko';
        $aiNarrative = "🔍 Fakta: Secara mengejutkan, hanya $countA $entityName (".number_format($pctA, 1)."% dari total elemen aktif) yang menyumbang 80% pendapatan utama (Kelas A Pareto).\n".
                       "💪 Kelebihan: Efisiensi tinggi! Tim bisa fokus hanya merawat $countA aset VIP ini untuk mendapat 80% omset perusahaan.\n".
                       "⚠️ Risiko & Saran: Ini bahaya ketergantungan ekstrem! Jika terjadi kelangkaan barang pada Top 3 $entityName, omset bulan depan akan hancur total. Segera matangkan strategi penetrasi untuk $entityName Kelas B.";

        // --- CSV EXPORT ---
        if ($request->get('export') === 'csv') {
            $rows = array_map(fn ($item) => [
                $item['name'],
                $item['sales'],
                round($item['percent'], 2),
                round($item['cumulative'], 2),
                $item['cumulative'] <= 80 ? 'A' : ($item['cumulative'] <= 95 ? 'B' : 'C'),
            ], $paretoData);

            return $this->streamCsv(
                "Pareto_{$type}_{$period}.csv",
                ['Nama', 'Total Sales', '% Kontribusi', '% Kumulatif', 'Kelas'],
                $rows
            );
        }

        return view('analytics.pareto', compact(
            'period', 'periods', 'type', 'paretoData', 'chartData',
            'classA', 'classB', 'classC', 'totalRevenue', 'aiNarrative', 'paretoPaginator'
        ));
    }
}
