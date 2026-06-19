<?php

namespace App\Exports\Sheets;

use App\Models\Transaction;
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

class RfmSheet implements FromArray, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use ExcelStyler;

    protected Request $request;

    protected string $period;

    protected int $dataRowCount = 0;

    protected int $summaryEndRow = 6;

    public function __construct(Request $request, string $period)
    {
        $this->request = $request;
        $this->period = $period;
    }

    public function title(): string
    {
        return 'Segmentasi RFM';
    }

    public function columnWidths(): array
    {
        return ['A' => 5, 'B' => 34, 'C' => 14, 'D' => 16, 'E' => 18, 'F' => 10, 'G' => 10, 'H' => 10, 'I' => 12, 'J' => 20];
    }

    public function array(): array
    {
        $outletStats = Transaction::withFilters($this->request)
            ->join('outlets', 'transactions.outlet_id', '=', 'outlets.id')
            ->select('outlets.name as outlet_name', DB::raw('MAX(CASE WHEN transactions.type = "I" THEN so_date END) as last_order_date'), DB::raw('COUNT(DISTINCT CASE WHEN transactions.type = "I" THEN so_no END) as frequency'), DB::raw('SUM(CASE WHEN transactions.type = "I" THEN transactions.taxed_amt WHEN transactions.type = "R" THEN -ABS(transactions.taxed_amt) ELSE 0 END) as monetary'))
            ->groupBy('outlets.name')->get();

        $count = $outletStats->count();
        $tiers = ['Champion' => 0, 'Loyal' => 0, 'Need Attention' => 0, 'At Risk' => 0];

        if ($count > 0) {
            $rSorted = $outletStats->sortBy('last_order_date')->pluck('outlet_name')->toArray();
            $fSorted = $outletStats->sortBy('frequency')->pluck('outlet_name')->toArray();
            $mSorted = $outletStats->sortBy('monetary')->pluck('outlet_name')->toArray();
            $rIndex = array_flip($rSorted);
            $fIndex = array_flip($fSorted);
            $mIndex = array_flip($mSorted);

            $outletStats = $outletStats->map(function ($item) use ($count, $rIndex, $fIndex, $mIndex, &$tiers) {
                $name = $item->outlet_name;
                $rScore = $rIndex[$name] >= ($count * 0.66) ? 3 : ($rIndex[$name] >= ($count * 0.33) ? 2 : 1);
                $fScore = $fIndex[$name] >= ($count * 0.66) ? 3 : ($fIndex[$name] >= ($count * 0.33) ? 2 : 1);
                $mScore = $mIndex[$name] >= ($count * 0.66) ? 3 : ($mIndex[$name] >= ($count * 0.33) ? 2 : 1);
                $overall = $rScore + $fScore + $mScore;
                $segment = $overall >= 8 ? 'Champion' : ($overall >= 6 ? 'Loyal' : ($overall >= 4 ? 'Need Attention' : 'At Risk'));
                $tiers[$segment]++;
                $item->r_score = $rScore;
                $item->f_score = $fScore;
                $item->m_score = $mScore;
                $item->overall = $overall;
                $item->segment = $segment;

                return $item;
            })->sortByDesc('monetary')->values();
        }

        $rows = [
            // 1
            ['ANALISA RFM — SEGMENTASI PELANGGAN — PERIODE '.$this->period, '', '', '', '', '', '', '', '', ''],
            // 2 - Summary header
            ['REKAPITULASI SEGMEN', 'JUMLAH TOKO', '', '', '', '', '', '', '', ''],
            // 3
            ['🏆 Champion (Best Customer)',         $tiers['Champion'],      '', '', '', '', '', '', '', ''],
            // 4
            ['🤝 Loyal (Steady Buyer)',              $tiers['Loyal'],         '', '', '', '', '', '', '', ''],
            // 5
            ['👀 Need Attention (Fading)',           $tiers['Need Attention'], '', '', '', '', '', '', '', ''],
            // 6
            ['⚠️ At Risk (Almost Lost)',             $tiers['At Risk'],       '', '', '', '', '', '', '', ''],
            // 7 - Data header
            ['#', 'NAMA TOKO', 'LAST ORDER', 'FREKUENSI (Trx)', 'MONETARY (Rp)', 'SKOR R', 'SKOR F', 'SKOR M', 'TOTAL SKOR', 'SEGMEN'],
        ];

        if ($count > 0) {
            foreach ($outletStats as $i => $o) {
                $rows[] = [$i + 1, $o->outlet_name, $o->last_order_date, (int) $o->frequency, (float) $o->monetary, $o->r_score, $o->f_score, $o->m_score, $o->overall, $o->segment];
            }
        }

        $this->dataRowCount = $count;

        // Formula notes
        $rows[] = [];
        $rows[] = array_pad(['CATATAN RUMUS & METODOLOGI'], 10, '');
        $rows[] = array_pad(['1. Recency (R)', 'Skor 1-3 berdasarkan tanggal order terakhir. Makin baru → skor makin tinggi.'], 10, '');
        $rows[] = array_pad(['2. Frequency (F)', 'Skor 1-3 berdasarkan jumlah transaksi unik (invoice). Makin sering → skor makin tinggi.'], 10, '');
        $rows[] = array_pad(['3. Monetary (M)', 'Skor 1-3 berdasarkan total Net Sales. Makin besar → skor makin tinggi.'], 10, '');
        $rows[] = array_pad(['4. Scoring', 'Toko diurutkan per metrik, dibagi 3 kuartil (33%/66%): Kuartil atas = 3, tengah = 2, bawah = 1.'], 10, '');
        $rows[] = array_pad(['5. Total Skor', 'R + F + M (rentang 3 s/d 9)'], 10, '');
        $rows[] = array_pad(['6. Champion', 'Total skor ≥ 8 → Pelanggan terbaik. Pertahankan dan beri prioritas!'], 10, '');
        $rows[] = array_pad(['7. Loyal', 'Total skor 6-7 → Pelanggan tetap. Naikkan engagement.'], 10, '');
        $rows[] = array_pad(['8. Need Attention', 'Total skor 4-5 → Mulai melemah. Butuh perhatian khusus.'], 10, '');
        $rows[] = array_pad(['9. At Risk', 'Total skor ≤ 3 → Hampir hilang. Intervensi segera!'], 10, '');

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
                $dataStart = 8;
                $lastDataRow = $dataStart - 1 + $this->dataRowCount;

                $this->styleTitle($ws, 'A1:J1');
                $ws->getRowDimension(1)->setRowHeight(28);

                // Summary table
                $ws->getStyle('A2:B2')->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => $this->clrWhite], 'name' => 'Segoe UI'],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $this->clrBlue]],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $this->clrBorder]]],
                ]);
                $ws->getStyle('A3:B6')->applyFromArray([
                    'font' => ['name' => 'Segoe UI'],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFFFFF']],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $this->clrBorder]]],
                ]);
                foreach ([3, 4, 5, 6] as $r) {
                    $ws->getStyle("B{$r}")->getAlignment()->setHorizontal('center');
                    $ws->getStyle("A{$r}")->getFont()->setBold(true)->setSize(10);
                }

                // Data column header
                $this->styleColHeader($ws, 'A7:J7');
                $ws->getRowDimension(7)->setRowHeight(28);

                if ($this->dataRowCount > 0) {
                    // Color-code rows by RFM segment
                    for ($row = $dataStart; $row <= $lastDataRow; $row++) {
                        $range = "A{$row}:J{$row}";
                        $segment = (string) $ws->getCell("J{$row}")->getValue();
                        if (str_contains($segment, 'Champion')) {
                            $bg = 'FFD1FAE5';
                        } elseif (str_contains($segment, 'Loyal')) {
                            $bg = 'FFDBEAFE';
                        } elseif (str_contains($segment, 'Need Attention')) {
                            $bg = 'FFFEF3C7';
                        } else {
                            $bg = 'FFFEE2E2';
                        }

                        $ws->getStyle($range)->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bg]],
                            'borders' => ['bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => $this->clrBorder]]],
                            'font' => ['size' => 10, 'name' => 'Segoe UI'],
                        ]);
                    }

                    $this->formatCurrencyCol($ws, "E{$dataStart}:E{$lastDataRow}");
                    $ws->getStyle("A{$dataStart}:A{$lastDataRow}")->getAlignment()->setHorizontal('center');

                    // Recency (D), Frequency (F) -> Integer
                    $ws->getStyle("D{$dataStart}:D{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0');
                    $ws->getStyle("D{$dataStart}:D{$lastDataRow}")->getAlignment()->setHorizontal('right');
                    $ws->getStyle("F{$dataStart}:F{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0');
                    $ws->getStyle("F{$dataStart}:F{$lastDataRow}")->getAlignment()->setHorizontal('right');

                    // Scores (G, H, I) -> Centered Integer
                    $ws->getStyle("G{$dataStart}:I{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0');
                    $ws->getStyle("G{$dataStart}:I{$lastDataRow}")->getAlignment()->setHorizontal('center');

                    $this->outerBorder($ws, "A7:J{$lastDataRow}");
                }

                $ws->freezePane('A8');
            },
        ];
    }
}
