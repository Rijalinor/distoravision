<?php

namespace App\Exports\Sheets;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

class SummarySheet implements FromArray, WithTitle, WithStyles, WithColumnWidths, WithEvents
{
    use ExcelStyler;

    protected Request $request;
    protected string $period;
    protected string $principalName;
    protected array $computed = [];

    public function __construct(Request $request, string $period, string $principalName)
    {
        $this->request       = $request;
        $this->period        = $period;
        $this->principalName = $principalName;

        $this->computeData();
    }

    protected function computeData(): void
    {
        $kpis = Transaction::withFilters($this->request)->invoices()
            ->selectRaw('SUM(taxed_amt) as total_omset, SUM(cogs) as total_cogs, SUM(gross) as gross_sales, SUM(disc_total) as total_discount, COUNT(DISTINCT so_no) as invoice_count, COUNT(DISTINCT outlet_id) as outlet_count')
            ->first();

        $totalOmset   = (float) ($kpis->total_omset ?? 0);
        $totalCogs    = (float) ($kpis->total_cogs ?? 0);
        $totalReturns = (float) Transaction::withFilters($this->request)->returns()->sum(DB::raw('ABS(taxed_amt)'));
        $netSales     = $totalOmset - $totalReturns;
        $grossProfit  = $netSales - $totalCogs;
        $totalDiscou  = (float) ($kpis->total_discount ?? 0);
        $blendedMargi = $netSales > 0 ? ($grossProfit / $netSales) * 100 : 0;
        $returnRate   = ($netSales + $totalReturns) > 0 ? ($totalReturns / ($netSales + $totalReturns)) * 100 : 0;

        $prevPeriod  = \Carbon\Carbon::parse($this->period . '-01')->subMonth()->format('Y-m');
        $prevReq     = new \Illuminate\Http\Request();
        $prevReq->merge(['start_period' => $prevPeriod, 'end_period' => $prevPeriod, 'principal_id' => $this->request->get('principal_id')]);
        $prevSales   = (float) Transaction::withFilters($prevReq)->invoices()->sum('taxed_amt');
        $prevReturns = (float) Transaction::withFilters($prevReq)->returns()->sum(DB::raw('ABS(taxed_amt)'));
        $prevNet     = $prevSales - $prevReturns;
        $momNet      = $prevNet > 0 ? (($netSales - $prevNet) / $prevNet) * 100 : 0;

        $prevOutlets    = Transaction::withFilters($prevReq)->groupBy('outlet_id')->having(DB::raw('SUM(CASE WHEN type = "I" THEN taxed_amt WHEN type = "R" THEN -ABS(taxed_amt) ELSE 0 END)'), '>', 0)->pluck('outlet_id')->unique();
        $currentOutlets = Transaction::withFilters($this->request)->groupBy('outlet_id')->having(DB::raw('SUM(CASE WHEN type = "I" THEN taxed_amt WHEN type = "R" THEN -ABS(taxed_amt) ELSE 0 END)'), '>', 0)->pluck('outlet_id')->unique();
        $churnedIds     = $prevOutlets->diff($currentOutlets);
        $churnedCount   = $churnedIds->count();
        $churnedLoss    = $churnedCount > 0 ? (float) Transaction::withFilters($prevReq)->whereIn('outlet_id', $churnedIds)->sum(DB::raw('CASE WHEN type = "I" THEN taxed_amt WHEN type = "R" THEN -ABS(taxed_amt) ELSE 0 END')) : 0;

        $topPrincipal = Transaction::withFilters($this->request)->invoices()
            ->join('products', 'transactions.product_id', '=', 'products.id')
            ->join('principals', 'products.principal_id', '=', 'principals.id')
            ->select('principals.name', DB::raw('SUM(transactions.taxed_amt) as rev'))
            ->groupBy('principals.name')->orderByDesc('rev')->first();

        $this->computed = compact(
            'netSales', 'totalOmset', 'totalCogs', 'totalReturns', 'grossProfit', 'totalDiscou',
            'blendedMargi', 'returnRate', 'prevNet', 'momNet', 'churnedCount', 'churnedLoss',
            'topPrincipal', 'kpis'
        );
    }

    public function title(): string { return 'Ringkasan Eksekutif'; }

    public function columnWidths(): array
    {
        return ['A' => 35, 'B' => 22, 'C' => 22, 'D' => 38];
    }

    public function array(): array
    {
        extract($this->computed);
        $generatedAt = now()->format('d/m/Y H:i');

        return [
            /* 1 */ ['BUKU RAPOR PENJUALAN 360°', '', '', ''],
            /* 2 */ ['DistoraVision Enterprise Analytics  ·  Digenerate: ' . $generatedAt, '', '', ''],
            /* 3 */ [],
            /* 4 */ ['Periode Laporan', $this->period, 'Principal / Brand', $this->principalName],
            /* 5 */ [],
            /* 6 */ ['A.   RINGKASAN KINERJA UTAMA', '', '', ''],
            /* 7 */ ['Metrik', 'Nilai (Rp / %)', 'Pembanding', 'Keterangan'],
            /* 8 */ ['Omset (Taxed Amount)',   $totalOmset,   '',        'Omset bruto setelah diskon sebelum retur'],
            /* 9 */ ['Total Retur',            $totalReturns, '',        'Nilai retur BAST yang dikembalikan'],
            /* 10*/['Net Sales (Omset-Retur)', $netSales,     $prevNet,  'Sales bersih setelah dikurangi retur'],
            /* 11*/['Return Rate',             $returnRate,   '',        'Persentase retur terhadap omset'],
            /* 12*/['HPP (COGS)',              $totalCogs,    '',        'Harga pokok penjualan'],
            /* 13*/['Gross Profit',            $grossProfit,  '',        'Laba kotor sebelum biaya operasional'],
            /* 14*/['Blended Margin',          $blendedMargi, '',        'Rata-rata % keuntungan keseluruhan'],
            /* 15*/['Total Diskon Diberikan',  $totalDiscou,  '',        'Subsidi promo ke toko-toko'],
            /* 16*/['Total Invoice / Faktur',  (int)($kpis->invoice_count ?? 0), '', 'Jumlah faktur penjualan unik'],
            /* 17*/['Outlet Aktif',            (int)($kpis->outlet_count ?? 0),  '', 'Toko yang bertransaksi bulan ini'],
            /* 18*/['MoM Growth (Net Sales)',  $momNet,       '',        'Pertumbuhan vs bulan sebelumnya'],
            /* 19*/ [],
            /* 20*/ ['B.   PERINGATAN DINI & RISIKO BISNIS', '', '', ''],
            /* 21*/ ['Indikator Risiko', 'Jumlah', 'Nilai Opportunity Loss (Rp)', 'Status'],
            /* 22*/ ['Toko Churn / Berhenti Order', $churnedCount, $churnedLoss, $churnedCount > 10 ? '⚠️ WASPADA' : ($churnedCount > 0 ? '🟡 PANTAU' : '✅ AMAN')],
            /* 23*/ [],
            /* 24*/ ['C.   PRINCIPAL DOMINASI OMSET', '', '', ''],
            /* 25*/ ['Principal', 'Revenue (Rp)', '', ''],
            /* 26*/ [$topPrincipal->name ?? 'N/A', (float)($topPrincipal->rev ?? 0), '', ''],
        ];
    }

    public function styles(Worksheet $sheet): array { return []; }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $ws = $event->sheet->getDelegate();

                // Title rows
                $this->styleTitle($ws, 'A1:D1');
                $ws->getRowDimension(1)->setRowHeight(30);
                $this->styleSubtitle($ws, 'A2:D2');
                $ws->getRowDimension(2)->setRowHeight(18);

                // Info row 4
                $ws->getStyle('A4:D4')->applyFromArray([
                    'font'   => ['bold' => true, 'size' => 11],
                    'fill'   => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFDBEAFE']],
                    'borders'=> ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF93C5FD']]],
                ]);
                $ws->getStyle('A4')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $ws->getStyle('C4')->getFont()->setBold(true);

                // Section headers
                $this->styleSectionHeader($ws, 'A6:D6');
                $ws->getRowDimension(6)->setRowHeight(22);
                $this->styleColHeader($ws, 'A7:D7');
                $ws->getRowDimension(7)->setRowHeight(22);

                // Data rows 8-18
                $this->styleDataRows($ws, 8, 18, 'D');

                // Currency columns B & C for data rows
                $this->formatCurrencyCol($ws, 'B8:B18');
                $this->formatCurrencyCol($ws, 'C8:C18');

                // Percent formatting on specific rows
                $ws->getStyle('B11')->getNumberFormat()->setFormatCode('0.00"%"');
                $ws->getStyle('B14')->getNumberFormat()->setFormatCode('0.00"%"');
                $ws->getStyle('B18')->getNumberFormat()->setFormatCode('0.00"%"');

                // section B
                $this->styleSectionHeader($ws, 'A20:D20');
                $ws->getRowDimension(20)->setRowHeight(22);
                $this->styleColHeader($ws, 'A21:D21');
                $ws->getRowDimension(21)->setRowHeight(22);
                $this->styleDataRows($ws, 22, 22, 'D');
                $this->formatCurrencyCol($ws, 'C22');

                // section C
                $this->styleSectionHeader($ws, 'A24:D24');
                $ws->getRowDimension(24)->setRowHeight(22);
                $this->styleColHeader($ws, 'A25:D25');
                $ws->getRowDimension(25)->setRowHeight(22);
                $this->styleDataRows($ws, 26, 26, 'D');
                $this->formatCurrencyCol($ws, 'B26');

                // Outer border
                $this->outerBorder($ws, 'A8:D18');
                $this->outerBorder($ws, 'A22:D22');
                $this->outerBorder($ws, 'A26:D26');

                // Freeze pane below headers
                $ws->freezePane('A8');

                // Left-align label column
                $ws->getStyle('A1:A26')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $ws->getStyle('D8:D18')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setWrapText(false);
            },
        ];
    }
}


