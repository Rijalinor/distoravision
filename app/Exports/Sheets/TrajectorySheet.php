<?php

namespace App\Exports\Sheets;

use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TrajectorySheet implements FromArray, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use ExcelStyler;

    protected Request $request;

    protected string $period;

    protected int $dataRowCount = 0;

    protected int $summaryEndRow = 7;

    protected int $notesStartRow = 0;

    protected array $periodRange = [];

    public function __construct(Request $request, string $period)
    {
        $this->request = $request;
        $this->period = $period;
    }

    public function title(): string
    {
        return 'Trajektori Outlet';
    }

    public function columnWidths(): array
    {
        return ['A' => 5, 'B' => 30, 'C' => 14, 'D' => 14, 'E' => 12, 'F' => 14, 'G' => 14, 'H' => 14, 'I' => 14, 'J' => 14, 'K' => 14, 'L' => 14, 'M' => 14];
    }

    public function array(): array
    {
        $endDate = Carbon::parse($this->period.'-01');
        $lookbackMonths = 6;
        $startDate = $endDate->copy()->subMonths($lookbackMonths - 1);
        $this->periodRange = [];
        for ($i = 0; $i < $lookbackMonths; $i++) {
            $this->periodRange[] = $startDate->copy()->addMonths($i)->format('Y-m');
        }

        $rawQuery = Transaction::query()
            ->whereIn('transactions.period', $this->periodRange)
            ->join('outlets', 'transactions.outlet_id', '=', 'outlets.id');

        if ($this->request->has('principal_id') && ! empty($this->request->get('principal_id')) && $this->request->get('principal_id') !== 'all') {
            $rawQuery->whereHas('product', fn ($q) => $q->where('principal_id', $this->request->get('principal_id')));
        }

        $monthlySales = $rawQuery->select(
            'transactions.outlet_id',
            'outlets.name as outlet_name',
            'outlets.city',
            DB::raw('SUBSTR(outlets.code, 1, 3) as region_code'),
            'transactions.period',
            DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.taxed_amt WHEN transactions.type = "R" THEN -ABS(transactions.taxed_amt) ELSE 0 END) as net_sales')
        )
            ->groupBy('transactions.outlet_id', 'outlets.name', 'outlets.city', 'outlets.code', 'transactions.period')
            ->get()
            ->groupBy('outlet_id');

        $trajectories = [];
        $segments = ['Growing' => 0, 'Stable' => 0, 'Declining' => 0, 'New' => 0, 'Dead' => 0];

        foreach ($monthlySales as $outletId => $monthlyData) {
            $outlet = $monthlyData->first();
            $activeMonths = $monthlyData->pluck('net_sales', 'period');

            $series = [];
            foreach ($this->periodRange as $p) {
                $series[$p] = (float) ($activeMonths[$p] ?? 0);
            }

            $values = array_values($series);
            $n = count($values);
            $monthCount = count(array_filter($values, fn ($v) => $v > 0));
            $latestSales = end($values);
            $prevSales = $values[$n - 2] ?? 0;

            $sumX = $sumY = $sumXY = $sumX2 = 0;
            for ($i = 0; $i < $n; $i++) {
                $sumX += $i;
                $sumY += $values[$i];
                $sumXY += $i * $values[$i];
                $sumX2 += $i * $i;
            }
            $denom = ($n * $sumX2) - ($sumX * $sumX);
            $slope = $denom > 0 ? (($n * $sumXY) - ($sumX * $sumY)) / $denom : 0;
            $avgSales = array_sum($values) / max($monthCount, 1);
            $slopePct = $avgSales > 0 ? ($slope / $avgSales) * 100 : 0;

            if ($monthCount <= 1 && $latestSales > 0) {
                $classification = 'New';
            } elseif ($monthCount <= 1 && $latestSales <= 0) {
                $classification = 'Dead';
            } elseif ($latestSales <= 0 && $prevSales <= 0) {
                $classification = 'Dead';
            } elseif ($slopePct > 10) {
                $classification = 'Growing';
            } elseif ($slopePct < -10) {
                $classification = 'Declining';
            } else {
                $classification = 'Stable';
            }

            $segments[$classification]++;

            $trajectories[] = [
                'outlet_name' => $outlet->outlet_name,
                'city' => $outlet->city ?? '-',
                'region' => strtoupper($outlet->region_code ?? '-'),
                'classification' => $classification,
                'slope_pct' => round($slopePct, 1),
                'active_months' => $monthCount,
                'series' => $series,
                'total_sales' => array_sum($values),
            ];
        }

        // Sort: Declining first, then by total_sales
        usort($trajectories, function ($a, $b) {
            $order = ['Declining' => 0, 'Dead' => 1, 'New' => 2, 'Stable' => 3, 'Growing' => 4];
            $classCompare = ($order[$a['classification']] ?? 5) <=> ($order[$b['classification']] ?? 5);
            if ($classCompare !== 0) {
                return $classCompare;
            }

            return $b['total_sales'] <=> $a['total_sales'];
        });

        // Limit to 500 outlets to keep Excel generation fast
        $trajectories = array_slice($trajectories, 0, 500);

        // Build summary
        $totalOutlets = array_sum($segments);
        $rows = [
            // 1
            ['TRAJEKTORI OUTLET (6 BULAN) — PERIODE '.$this->period, '', '', '', '', '', '', '', '', '', '', '', ''],
            // 2 - Summary header
            ['REKAPITULASI SEGMEN', 'JUMLAH', '', '', '', '', '', '', '', '', '', '', ''],
            // 3
            ['📈 Growing (Slope > +10%)', $segments['Growing'], '', '', '', '', '', '', '', '', '', '', ''],
            // 4
            ['➡️ Stable (Slope ±10%)', $segments['Stable'], '', '', '', '', '', '', '', '', '', '', ''],
            // 5
            ['📉 Declining (Slope < -10%)', $segments['Declining'], '', '', '', '', '', '', '', '', '', '', ''],
            // 6
            ['🆕 New (1 Bulan Aktif)', $segments['New'], '', '', '', '', '', '', '', '', '', '', ''],
            // 7
            ['💀 Dead (Tidak Aktif)', $segments['Dead'], '', '', '', '', '', '', '', '', '', '', ''],
        ];

        // Data header
        $salesHeaders = array_map(fn ($p) => 'Sales '.Carbon::parse($p.'-01')->format('M Y'), $this->periodRange);
        $headerRow = array_merge(['#', 'NAMA TOKO', 'KOTA', 'WILAYAH', 'KLASIFIKASI', 'SLOPE (%)', 'BLN AKTIF'], $salesHeaders);
        $rows[] = $headerRow;

        foreach ($trajectories as $i => $t) {
            $row = [$i + 1, $t['outlet_name'], $t['city'], $t['region'], $t['classification'], $t['slope_pct'], $t['active_months']];
            foreach ($this->periodRange as $p) {
                $row[] = $t['series'][$p] ?? 0;
            }
            $rows[] = $row;
        }

        $this->dataRowCount = count($trajectories);
        $this->notesStartRow = 8 + $this->dataRowCount + 2;

        // Formula notes
        $rows[] = [];
        $rows[] = array_pad(['CATATAN RUMUS & METODOLOGI'], 13, '');
        $rows[] = array_pad(['1. Slope (%)', 'Menggunakan Linear Regression (OLS) dari 6 titik data bulanan.'], 13, '');
        $rows[] = array_pad(['2. Rumus Slope', 'slope = (n·ΣXY - ΣX·ΣY) / (n·ΣX² - (ΣX)²)  dimana X = indeks bulan (0-5), Y = sales per bulan.'], 13, '');
        $rows[] = array_pad(['3. Normalisasi', 'Slope % = (slope / rata-rata penjualan aktif) × 100'], 13, '');
        $rows[] = array_pad(['4. Growing', 'Slope % > +10% → Tren naik konsisten. Outlook positif.'], 13, '');
        $rows[] = array_pad(['5. Stable', 'Slope % antara -10% s/d +10% → Konsisten, tidak berubah signifikan.'], 13, '');
        $rows[] = array_pad(['6. Declining', 'Slope % < -10% → Tren turun. Butuh intervensi cepat.'], 13, '');
        $rows[] = array_pad(['7. New', 'Hanya 1 bulan aktif & bulan terakhir masih transaksi → Pelanggan baru.'], 13, '');
        $rows[] = array_pad(['8. Dead', 'Hanya 1 bulan aktif & sudah tidak transaksi, atau 2 bulan terakhir nihil → Outlet mati.'], 13, '');
        $rows[] = array_pad(['9. Lookback', 'Analisis melihat 6 bulan ke belakang dari periode yang dipilih.'], 13, '');

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $ws = $event->sheet->getDelegate();
                $dataHeaderRow = 8;
                $dataStart = 9;
                $lastDataRow = $dataStart - 1 + $this->dataRowCount;
                $lastCol = chr(ord('G') + count($this->periodRange));

                $this->styleTitle($ws, "A1:{$lastCol}1");
                $ws->getRowDimension(1)->setRowHeight(28);
                // Summary section
                $ws->getStyle('A2:B2')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => $this->clrWhite], 'name' => 'Segoe UI'],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $this->clrBlue]],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $this->clrBorder]]],
                ]);
                // Color-code summary table segments
                $summaryColors = [
                    3 => 'FFD1FAE5', // Growing
                    4 => 'FFDBEAFE', // Stable
                    5 => 'FFFEE2E2', // Declining
                    6 => 'FFE0F2FE', // New
                    7 => 'FFF1F5F9', // Dead
                ];
                foreach ($summaryColors as $r => $bg) {
                    $ws->getStyle("A{$r}:B{$r}")->applyFromArray([
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bg]],
                        'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $this->clrBorder]]],
                        'font' => ['name' => 'Segoe UI'],
                    ]);
                    $ws->getStyle("A{$r}")->getFont()->setBold(true)->setSize(10);
                    $ws->getStyle("B{$r}")->getAlignment()->setHorizontal('center');
                    $ws->getStyle("B{$r}")->getFont()->setBold(true)->setSize(11);
                }

                // Data column header
                $this->styleColHeader($ws, "A{$dataHeaderRow}:{$lastCol}{$dataHeaderRow}");
                $ws->getRowDimension($dataHeaderRow)->setRowHeight(32);

                if ($this->dataRowCount > 0) {
                    // Color-code data rows based on classification in column E
                    for ($row = $dataStart; $row <= $lastDataRow; $row++) {
                        $range = "A{$row}:{$lastCol}{$row}";
                        $class = (string) $ws->getCell("E{$row}")->getValue();
                        if (str_contains($class, 'Growing')) {
                            $bg = 'FFD1FAE5';
                        } elseif (str_contains($class, 'Stable')) {
                            $bg = 'FFDBEAFE';
                        } elseif (str_contains($class, 'Declining')) {
                            $bg = 'FFFEE2E2';
                        } elseif (str_contains($class, 'New')) {
                            $bg = 'FFE0F2FE';
                        } else {
                            $bg = 'FFF1F5F9';
                        }

                        $ws->getStyle($range)->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bg]],
                            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $this->clrBorder]]],
                            'font' => ['size' => 10, 'name' => 'Segoe UI'],
                        ]);
                    }

                    $this->formatPercentCol($ws, "F{$dataStart}:F{$lastDataRow}");
                    $ws->getStyle("A{$dataStart}:A{$lastDataRow}")->getAlignment()->setHorizontal('center');

                    // Bulan Aktif (D) -> Integer
                    $ws->getStyle("D{$dataStart}:D{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0');
                    $ws->getStyle("D{$dataStart}:D{$lastDataRow}")->getAlignment()->setHorizontal('center');

                    $ws->getStyle("E{$dataStart}:E{$lastDataRow}")->getAlignment()->setHorizontal('center');

                    // Rata-rata Sales (G) -> Currency
                    $this->formatCurrencyCol($ws, "G{$dataStart}:G{$lastDataRow}");

                    // Format sales columns as currency (bulk)
                    for ($c = 0; $c < count($this->periodRange); $c++) {
                        $col = chr(ord('H') + $c);
                        $this->formatCurrencyCol($ws, "{$col}{$dataStart}:{$col}{$lastDataRow}");
                    }

                    $this->outerBorder($ws, "A{$dataHeaderRow}:{$lastCol}{$lastDataRow}");
                }

                // Notes section
                $notesRow = $this->notesStartRow;
                if ($notesRow > 0) {
                    $this->styleNotesBlock($ws, $notesRow, 10, $lastCol);
                }

                $ws->freezePane('A9');
            },
        ];
    }
}
