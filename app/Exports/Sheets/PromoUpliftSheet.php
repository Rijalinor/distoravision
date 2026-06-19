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
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class PromoUpliftSheet implements FromArray, WithColumnWidths, WithEvents, WithStyles, WithTitle
{
    use ExcelStyler;

    protected Request $request;

    protected string $period;

    protected int $dataRowCount = 0;

    protected int $notesStartRow = 0;

    public function __construct(Request $request, string $period)
    {
        $this->request = $request;
        $this->period = $period;
    }

    public function title(): string
    {
        return 'Promo Uplift & ROI';
    }

    public function columnWidths(): array
    {
        return ['A' => 5, 'B' => 32, 'C' => 18, 'D' => 14, 'E' => 12, 'F' => 14, 'G' => 12, 'H' => 12, 'I' => 14, 'J' => 16, 'K' => 12, 'L' => 16];
    }

    public function array(): array
    {
        $periods = Transaction::select('period')->distinct()->orderByDesc('period')->pluck('period');
        $promoReq = clone $this->request;
        if (! $this->request->has('start_period') && ! $this->request->has('end_period') && $periods->isNotEmpty()) {
            $promoReq->merge(['start_period' => $periods->last(), 'end_period' => $periods->first()]);
        }

        $data = Transaction::withFilters($promoReq)->invoices()
            ->where('transactions.gross', '>', 0)
            ->join('products', 'transactions.product_id', '=', 'products.id')
            ->join('principals', 'products.principal_id', '=', 'principals.id')
            ->select(
                'transactions.product_id', 'products.name as product_name', 'principals.name as principal_name',
                'transactions.period',
                DB::raw('SUM(transactions.qty_base) as total_qty'),
                DB::raw('SUM(transactions.gross) as total_gross'),
                DB::raw('SUM(transactions.disc_total) as total_discount'),
                DB::raw('SUM(transactions.cogs) as total_cogs'),
                DB::raw('ROUND((SUM(transactions.disc_total) / SUM(transactions.gross)) * 100, 2) as discount_pct')
            )
            ->groupBy('transactions.product_id', 'products.name', 'principals.name', 'transactions.period')
            ->havingRaw('SUM(transactions.qty_base) > 0')->get();

        $grouped = [];
        foreach ($data as $row) {
            if (! isset($grouped[$row->product_id])) {
                $grouped[$row->product_id] = ['name' => $row->product_name, 'principal' => str_replace('PT. ', '', $row->principal_name), 'periods' => []];
            }
            $grouped[$row->product_id]['periods'][$row->period] = $row;
        }

        $results = [];
        foreach ($grouped as $prod) {
            if (count($prod['periods']) < 2) {
                continue;
            }
            $sorted = collect($prod['periods'])->sortBy('discount_pct')->values();
            $baseline = $sorted->first();
            $promo = $sorted->last();
            if (($promo->discount_pct - $baseline->discount_pct) < 3 || $promo->total_qty < 10 || $baseline->total_qty < 10) {
                continue;
            }

            $upliftPct = $baseline->total_qty > 0 ? (($promo->total_qty - $baseline->total_qty) / $baseline->total_qty) * 100 : 0;
            $profitNormal = ($baseline->total_gross - $baseline->total_discount) - $baseline->total_cogs;
            $profitPromo = ($promo->total_gross - $promo->total_discount) - $promo->total_cogs;
            $profitDiff = $profitPromo - $profitNormal;

            // Anomaly detection
            $anomalyFlags = [];
            if ($promo->discount_pct > $baseline->discount_pct && $upliftPct <= -30) {
                $anomalyFlags[] = 'STOCKOUT';
            }
            $promoMonth = Carbon::parse($promo->period.'-01');
            $t1Period = $promoMonth->copy()->subMonth()->format('Y-m');
            if (isset($prod['periods'][$t1Period])) {
                $t1 = $prod['periods'][$t1Period];
                $t1QtyChange = $baseline->total_qty > 0 ? (($t1->total_qty - $baseline->total_qty) / $baseline->total_qty) * 100 : 0;
                $t1DiscDiff = $t1->discount_pct - $baseline->discount_pct;
                if ($t1QtyChange >= 40 && $t1DiscDiff < 3) {
                    $anomalyFlags[] = 'FORWARD BUY';
                }
            }

            $results[] = [
                'product' => $prod['name'], 'principal' => $prod['principal'],
                'baseline_period' => $baseline->period, 'baseline_disc' => $baseline->discount_pct,
                'baseline_qty' => (int) $baseline->total_qty,
                'promo_period' => $promo->period, 'promo_disc' => $promo->discount_pct,
                'promo_qty' => (int) $promo->total_qty,
                'uplift_pct' => $upliftPct, 'profit_diff' => $profitDiff,
                'status' => $profitDiff > 0 ? 'SUKSES' : 'GAGAL',
                'flags' => implode(', ', $anomalyFlags),
            ];
        }

        usort($results, fn ($a, $b) => $b['profit_diff'] <=> $a['profit_diff']);

        $rows = [
            ['ANALISA PROMO UPLIFT & ROI — PERIODE '.$this->period, '', '', '', '', '', '', '', '', '', '', ''],
            ['#', 'PRODUK', 'PRINCIPAL', 'BLN NORMAL', 'DISC NORMAL (%)', 'QTY NORMAL', 'BLN PROMO', 'DISC PROMO (%)', 'QTY PROMO', 'UPLIFT VOL (%)', 'SELISIH LABA (Rp)', 'STATUS'],
        ];

        foreach ($results as $i => $r) {
            $rows[] = [
                $i + 1, $r['product'], $r['principal'], $r['baseline_period'], $r['baseline_disc'],
                $r['baseline_qty'], $r['promo_period'], $r['promo_disc'], $r['promo_qty'],
                $r['uplift_pct'], $r['profit_diff'], $r['status'].($r['flags'] ? ' | '.$r['flags'] : ''),
            ];
        }

        $this->dataRowCount = count($results);
        $this->notesStartRow = 2 + $this->dataRowCount + 2;

        // Formula notes
        $rows[] = [];
        $rows[] = ['CATATAN RUMUS & METODOLOGI', '', '', '', '', '', '', '', '', '', '', ''];
        $rows[] = ['1. Baseline Month', 'Bulan dengan % diskon TERENDAH untuk produk tersebut (bulan "normal").', '', '', '', '', '', '', '', '', '', ''];
        $rows[] = ['2. Promo Month', 'Bulan dengan % diskon TERTINGGI untuk produk tersebut.', '', '', '', '', '', '', '', '', '', ''];
        $rows[] = ['3. Uplift Volume (%)', '((Qty Promo - Qty Normal) / Qty Normal) × 100', '', '', '', '', '', '', '', '', '', ''];
        $rows[] = ['4. Selisih Laba', 'Profit(Promo) - Profit(Normal)  dimana Profit = (Gross - Diskon) - COGS', '', '', '', '', '', '', '', '', '', ''];
        $rows[] = ['5. Status SUKSES', 'Jika Selisih Laba > 0 (promo menghasilkan laba tambahan)', '', '', '', '', '', '', '', '', '', ''];
        $rows[] = ['6. Status GAGAL', 'Jika Selisih Laba ≤ 0 (promo tidak menghasilkan laba, rugi bandar)', '', '', '', '', '', '', '', '', '', ''];
        $rows[] = ['7. Flag STOCKOUT', 'Diskon naik tetapi volume turun ≥ 30% (kemungkinan barang kosong)', '', '', '', '', '', '', '', '', '', ''];
        $rows[] = ['8. Flag FORWARD BUY', 'Volume bulan T-1 naik ≥ 40% tanpa kenaikan diskon (toko menimbun sebelum promo)', '', '', '', '', '', '', '', '', '', ''];
        $rows[] = ['9. Filter', 'Hanya produk dengan selisih diskon > 3pp dan volume > 10 unit yang dianalisis.', '', '', '', '', '', '', '', '', '', ''];

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
                $lastDataRow = 2 + $this->dataRowCount;

                $this->styleTitle($ws, 'A1:L1');
                $ws->getRowDimension(1)->setRowHeight(28);

                $this->styleColHeader($ws, 'A2:L2');
                $ws->getRowDimension(2)->setRowHeight(36);

                if ($this->dataRowCount > 0) {
                    $this->styleDataRows($ws, 3, $lastDataRow, 'L');

                    $this->formatPercentCol($ws, "E3:E{$lastDataRow}");
                    $this->formatPercentCol($ws, "H3:H{$lastDataRow}");
                    $this->formatPercentCol($ws, "J3:J{$lastDataRow}");
                    $this->formatCurrencyCol($ws, "K3:K{$lastDataRow}");
                    $ws->getStyle("F3:F{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0');
                    $ws->getStyle("F3:F{$lastDataRow}")->getAlignment()->setHorizontal('right');
                    $ws->getStyle("I3:I{$lastDataRow}")->getNumberFormat()->setFormatCode('#,##0');
                    $ws->getStyle("I3:I{$lastDataRow}")->getAlignment()->setHorizontal('right');
                    $ws->getStyle("A3:A{$lastDataRow}")->getAlignment()->setHorizontal('center');

                    // Color-code status column
                    for ($row = 3; $row <= $lastDataRow; $row++) {
                        $status = (string) $ws->getCell("L{$row}")->getValue();
                        if (str_contains($status, 'SUKSES')) {
                            $ws->getStyle("L{$row}")->getFont()->setColor(new Color('FF16A34A'));
                        } elseif (str_contains($status, 'GAGAL')) {
                            $ws->getStyle("L{$row}")->getFont()->setColor(new Color('FFDC2626'));
                        }
                    }

                    $this->outerBorder($ws, "A2:L{$lastDataRow}");
                }

                // Notes section
                $notesRow = $this->notesStartRow;
                $this->styleNotesBlock($ws, $notesRow, 10, 'L');

                $ws->freezePane('A3');
            },
        ];
    }
}
