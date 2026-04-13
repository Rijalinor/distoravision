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

class DiscountSheet implements FromArray, WithTitle, WithStyles, WithColumnWidths, WithEvents
{
    use ExcelStyler;

    protected Request $request;
    protected string $period;
    protected int $principalCount = 0;
    protected int $productCount   = 0;

    public function __construct(Request $request, string $period)
    {
        $this->request = $request;
        $this->period  = $period;
    }

    public function title(): string { return 'Analisa Diskon'; }

    public function columnWidths(): array
    {
        return ['A' => 5, 'B' => 32, 'C' => 22, 'D' => 18, 'E' => 18, 'F' => 14, 'G' => 14];
    }

    public function array(): array
    {
        $kpi = Transaction::withFilters($this->request)
            ->selectRaw('SUM(CASE WHEN type = "I" THEN gross ELSE 0 END) as total_gross, SUM(CASE WHEN type = "I" THEN disc_total ELSE 0 END) as total_discount, SUM(CASE WHEN type = "I" THEN taxed_amt WHEN type = "R" THEN -ABS(taxed_amt) ELSE 0 END) as total_net')->first();

        $totalGross        = (float) ($kpi->total_gross ?? 0);
        $totalDiscount     = (float) ($kpi->total_discount ?? 0);
        $avgDiscPct        = $totalGross > 0 ? ($totalDiscount / $totalGross) * 100 : 0;

        $principalDiscs = Transaction::withFilters($this->request)
            ->join('products', 'transactions.product_id', '=', 'products.id')
            ->join('principals', 'products.principal_id', '=', 'principals.id')
            ->select('principals.name as principal_name', DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.gross ELSE 0 END) as gross_sales'), DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.disc_total ELSE 0 END) as discount_given'), DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.taxed_amt WHEN transactions.type = "R" THEN -ABS(transactions.taxed_amt) ELSE 0 END) as net_sales'))
            ->groupBy('principals.name')->having('discount_given', '>', 0)->orderByDesc('discount_given')
            ->get()->map(fn($i) => tap($i, fn($it) => $it->disc_pct = $it->gross_sales > 0 ? ($it->discount_given / $it->gross_sales) * 100 : 0));

        $productDiscs = Transaction::withFilters($this->request)->invoices()
            ->join('products', 'transactions.product_id', '=', 'products.id')
            ->join('principals', 'products.principal_id', '=', 'principals.id')
            ->select('products.name as product_name', 'principals.name as principal_name', DB::raw('SUM(transactions.gross) as gross_sales'), DB::raw('SUM(transactions.disc_total) as discount_given'), DB::raw('SUM(transactions.qty_base) as qty'))
            ->groupBy('products.name', 'principals.name')->having('discount_given', '>', 0)
            ->orderByDesc('discount_given')->limit(50)
            ->get()->map(fn($i) => tap($i, fn($it) => $it->disc_pct = $it->gross_sales > 0 ? ($it->discount_given / $it->gross_sales) * 100 : 0));

        $this->principalCount = $principalDiscs->count();
        $this->productCount   = $productDiscs->count();

        $rows = [
            // 1
            ['ANALISA EFEKTIVITAS DISKON — PERIODE ' . $this->period, '', '', '', '', '', ''],
            // 2 - Ringkasan
            ['RINGKASAN',              '',           '',            '',          '',         '',      ''],
            // 3
            ['Total Gross Sales',       $totalGross,  '',            '',          '',         '',      ''],
            // 4
            ['Total Diskon',            $totalDiscount,'',           '',          '',         '',      ''],
            // 5
            ['Kedalaman Diskon Rata²',  $avgDiscPct,  '',            '',          '',         '',      ''],
            // 6 - Principal header
            ['DISKON PER PRINCIPAL', '', '', '', '', '', ''],
            // 7
            ['PRINCIPAL', 'GROSS SALES (Rp)', 'TOTAL DISKON (Rp)', 'NET SALES (Rp)', 'KEDALAMAN (%)', '', ''],
        ];

        foreach ($principalDiscs as $p) {
            $rows[] = [str_replace('PT. ', '', $p->principal_name), (float)$p->gross_sales, (float)$p->discount_given, (float)$p->net_sales, $p->disc_pct, '', ''];
        }

        $rows[] = ['DISKON PER PRODUK (TOP 50)', '', '', '', '', '', ''];
        $rows[] = ['#', 'PRODUK', 'PRINCIPAL', 'GROSS SALES (Rp)', 'DISKON (Rp)', 'KEDALAMAN (%)', 'QTY'];

        foreach ($productDiscs as $i => $p) {
            $rows[] = [$i + 1, $p->product_name, str_replace('PT. ', '', $p->principal_name), (float)$p->gross_sales, (float)$p->discount_given, $p->disc_pct, (float)$p->qty];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet): array { return []; }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $ws = $event->sheet->getDelegate();

                $this->styleTitle($ws, 'A1:G1');
                $ws->getRowDimension(1)->setRowHeight(28);

                // Summary section: RINGKASAN header row 2, data rows 3-5
                $this->styleSectionHeader($ws, 'A2:G2');
                $this->styleDataRows($ws, 3, 5, 'B');
                $ws->getStyle('A3:A5')->getFont()->setBold(true);
                $this->formatCurrencyCol($ws, 'B3:B4');
                $this->formatPercentCol($ws,  'B5');

                // Principal section: header row 6, col header row 7, data row 8+
                $principalHeaderRow = 6;
                $principalDataStart = 8;
                $principalDataEnd   = $principalDataStart + $this->principalCount - 1;

                $this->styleSectionHeader($ws, "A{$principalHeaderRow}:G{$principalHeaderRow}");
                $this->styleColHeader($ws, "A7:E7");
                $ws->getRowDimension(7)->setRowHeight(28);

                if ($this->principalCount > 0) {
                    $this->styleDataRows($ws, $principalDataStart, $principalDataEnd, 'E');
                    $this->formatCurrencyCol($ws, "B{$principalDataStart}:B{$principalDataEnd}");
                    $this->formatCurrencyCol($ws, "C{$principalDataStart}:C{$principalDataEnd}");
                    $this->formatCurrencyCol($ws, "D{$principalDataStart}:D{$principalDataEnd}");
                    $this->formatPercentCol($ws,  "E{$principalDataStart}:E{$principalDataEnd}");
                    $this->outerBorder($ws, "A7:E{$principalDataEnd}");
                }

                // Product section: comes right after principal data (no empty row since [] is gone)
                $productHeaderRow = $principalDataEnd + 1;
                $productColHeader = $productHeaderRow + 1;
                $productDataStart = $productColHeader + 1;
                $productDataEnd   = $productDataStart + $this->productCount - 1;

                $this->styleSectionHeader($ws, "A{$productHeaderRow}:G{$productHeaderRow}");
                $this->styleColHeader($ws, "A{$productColHeader}:G{$productColHeader}");
                $ws->getRowDimension($productColHeader)->setRowHeight(28);

                if ($this->productCount > 0) {
                    $this->styleDataRows($ws, $productDataStart, $productDataEnd, 'G');
                    $ws->getStyle("A{$productDataStart}:A{$productDataEnd}")->getAlignment()->setHorizontal('center');
                    $this->formatCurrencyCol($ws, "D{$productDataStart}:D{$productDataEnd}");
                    $this->formatCurrencyCol($ws, "E{$productDataStart}:E{$productDataEnd}");
                    $this->formatPercentCol($ws,  "F{$productDataStart}:F{$productDataEnd}");
                    $ws->getStyle("G{$productDataStart}:G{$productDataEnd}")->getAlignment()->setHorizontal('right');
                    $this->outerBorder($ws, "A{$productColHeader}:G{$productDataEnd}");
                }

                $ws->freezePane('A2');
            },
        ];
    }
}

