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
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class SalesmanSheet implements FromArray, WithTitle, WithStyles, WithColumnWidths, WithEvents
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

    public function title(): string { return 'Rapor Salesman'; }

    public function columnWidths(): array
    {
        return ['A' => 5, 'B' => 28, 'C' => 18, 'D' => 18, 'E' => 14, 'F' => 16, 'G' => 14, 'H' => 16, 'I' => 12, 'J' => 14, 'K' => 14, 'L' => 14];
    }

    public function array(): array
    {
        $prevPeriod = \Carbon\Carbon::parse($this->period . '-01')->subMonth()->format('Y-m');
        $prevReq    = new \Illuminate\Http\Request();
        $prevReq->merge(['start_period' => $prevPeriod, 'end_period' => $prevPeriod, 'principal_id' => $this->request->get('principal_id')]);

        $salesmen = Transaction::withFilters($this->request)
            ->join('salesmen', 'transactions.salesman_id', '=', 'salesmen.id')
            ->select('salesmen.name as salesman_name', DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.taxed_amt WHEN transactions.type = "R" THEN -ABS(transactions.taxed_amt) ELSE 0 END) as net_sales'), DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.cogs ELSE 0 END) as cogs'), DB::raw('COUNT(DISTINCT CASE WHEN transactions.type = "I" THEN transactions.so_no END) as invoice_count'), DB::raw('COUNT(DISTINCT CASE WHEN transactions.type = "I" THEN transactions.outlet_id END) as outlet_count'), DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.qty_base ELSE 0 END) as total_qty'))
            ->groupBy('salesmen.name')->orderByDesc('net_sales')->get();

        $prevSalesMap = Transaction::withFilters($prevReq)
            ->join('salesmen', 'transactions.salesman_id', '=', 'salesmen.id')
            ->select('salesmen.name as salesman_name', DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.taxed_amt WHEN transactions.type = "R" THEN -ABS(transactions.taxed_amt) ELSE 0 END) as prev_sales'))
            ->groupBy('salesmen.name')->get()->keyBy('salesman_name');

        $returnMap = Transaction::withFilters($this->request)->returns()
            ->join('salesmen', 'transactions.salesman_id', '=', 'salesmen.id')
            ->select('salesmen.name as salesman_name', DB::raw('SUM(ABS(transactions.taxed_amt)) as total_returns'))
            ->groupBy('salesmen.name')->get()->keyBy('salesman_name');

        $rows = [
            ['RAPOR INDIVIDU SALESMAN — PERIODE ' . $this->period, '', '', '', '', '', '', '', '', '', '', ''],
            ['#', 'NAMA SALESMAN', 'NET SALES (Rp)', 'SALES BLN LALU (Rp)', 'DELTA MoM (%)', 'RETUR (Rp)', 'RETURN RATE (%)', 'GROSS PROFIT (Rp)', 'MARGIN (%)', 'JML INVOICE', 'OUTLET AKTIF', 'TOTAL QTY'],
        ];

        foreach ($salesmen as $i => $s) {
            $prev       = (float) ($prevSalesMap[$s->salesman_name]->prev_sales ?? 0);
            $mom        = $prev > 0 ? (($s->net_sales - $prev) / $prev) * 100 : 0;
            $returns    = (float) ($returnMap[$s->salesman_name]->total_returns ?? 0);
            $returnRate = ($s->net_sales + $returns) > 0 ? ($returns / ($s->net_sales + $returns)) * 100 : 0;
            $gp         = (float) $s->net_sales - (float) $s->cogs;
            $margin     = $s->net_sales > 0 ? ($gp / $s->net_sales) * 100 : 0;

            $rows[] = [
                $i + 1,
                $s->salesman_name,
                (float) $s->net_sales,
                $prev,
                $mom,
                $returns,
                $returnRate,
                $gp,
                $margin,
                (int) $s->invoice_count,
                (int) $s->outlet_count,
                (float) $s->total_qty,
            ];
        }

        $this->dataRowCount = $salesmen->count();

        // Totals
        $rows[] = ['TOTAL', '', (float) $salesmen->sum('net_sales'), '', '', '', '', '', '', $salesmen->sum('invoice_count'), $salesmen->sum('outlet_count'), ''];

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

                // Currency columns
                foreach (['C', 'D', 'F', 'H'] as $col) {
                    $this->formatCurrencyCol($ws, "{$col}3:{$col}{$lastDataRow}");
                    $this->formatCurrencyCol($ws, "{$col}{$totalRow}");
                }
                // Percent columns
                foreach (['E', 'G', 'I'] as $col) {
                    $this->formatPercentCol($ws, "{$col}3:{$col}{$lastDataRow}");
                }

                $ws->getStyle("A3:A{$lastDataRow}")->getAlignment()->setHorizontal('center');
                $ws->getStyle("J3:L{$lastDataRow}")->getAlignment()->setHorizontal('right');

                $this->styleTotalsRow($ws, "A{$totalRow}:L{$totalRow}");
                $this->formatCurrencyCol($ws, "C{$totalRow}");

                $this->outerBorder($ws, "A2:L{$totalRow}");
                $ws->freezePane('A3');

                // Conditional color: MoM negative = red, positive = green
                for ($row = 3; $row <= $lastDataRow; $row++) {
                    $val = $ws->getCell("E{$row}")->getValue();
                    if (is_numeric($val) && $val < 0) {
                        $ws->getStyle("E{$row}")->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFDC2626'));
                    } elseif (is_numeric($val) && $val > 0) {
                        $ws->getStyle("E{$row}")->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF16A34A'));
                    }
                }
            },
        ];
    }
}

