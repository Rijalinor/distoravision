@extends('layouts.app')
@section('page-title', 'Root Cause Analysis')

@section('top-bar-actions')
<form method="GET" style="display:flex;gap:0.75rem;align-items:center;">
    @include('components.filter')
</form>
@endsection

@section('content')
<style>
    .rca-movement-grid { display:grid; grid-template-columns:1.4fr 1fr 1fr; gap:1rem; align-items:stretch; }
    .rca-kpi-grid { grid-template-columns:repeat(4, 1fr); margin-bottom:1.25rem; }
    @media (max-width: 900px) {
        .rca-movement-grid, .rca-kpi-grid { grid-template-columns:1fr 1fr; }
    }
    @media (max-width: 560px) {
        .rca-movement-grid, .rca-kpi-grid { grid-template-columns:1fr; }
    }
</style>
@php
    $money = fn ($value) => 'Rp '.number_format(abs((float) $value), 0, ',', '.');
    $signedMoney = fn ($value) => ((float) $value >= 0 ? '+ ' : '- ').$money($value);
    $pct = fn ($value) => is_null($value) ? '-' : (((float) $value >= 0 ? '+' : '').number_format((float) $value, 1, ',', '.').'%');
    $trendColor = fn ($value) => (float) $value < 0 ? 'var(--accent-red)' : 'var(--accent-green)';
    $short = fn ($value, $limit = 34) => \Illuminate\Support\Str::limit(str_replace('PT. ', '', (string) $value), $limit);
@endphp

@include('components.ai-insight')

<x-page-guide
    title="Cara baca Root Cause Analysis"
    description="Halaman ini dipakai untuk menjawab kenapa Net Sales berubah dibanding periode sebelumnya."
    :details="$aiDetails ?? []"
    :steps="[
        'Lihat Hasil Utama Net Sales untuk tahu bisnis sedang naik atau turun.',
        'Baca Produk, Outlet, Salesman, dan Principal Paling Mempengaruhi untuk menemukan penyebab terbesar.',
        'Cek Retur, Stok, dan AR untuk menentukan tindak lanjut: dorong demand, bereskan barang, atau tagih outlet.',
    ]"
    :metrics="[
        'Naik/Turun' => 'Selisih angka dibanding periode sebelumnya yang durasinya sama. Jika filter 1 bulan, berarti dibanding bulan lalu.',
        'Penjualan Kotor' => 'Omset invoice sebelum dikurangi retur.',
        'Dampak Retur' => 'Retur yang membesar akan menekan Net Sales, walaupun Gross Sales naik.',
        'Sinyal Operasional' => 'Petunjuk awal apakah penurunan berhubungan dengan stok kritis atau AR overdue.',
    ]"
/>

<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-header" style="display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;flex-wrap:wrap;">
        <div>
            <div class="card-title">Peta Perubahan Net Sales</div>
            <div class="text-muted" style="font-size:0.78rem;margin-top:0.35rem;">
                Dibandingkan dengan periode sebelumnya: {{ \Carbon\Carbon::parse($prevStartPeriod.'-01')->translatedFormat('M Y') }} - {{ \Carbon\Carbon::parse($prevEndPeriod.'-01')->translatedFormat('M Y') }}
            </div>
        </div>
        <span class="badge badge-blue">{{ $rangeLabel }}</span>
    </div>

    <div class="rca-movement-grid">
        <div style="padding:1rem;border:1px solid var(--border-color);border-radius:8px;background:var(--bg-darker);">
            <div class="card-title" style="font-size:0.72rem;">1. Hasil Utama Net Sales</div>
            <div style="font-size:2rem;font-weight:900;color:{{ $trendColor($movement['net_sales']['delta']) }};line-height:1.1;margin-top:0.45rem;">
                {{ $signedMoney($movement['net_sales']['delta']) }}
            </div>
            <div class="text-muted" style="font-size:0.78rem;margin-top:0.45rem;">
                Net Sales sekarang {{ $money($currentKpis->net_sales) }}. Periode pembanding {{ $money($previousKpis->net_sales) }}.
            </div>
        </div>
        <div style="padding:1rem;border:1px solid var(--border-color);border-radius:8px;background:var(--bg-darker);">
            <div class="card-title" style="font-size:0.72rem;">Penjualan Kotor</div>
            <div style="font-size:1.35rem;font-weight:900;color:{{ $trendColor($movement['gross_sales']['delta']) }};margin-top:0.55rem;">
                {{ $signedMoney($movement['gross_sales']['delta']) }}
            </div>
            <div class="text-muted" style="font-size:0.75rem;margin-top:0.4rem;">Sebelum dikurangi retur. {{ $pct($movement['gross_sales']['pct']) }} vs pembanding.</div>
        </div>
        <div style="padding:1rem;border:1px solid var(--border-color);border-radius:8px;background:var(--bg-darker);">
            <div class="card-title" style="font-size:0.72rem;">Dampak Retur</div>
            <div style="font-size:1.35rem;font-weight:900;color:{{ $movement['returns']['delta'] > 0 ? 'var(--accent-red)' : 'var(--accent-green)' }};margin-top:0.55rem;">
                {{ $signedMoney($movement['returns']['delta']) }}
            </div>
            <div class="text-muted" style="font-size:0.75rem;margin-top:0.4rem;">Kalau merah berarti retur membesar. Return rate {{ number_format($currentKpis->return_rate, 1, ',', '.') }}%</div>
        </div>
    </div>
</div>

<div class="kpi-grid rca-kpi-grid">
    <div class="card kpi-card">
        <div class="card-title">Invoice</div>
        <div class="kpi-value">{{ number_format($currentKpis->invoice_count) }}</div>
        <div class="kpi-label" style="color:{{ $trendColor($movement['invoice_count']['delta']) }};">{{ $movement['invoice_count']['delta'] >= 0 ? '+' : '' }}{{ number_format($movement['invoice_count']['delta']) }} transaksi</div>
    </div>
    <div class="card kpi-card">
        <div class="card-title">Outlet Aktif</div>
        <div class="kpi-value">{{ number_format($currentKpis->active_outlets) }}</div>
        <div class="kpi-label" style="color:{{ $trendColor($movement['active_outlets']['delta']) }};">{{ $movement['active_outlets']['delta'] >= 0 ? '+' : '' }}{{ number_format($movement['active_outlets']['delta']) }} outlet</div>
    </div>
    <div class="card kpi-card">
        <div class="card-title">SKU Aktif</div>
        <div class="kpi-value">{{ number_format($currentKpis->active_skus) }}</div>
        <div class="kpi-label" style="color:{{ $trendColor($movement['active_skus']['delta']) }};">{{ $movement['active_skus']['delta'] >= 0 ? '+' : '' }}{{ number_format($movement['active_skus']['delta']) }} SKU</div>
    </div>
    <div class="card kpi-card">
        <div class="card-title">Return Rate</div>
        <div class="kpi-value">{{ number_format($currentKpis->return_rate, 1, ',', '.') }}%</div>
        <div class="kpi-label" style="color:{{ $movement['return_rate']['delta'] > 0 ? 'var(--accent-red)' : 'var(--accent-green)' }};">{{ $pct($movement['return_rate']['delta']) }} poin</div>
    </div>
</div>

<div class="grid-2" style="gap:1.25rem;">
    @foreach([
        'products' => ['title' => '2. Produk Paling Mempengaruhi', 'meta' => 'Principal'],
        'outlets' => ['title' => '2. Outlet Paling Mempengaruhi', 'meta' => 'Kota'],
        'salesmen' => ['title' => '2. Salesman Paling Mempengaruhi', 'meta' => 'Kode'],
        'principals' => ['title' => '2. Principal Paling Mempengaruhi', 'meta' => ''],
    ] as $key => $config)
    <div class="card">
        <div class="card-header">
            <span class="card-title">{{ $config['title'] }}</span>
        </div>
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Nama</th>
                        <th class="text-right">Net Sales</th>
                        <th class="text-right">Naik/Turun</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($drivers[$key] as $row)
                    <tr>
                        <td>
                            <div style="font-weight:700;">{{ $short($row->name, 38) }}</div>
                            <div class="text-muted" style="font-size:0.68rem;">{{ $row->code }}{{ $row->meta !== '-' ? ' | '.$short($row->meta, 28) : '' }}</div>
                        </td>
                        <td class="text-right font-mono">{{ $money($row->current) }}</td>
                        <td class="text-right font-mono" style="font-weight:800;color:{{ $trendColor($row->delta) }};">{{ $signedMoney($row->delta) }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="3" class="text-center text-muted" style="padding:1.5rem;">Tidak ada driver</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endforeach
</div>

<div class="grid-2" style="gap:1.25rem;margin-top:1.25rem;">
    <div class="card">
        <div class="card-header">
            <span class="card-title">3. Produk yang Returnya Membesar</span>
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Produk</th>
                    <th class="text-right">Retur Saat Ini</th>
                    <th class="text-right">Naik/Turun</th>
                </tr>
            </thead>
            <tbody>
                @forelse($returnDrivers as $row)
                <tr>
                    <td>
                        <div style="font-weight:700;">{{ $short($row->name, 38) }}</div>
                        <div class="text-muted" style="font-size:0.68rem;">{{ $short($row->meta, 34) }}</div>
                    </td>
                    <td class="text-right font-mono">{{ $money($row->current) }}</td>
                    <td class="text-right font-mono" style="font-weight:800;color:{{ $row->delta > 0 ? 'var(--accent-red)' : 'var(--accent-green)' }};">{{ $signedMoney($row->delta) }}</td>
                </tr>
                @empty
                <tr><td colspan="3" class="text-center text-muted" style="padding:1.5rem;">Tidak ada retur signifikan</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title">3. Sinyal yang Perlu Dicek Tim</span>
        </div>
        <div style="display:grid;gap:0.75rem;">
            <div style="border:1px solid var(--border-color);border-radius:8px;padding:0.9rem;background:var(--bg-darker);">
                <div style="font-weight:800;margin-bottom:0.55rem;">Stok Produk Turun</div>
                @forelse($stockSignals as $row)
                    <div style="display:flex;justify-content:space-between;gap:1rem;padding:0.45rem 0;border-top:1px solid var(--border-color);">
                        <div>
                            <div style="font-weight:700;">{{ $short($row->item_name, 38) }}</div>
                            <div class="text-muted" style="font-size:0.68rem;">{{ $row->item_no }} | {{ $short($row->warehouse_name, 24) }}</div>
                        </div>
                        <div class="text-right font-mono" style="color:var(--accent-yellow);">SWC {{ number_format((float) $row->swc, 1, ',', '.') }}</div>
                    </div>
                @empty
                    <div class="text-muted" style="font-size:0.78rem;">Belum ada sinyal stok kritis pada produk yang turun.</div>
                @endforelse
            </div>

            <div style="border:1px solid var(--border-color);border-radius:8px;padding:0.9rem;background:var(--bg-darker);">
                <div style="font-weight:800;margin-bottom:0.55rem;">AR Outlet Turun</div>
                @forelse($arSignals as $row)
                    <div style="display:flex;justify-content:space-between;gap:1rem;padding:0.45rem 0;border-top:1px solid var(--border-color);">
                        <div>
                            <div style="font-weight:700;">{{ $short($row->outlet_name, 38) }}</div>
                            <div class="text-muted" style="font-size:0.68rem;">{{ $row->outlet_code }} | {{ $short($row->salesman_name, 24) }}</div>
                        </div>
                        <div class="text-right font-mono" style="color:var(--accent-red);">{{ $money($row->ar_balance) }}</div>
                    </div>
                @empty
                    <div class="text-muted" style="font-size:0.78rem;">Belum ada AR overdue pada outlet driver penurunan.</div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection
