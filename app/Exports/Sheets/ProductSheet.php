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

class ProductSheet implements FromArray, WithTitle, WithStyles, WithColumnWidths, WithEvents
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

    public function title(): string { return 'Rapor Produk'; }

    public function columnWidths(): array
    {
        return ['A' => 5, 'B' => 38, 'C' => 18, 'D' => 18, 'E' => 14, 'F' => 14, 'G' => 14, 'H' => 18, 'I' => 12, 'J' => 14, 'K' => 14];
    }

    public function array(): array
    {
        $products = Transaction::withFilters($this->request)->invoices()
            ->join('products', 'transactions.product_id', '=', 'products.id')
            ->join('principals', 'products.principal_id', '=', 'principals.id')
            ->select('products.name as product_name', 'principals.name as principal_name', DB::raw('SUM(transactions.ar_amt) as net_sales'), DB::raw('SUM(transactions.cogs) as cogs'), DB::raw('SUM(transactions.qty_base) as total_qty'), DB::raw('SUM(transactions.disc_total) as total_disc'), DB::raw('COUNT(DISTINCT transactions.outlet_id) as outlet_reach'))
            ->groupBy('products.name', 'principals.name')->orderByDesc('net_sales')->get();

        $returnMap  = Transaction::withFilters($this->request)->returns()
            ->join('products', 'transactions.product_id', '=', 'products.id')
            ->select('products.name as product_name', DB::raw('SUM(ABS(transactions.ar_amt)) as total_returns'))
            ->groupBy('products.name')->get()->keyBy('product_name');

        $totalSales = (float) $products->sum('net_sales');

        $rows = [
            ['RAPOR PRODUK / SKU LENGKAP — PERIODE ' . $this->period, '', '', '', '', '', '', '', '', '', ''],
            ['#', 'NAMA PRODUK', 'PRINCIPAL', 'REVENUE (Rp)', '% KONTRIBUSI', 'TOTAL QTY', 'RETUR (Rp)', 'GROSS PROFIT (Rp)', 'MARGIN (%)', 'DISKON (Rp)', 'JANGKAUAN OUTLET'],
        ];

        foreach ($products as $i => $p) {
            $gp      = (float) $p->net_sales - (float) $p->cogs;
            $margin  = $p->net_sales > 0 ? ($gp / $p->net_sales) * 100 : 0;
            $contrib = $totalSales > 0 ? ($p->net_sales / $totalSales) * 100 : 0;
            $returns = (float) ($returnMap[$p->product_name]->total_returns ?? 0);

            $rows[] = [
                $i + 1,
                $p->product_name,
                str_replace('PT. ', '', $p->principal_name),
                (float) $p->net_sales,
                $contrib,
                (float) $p->total_qty,
                $returns,
                $gp,
                $margin,
                (float) $p->total_disc,
                (int) $p->outlet_reach,
            ];
        }

        $this->dataRowCount = $products->count();
        $rows[] = ['TOTAL', '', '', (float) $products->sum('net_sales'), 100.0, (float) $products->sum('total_qty'), '', '', '', '', ''];

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

                $this->styleTitle($ws, 'A1:K1');
                $ws->getRowDimension(1)->setRowHeight(28);

                $this->styleColHeader($ws, 'A2:K2');
                $ws->getRowDimension(2)->setRowHeight(36);

                $this->styleDataRows($ws, 3, $lastDataRow, 'K');

                $this->formatCurrencyCol($ws, "D3:D{$lastDataRow}");
                $this->formatPercentCol($ws,  "E3:E{$lastDataRow}");
                $this->formatCurrencyCol($ws, "F3:F{$lastDataRow}");
                $this->formatCurrencyCol($ws, "G3:G{$lastDataRow}");
                $this->formatCurrencyCol($ws, "H3:H{$lastDataRow}");
                $this->formatPercentCol($ws,  "I3:I{$lastDataRow}");
                $this->formatCurrencyCol($ws, "J3:J{$lastDataRow}");
                $ws->getStyle("K3:K{$lastDataRow}")->getAlignment()->setHorizontal('right');

                $this->styleTotalsRow($ws, "A{$totalRow}:K{$totalRow}");
                $this->formatCurrencyCol($ws, "D{$totalRow}");
                $this->formatCurrencyCol($ws, "F{$totalRow}");

                $ws->getStyle("A3:A{$lastDataRow}")->getAlignment()->setHorizontal('center');
                $ws->getStyle('B3:B' . $lastDataRow)->getAlignment()->setWrapText(false);

                $this->outerBorder($ws, "A2:K{$totalRow}");
                $ws->freezePane('A3');
            },
        ];
    }
}
