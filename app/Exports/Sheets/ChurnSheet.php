<?php

namespace App\Exports\Sheets;

use App\Models\Transaction;
use App\Models\Outlet;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class ChurnSheet implements FromArray, WithTitle, WithStyles, ShouldAutoSize
{
    protected Request $request;
    protected string $period;

    public function __construct(Request $request, string $period)
    {
        $this->request = $request;
        $this->period  = $period;
    }

    public function title(): string { return '🔴 Toko Berhenti (Churn)'; }

    public function array(): array
    {
        $prevPeriod  = \Carbon\Carbon::parse($this->period . '-01')->subMonth()->format('Y-m');
        $prevRequest = new \Illuminate\Http\Request();
        $prevRequest->merge(['start_period' => $prevPeriod, 'end_period' => $prevPeriod, 'principal_id' => $this->request->get('principal_id')]);

        $prevOutlets = Transaction::withFilters($prevRequest)->invoices()
            ->select('outlet_id', DB::raw('SUM(ar_amt) as prev_sales'), DB::raw('MAX(so_date) as last_order'))
            ->groupBy('outlet_id')
            ->having('prev_sales', '>', 0)
            ->get()->keyBy('outlet_id');

        $currentOutlets = Transaction::withFilters($this->request)->invoices()
            ->groupBy('outlet_id')
            ->having(DB::raw('SUM(ar_amt)'), '>', 0)
            ->pluck('outlet_id')->unique()->toArray();

        $churnedIds = $prevOutlets->keys()->diff($currentOutlets);

        $rows = [
            ['DAFTAR TOKO BERHENTI (CHURN) - PERIODE ' . $this->period],
            ['Toko yang aktif pada ' . $prevPeriod . ' namun tidak ada transaksi sama sekali di ' . $this->period],
            [],
            ['#', 'NAMA TOKO', 'KOTA', 'WILAYAH', 'SALESMAN TERAKHIR', 'OMSET BLN LALU (Rp)', 'TANGGAL ORDER TERAKHIR', 'STATUS'],
        ];

        if ($churnedIds->isNotEmpty()) {
            $outlets = Outlet::whereIn('id', $churnedIds)->get()->keyBy('id');

            // Fetch last salesman per outlet from transactions table directly
            $lastSalesmanMap = \Illuminate\Support\Facades\DB::table('transactions')
                ->join('salesmen', 'transactions.salesman_id', '=', 'salesmen.id')
                ->whereIn('transactions.outlet_id', $churnedIds->toArray())
                ->select('transactions.outlet_id', 'salesmen.name as salesman_name')
                ->orderByDesc('transactions.so_date')
                ->get()->unique('outlet_id')->keyBy('outlet_id');

            $i = 1;
            foreach ($churnedIds as $id) {
                $outlet   = $outlets[$id] ?? null;
                if (!$outlet) continue;
                $prev     = $prevOutlets[$id];
                $salesman = $lastSalesmanMap[$id]->salesman_name ?? '-';
                $region   = strlen($outlet->code ?? '') >= 3 ? strtoupper(substr($outlet->code, 0, 3)) : '-';

                $rows[] = [
                    $i++,
                    $outlet->name,
                    $outlet->city ?? '-',
                    $region,
                    $salesman,
                    number_format($prev->prev_sales, 0, ',', '.'),
                    $prev->last_order ?? '-',
                    '🔴 CHURN - Perlu Follow Up',
                ];
            }
        } else {
            $rows[] = ['', 'Tidak ada toko yang churn di periode ini.', '', '', '', '', '', ''];
        }

        $totalLoss = 0;
        foreach ($churnedIds as $cid) {
            $totalLoss += $prevOutlets[$cid]->prev_sales ?? 0;
        }
        $rows[] = [];
        $rows[] = ['TOTAL OPPORTUNITY LOSS', '', '', '', '', number_format($totalLoss, 0, ',', '.'), '', ''];

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $blue  = ['argb' => 'FF1E3A5F'];
        $red   = ['argb' => 'FF7F1D1D'];
        $white = ['argb' => 'FFFFFFFF'];
        $gold  = ['argb' => 'FFD4AC0D'];

        return [
            1 => ['font' => ['bold' => true, 'size' => 13, 'color' => $white], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => $red]],
            2 => ['font' => ['italic' => true, 'color' => ['argb' => 'FFEF4444']], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF1E293B']]],
            4 => ['font' => ['bold' => true, 'color' => $white], 'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => $blue]],
        ];
    }
}
