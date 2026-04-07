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
            ->selectRaw('SUM(ar_amt) as net_sales, SUM(cogs) as total_cogs, SUM(gross) as gross_sales, SUM(disc_total) as total_discount, COUNT(DISTINCT so_no) as invoice_count, COUNT(DISTINCT outlet_id) as outlet_count')
            ->first();

        $netSales     = (float) ($kpis->net_sales ?? 0);
        $totalCogs    = (float) ($kpis->total_cogs ?? 0);
        $totalReturns = (float) Transaction::withFilters($this->request)->returns()->sum(DB::raw('ABS(ar_amt)'));
        $grossProfit  = $netSales - $totalCogs;
        $totalDiscou  = (float) ($kpis->total_discount ?? 0);
        $blendedMargi = $netSales > 0 ? ($grossProfit / $netSales) * 100 : 0;
        $returnRate   = ($netSales + $totalReturns) > 0 ? ($totalReturns / ($netSales + $totalReturns)) * 100 : 0;

        $prevPeriod  = \Carbon\Carbon::parse($this->period . '-01')->subMonth()->format('Y-m');
        $prevReq     = new \Illuminate\Http\Request();
        $prevReq->merge(['start_period' => $prevPeriod, 'end_period' => $prevPeriod, 'principal_id' => $this->request->get('principal_id')]);
        $prevSales   = (float) Transaction::withFilters($prevReq)->invoices()->sum('ar_amt');
        $prevReturns = (float) Transaction::withFilters($prevReq)->returns()->sum(DB::raw('ABS(ar_amt)'));
        $prevNet     = $prevSales - $prevReturns;
        $momNet      = $prevNet > 0 ? (($netSales - $prevNet) / $prevNet) * 100 : 0;

        $prevOutlets    = Transaction::withFilters($prevReq)->invoices()->groupBy('outlet_id')->having(DB::raw('SUM(ar_amt)'), '>', 0)->pluck('outlet_id')->unique();
        $currentOutlets = Transaction::withFilters($this->request)->invoices()->groupBy('outlet_id')->having(DB::raw('SUM(ar_amt)'), '>', 0)->pluck('outlet_id')->unique();
        $churnedIds     = $prevOutlets->diff($currentOutlets);
        $churnedCount   = $churnedIds->count();
        $churnedLoss    = $churnedCount > 0 ? (float) Transaction::withFilters($prevReq)->invoices()->whereIn('outlet_id', $churnedIds)->sum('ar_amt') : 0;

        $topPrincipal = Transaction::withFilters($this->request)->invoices()
            ->join('products', 'transactions.product_id', '=', 'products.id')
            ->join('principals', 'products.principal_id', '=', 'principals.id')
            ->select('principals.name', DB::raw('SUM(transactions.ar_amt) as rev'))
            ->groupBy('principals.name')->orderByDesc('rev')->first();

        $this->computed = compact(
            'netSales', 'totalCogs', 'totalReturns', 'grossProfit', 'totalDiscou',
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
            /* 8 */ ['Net Sales (AR)',         $netSales,     $prevNet,  'Sales bersih bulan ini vs bulan lalu'],
            /* 9 */ ['Total Retur',            $totalReturns, '',        'Nilai retur BAST yang dikembalikan'],
            /* 10*/['Return Rate',             $returnRate,   '',        'Persentase retur terhadap gross sales'],
            /* 11*/['HPP (COGS)',              $totalCogs,    '',        'Harga pokok penjualan'],
            /* 12*/['Gross Profit',            $grossProfit,  '',        'Laba kotor sebelum biaya operasional'],
            /* 13*/['Blended Margin',          $blendedMargi, '',        'Rata-rata % keuntungan keseluruhan'],
            /* 14*/['Total Diskon Diberikan',  $totalDiscou,  '',        'Subsidi promo ke toko-toko'],
            /* 15*/['Total Invoice / Faktur',  (int)($kpis->invoice_count ?? 0), '', 'Jumlah faktur penjualan unik'],
            /* 16*/['Outlet Aktif',            (int)($kpis->outlet_count ?? 0),  '', 'Toko yang bertransaksi bulan ini'],
            /* 17*/['MoM Growth (Net Sales)',  $momNet,       '',        'Pertumbuhan vs bulan sebelumnya'],
            /* 18*/ [],
            /* 19*/ ['B.   PERINGATAN DINI & RISIKO BISNIS', '', '', ''],
            /* 20*/ ['Indikator Risiko', 'Jumlah', 'Nilai Opportunity Loss (Rp)', 'Status'],
            /* 21*/ ['Toko Churn / Berhenti Order', $churnedCount, $churnedLoss, $churnedCount > 10 ? '⚠️ WASPADA' : ($churnedCount > 0 ? '🟡 PANTAU' : '✅ AMAN')],
            /* 22*/ [],
            /* 23*/ ['C.   PRINCIPAL DOMINASI OMSET', '', '', ''],
            /* 24*/ ['Principal', 'Revenue (Rp)', '', ''],
            /* 25*/ [$topPrincipal->name ?? 'N/A', (float)($topPrincipal->rev ?? 0), '', ''],
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

                // Data rows 8-17
                $this->styleDataRows($ws, 8, 17, 'D');

                // Currency columns B & C for data rows
                $this->formatCurrencyCol($ws, 'B8:B17');
                $this->formatCurrencyCol($ws, 'C8:C17');

                // Percent formatting on specific rows
                $ws->getStyle('B10')->getNumberFormat()->setFormatCode('0.00"%"');
                $ws->getStyle('B13')->getNumberFormat()->setFormatCode('0.00"%"');
                $ws->getStyle('B17')->getNumberFormat()->setFormatCode('0.00"%"');

                // section B
                $this->styleSectionHeader($ws, 'A19:D19');
                $ws->getRowDimension(19)->setRowHeight(22);
                $this->styleColHeader($ws, 'A20:D20');
                $ws->getRowDimension(20)->setRowHeight(22);
                $this->styleDataRows($ws, 21, 21, 'D');
                $this->formatCurrencyCol($ws, 'C21');

                // section C
                $this->styleSectionHeader($ws, 'A23:D23');
                $ws->getRowDimension(23)->setRowHeight(22);
                $this->styleColHeader($ws, 'A24:D24');
                $ws->getRowDimension(24)->setRowHeight(22);
                $this->styleDataRows($ws, 25, 25, 'D');
                $this->formatCurrencyCol($ws, 'B25');

                // Outer border
                $this->outerBorder($ws, 'A8:D17');
                $this->outerBorder($ws, 'A21:D21');
                $this->outerBorder($ws, 'A25:D25');

                // Freeze pane below headers
                $ws->freezePane('A8');

                // Left-align label column
                $ws->getStyle('A1:A25')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                $ws->getStyle('D8:D17')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT)->setWrapText(false);
            },
        ];
    }
}
