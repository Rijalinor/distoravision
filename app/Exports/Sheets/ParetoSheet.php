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
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;

class ParetoSheet implements FromArray, WithTitle, WithStyles, WithColumnWidths, WithEvents
{
    use ExcelStyler;

    protected Request $request;
    protected string $period;
    protected array $klassCounts = [0, 0, 0];
    protected int $dataRowCount  = 0;

    public function __construct(Request $request, string $period)
    {
        $this->request = $request;
        $this->period  = $period;
    }

    public function title(): string { return 'Pareto 80-20'; }

    public function columnWidths(): array
    {
        return ['A' => 5, 'B' => 40, 'C' => 18, 'D' => 14, 'E' => 14, 'F' => 18];
    }

    public function array(): array
    {
        $data = Transaction::withFilters($this->request)->invoices()
            ->join('products', 'transactions.product_id', '=', 'products.id')
            ->select('products.name', DB::raw('SUM(transactions.taxed_amt) as total_sales'))
            ->groupBy('products.name')->having('total_sales', '>', 0)
            ->orderByDesc('total_sales')->get();

        $totalRevenue = (float) $data->sum('total_sales');
        $cumulative   = 0.0;

        $rows = [
            ['ANALISA PARETO 80/20 (PRODUK) — PERIODE ' . $this->period, '', '', '', '', ''],
            ['#', 'NAMA PRODUK', 'REVENUE (Rp)', '% INDIVIDU', '% KUMULATIF', 'KELAS PARETO'],
        ];

        foreach ($data as $i => $item) {
            $pct       = $totalRevenue > 0 ? ($item->total_sales / $totalRevenue) * 100 : 0;
            $cumulative += $pct;
            if ($cumulative <= 80)      { $kelas = 'Kelas A (VIP)'; $this->klassCounts[0]++; }
            elseif ($cumulative <= 95)  { $kelas = 'Kelas B';       $this->klassCounts[1]++; }
            else                        { $kelas = 'Kelas C';       $this->klassCounts[2]++; }

            $rows[] = [
                $i + 1,
                $item->name,
                (float) $item->total_sales,
                $pct,
                $cumulative,
                $kelas,
            ];
        }

        $this->dataRowCount = $data->count();
        return $rows;
    }

    public function styles(Worksheet $sheet): array { return []; }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $ws = $event->sheet->getDelegate();
                $lastDataRow = 2 + $this->dataRowCount;

                $this->styleTitle($ws, 'A1:F1');
                $ws->getRowDimension(1)->setRowHeight(28);

                $this->styleColHeader($ws, 'A2:F2');
                $ws->getRowDimension(2)->setRowHeight(30);

                // Color-code rows by Kelas A/B/C
                for ($row = 3; $row <= $lastDataRow; $row++) {
                    $range = "A{$row}:F{$row}";
                    $kelas = (string) $ws->getCell("F{$row}")->getValue();
                    if (str_contains($kelas, 'Kelas A'))     { $bg = 'FFD1FAE5'; }
                    elseif (str_contains($kelas, 'Kelas B')) { $bg = 'FFFEF3C7'; }
                    else                                     { $bg = 'FFFEE2E2'; }

                    $ws->getStyle($range)->applyFromArray([
                        'fill'    => ['fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID, 'startColor' => ['argb' => $bg]],
                        'borders' => ['bottom' => ['borderStyle' => 'thin', 'color' => ['argb' => $this->clrBorder]]],
                        'font'    => ['size' => 10, 'name' => 'Calibri'],
                    ]);
                }

                $this->formatCurrencyCol($ws, "C3:C{$lastDataRow}");
                $this->formatPercentCol($ws,  "D3:D{$lastDataRow}");
                $this->formatPercentCol($ws,  "E3:E{$lastDataRow}");

                $ws->getStyle("A3:A{$lastDataRow}")->getAlignment()->setHorizontal('center');
                $ws->getStyle("F3:F{$lastDataRow}")->getAlignment()->setHorizontal('center');

                $this->outerBorder($ws, "A2:F{$lastDataRow}");
                $ws->freezePane('A3');
            },
        ];
    }
}

