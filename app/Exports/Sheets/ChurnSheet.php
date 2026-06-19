<?php

namespace App\Exports\Sheets;

use App\Models\Outlet;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

class ChurnSheet implements FromArray, ShouldAutoSize, WithEvents, WithTitle
{
    use ExcelStyler;

    protected Request $request;

    protected string $period;

    protected int $dataRowCount = 0;

    public function __construct(Request $request, string $period)
    {
        $this->request = $request;
        $this->period = $period;
    }

    public function title(): string
    {
        return 'Toko Berhenti (Churn)';
    }

    public function array(): array
    {
        $prevPeriod = Carbon::parse($this->period.'-01')->subMonth()->format('Y-m');
        $prevRequest = new Request;
        $prevRequest->merge(['start_period' => $prevPeriod, 'end_period' => $prevPeriod, 'principal_id' => $this->request->get('principal_id')]);

        $prevOutlets = Transaction::withFilters($prevRequest)
            ->select('outlet_id', DB::raw('SUM(CASE WHEN type = "I" THEN taxed_amt WHEN type = "R" THEN -ABS(taxed_amt) ELSE 0 END) as prev_sales'), DB::raw('MAX(CASE WHEN type = "I" THEN so_date END) as last_order'))
            ->groupBy('outlet_id')
            ->having('prev_sales', '>', 0)
            ->get()->keyBy('outlet_id');

        $currentOutlets = Transaction::withFilters($this->request)
            ->groupBy('outlet_id')
            ->having(DB::raw('SUM(CASE WHEN type = "I" THEN taxed_amt WHEN type = "R" THEN -ABS(taxed_amt) ELSE 0 END)'), '>', 0)
            ->pluck('outlet_id')->unique()->toArray();

        $churnedIds = $prevOutlets->keys()->diff($currentOutlets);

        $rows = [
            ['DAFTAR TOKO BERHENTI (CHURN) — PERIODE '.$this->period, '', '', '', '', '', '', ''],
            ['Toko yang aktif pada '.$prevPeriod.' namun tidak ada transaksi sama sekali di '.$this->period, '', '', '', '', '', '', ''],
            [],
            ['#', 'NAMA TOKO', 'KOTA', 'WILAYAH', 'SALESMAN TERAKHIR', 'OMSET BLN LALU (Rp)', 'TANGGAL ORDER TERAKHIR', 'STATUS'],
        ];

        if ($churnedIds->isNotEmpty()) {
            $outlets = Outlet::whereIn('id', $churnedIds)->get()->keyBy('id');

            // Fetch last salesman per outlet from transactions table directly
            $lastSalesmanMap = DB::table('transactions')
                ->join('salesmen', 'transactions.salesman_id', '=', 'salesmen.id')
                ->whereIn('transactions.outlet_id', $churnedIds->toArray())
                ->select('transactions.outlet_id', 'salesmen.name as salesman_name')
                ->orderByDesc('transactions.so_date')
                ->get()->unique('outlet_id')->keyBy('outlet_id');

            $i = 1;
            foreach ($churnedIds as $id) {
                $outlet = $outlets[$id] ?? null;
                if (! $outlet) {
                    continue;
                }
                $prev = $prevOutlets[$id];
                $salesman = $lastSalesmanMap[$id]->salesman_name ?? '-';
                $region = strlen($outlet->code ?? '') >= 3 ? strtoupper(substr($outlet->code, 0, 3)) : '-';

                $rows[] = [
                    $i++,
                    $outlet->name,
                    $outlet->city ?? '-',
                    $region,
                    $salesman,
                    (float) $prev->prev_sales,
                    $prev->last_order ?? '-',
                    'CHURN',
                ];
            }
        } else {
            $rows[] = ['', 'Tidak ada toko yang churn di periode ini.', '', '', '', '', '', ''];
        }

        $this->dataRowCount = max(0, $i - 1);

        $totalLoss = 0;
        foreach ($churnedIds as $cid) {
            $totalLoss += $prevOutlets[$cid]->prev_sales ?? 0;
        }
        $rows[] = [];
        $rows[] = ['TOTAL OPPORTUNITY LOSS', '', '', '', '', (float) $totalLoss, '', ''];

        // Formula notes
        $rows[] = [];
        $rows[] = ['CATATAN RUMUS & METODOLOGI', '', '', '', '', '', '', ''];
        $rows[] = ['1. Deteksi Churn', 'Outlet yang memiliki Net Sales > 0 di bulan T-1 tetapi tidak ada transaksi di bulan T.', '', '', '', '', '', ''];
        $rows[] = ['2. Opportunity Loss', 'SUM Net Sales bulan T-1 dari semua outlet yang churn.', '', '', '', '', '', ''];
        $rows[] = ['3. Net Sales', 'SUM(Invoice taxed_amt) - SUM(ABS(Return taxed_amt)) per outlet.', '', '', '', '', '', ''];
        $rows[] = ['4. Interpretasi', 'Outlet churn = potensi pendapatan yang hilang. Perlu ditindaklanjuti oleh tim sales.', '', '', '', '', '', ''];

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $ws = $event->sheet->getDelegate();
                $lastDataRow = 4 + $this->dataRowCount;
                $totalRow = $lastDataRow + 2;

                $this->styleTitle($ws, 'A1:H1');
                $ws->getRowDimension(1)->setRowHeight(28);
                $this->styleSubtitle($ws, 'A2:H2');
                $ws->getRowDimension(2)->setRowHeight(18);

                $this->styleColHeader($ws, 'A4:H4');
                $ws->getRowDimension(4)->setRowHeight(30);

                if ($this->dataRowCount > 0) {
                    $this->styleDataRows($ws, 5, $lastDataRow, 'H');
                    $this->formatCurrencyCol($ws, "F5:F{$lastDataRow}");
                    $ws->getStyle("A5:A{$lastDataRow}")->getAlignment()->setHorizontal('center');
                    $ws->getStyle("D5:D{$lastDataRow}")->getAlignment()->setHorizontal('center');
                    $ws->getStyle("G5:G{$lastDataRow}")->getAlignment()->setHorizontal('center');
                    $ws->getStyle("H5:H{$lastDataRow}")->getAlignment()->setHorizontal('center');
                }

                $this->styleTotalsRow($ws, "A{$totalRow}:H{$totalRow}");
                $this->formatCurrencyCol($ws, "F{$totalRow}");

                $this->outerBorder($ws, "A4:H{$totalRow}");
                $ws->freezePane('A5');

                // Style notes block (starts at $totalRow + 2, count 5 rows)
                $this->styleNotesBlock($ws, $totalRow + 2, 5, 'H');
            },
        ];
    }
}
