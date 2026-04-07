<?php

namespace App\Exports\Sheets;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class OutletSheet implements FromArray, WithTitle, WithStyles, WithColumnWidths, WithEvents
{
    use ExcelStyler;

    protected Request $request;
    protected string $period;
    protected int $dataRowCount = 0;

    public function __construct(Request $request, string $period)
    {
        $this->request = $request;
        $this->period  = $period;
    }

    public function title(): string { return 'Rapor Toko'; }

    public function columnWidths(): array
    {
        return ['A' => 5, 'B' => 32, 'C' => 16, 'D' => 12, 'E' => 24, 'F' => 18, 'G' => 14, 'H' => 14, 'I' => 14, 'J' => 12, 'K' => 14, 'L' => 14];
    }

    public function array(): array
    {
        $outlets = Transaction::withFilters($this->request)->invoices()
            ->join('outlets', 'transactions.outlet_id', '=', 'outlets.id')
            ->join('salesmen', 'transactions.salesman_id', '=', 'salesmen.id')
            ->select('outlets.name as outlet_name', 'outlets.city', DB::raw('LEFT(outlets.code, 3) as region_code'), 'salesmen.name as salesman_name', DB::raw('SUM(transactions.ar_amt) as net_sales'), DB::raw('SUM(transactions.qty_base) as total_qty'), DB::raw('COUNT(DISTINCT transactions.so_no) as invoice_count'), DB::raw('MAX(transactions.so_date) as last_order_date'))
            ->whereNotNull('outlets.code')
            ->groupBy('outlets.name', 'outlets.city', 'outlets.code', 'salesmen.name')
            ->orderByDesc('net_sales')->get();

        $returnMap = Transaction::withFilters($this->request)->returns()
            ->join('outlets', 'transactions.outlet_id', '=', 'outlets.id')
            ->select('outlets.name as outlet_name', DB::raw('SUM(ABS(transactions.ar_amt)) as total_returns'))
            ->groupBy('outlets.name')->get()->keyBy('outlet_name');

        $totalSales = (float) $outlets->sum('net_sales');

        $rows = [
            ['RAPOR TOKO (OUTLET) LENGKAP — PERIODE ' . $this->period, '', '', '', '', '', '', '', '', '', '', ''],
            ['#', 'NAMA TOKO', 'KOTA', 'WILAYAH', 'SALESMAN', 'REVENUE (Rp)', '% KONTRIBUSI', 'RETUR (Rp)', 'RETURN RATE (%)', 'JML FAKTUR', 'TOTAL QTY', 'LAST ORDER'],
        ];

        foreach ($outlets as $i => $o) {
            $returns    = (float) ($returnMap[$o->outlet_name]->total_returns ?? 0);
            $returnRate = ($o->net_sales + $returns) > 0 ? ($returns / ($o->net_sales + $returns)) * 100 : 0;
            $contrib    = $totalSales > 0 ? ($o->net_sales / $totalSales) * 100 : 0;

            $rows[] = [
                $i + 1,
                $o->outlet_name,
                $o->city ?? '-',
                strtoupper($o->region_code ?? '-'),
                $o->salesman_name,
                (float) $o->net_sales,
                $contrib,
                $returns,
                $returnRate,
                (int) $o->invoice_count,
                (float) $o->total_qty,
                $o->last_order_date ?? '-',
            ];
        }

        $this->dataRowCount = $outlets->count();
        $rows[] = ['TOTAL', '', '', '', '', (float) $outlets->sum('net_sales'), 100.0, '', '', $outlets->sum('invoice_count'), '', ''];

        return $rows;
    }

    public function styles(Worksheet $sheet): array { return []; }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $ws = $event->sheet->getDelegate();
                $lastDataRow = 2 + $this->dataRowCount;
                $totalRow    = $lastDataRow + 1;

                $this->styleTitle($ws, 'A1:L1');
                $ws->getRowDimension(1)->setRowHeight(28);

                $this->styleColHeader($ws, 'A2:L2');
                $ws->getRowDimension(2)->setRowHeight(36);

                $this->styleDataRows($ws, 3, $lastDataRow, 'L');

                $this->formatCurrencyCol($ws, "F3:F{$lastDataRow}");
                $this->formatPercentCol($ws,  "G3:G{$lastDataRow}");
                $this->formatCurrencyCol($ws, "H3:H{$lastDataRow}");
                $this->formatPercentCol($ws,  "I3:I{$lastDataRow}");
                $ws->getStyle("J3:K{$lastDataRow}")->getAlignment()->setHorizontal('right');
                $ws->getStyle("A3:A{$lastDataRow}")->getAlignment()->setHorizontal('center');
                $ws->getStyle("D3:D{$lastDataRow}")->getAlignment()->setHorizontal('center');

                $this->styleTotalsRow($ws, "A{$totalRow}:L{$totalRow}");
                $this->formatCurrencyCol($ws, "F{$totalRow}");

                $this->outerBorder($ws, "A2:L{$totalRow}");
                $ws->freezePane('B3');
            },
        ];
    }
}
