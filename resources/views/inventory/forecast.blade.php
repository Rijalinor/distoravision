@extends('layouts.app')
@section('page-title', 'Pure Sales Forecast')

@section('content')

@include('components.inventory-tabs')

@if(!$hasData)
<div class="card" style="text-align:center;padding:4rem 2rem;">
    <div style="font-size:3rem;margin-bottom:1rem;">🔮</div>
    <h2 style="font-size:1.2rem;margin-bottom:0.5rem;">Belum Ada Histori Transaksi</h2>
    <p style="color:var(--text-muted);margin-bottom:1.5rem;">Sistem membutuhkan histori transaksi penjualan untuk memprediksi kebutuhan.</p>
</div>
@else

{{-- FILTER --}}
<div class="card" style="margin-bottom:1.5rem;padding:0.75rem 1.25rem;">
    <form method="GET" style="display:flex;gap:0.75rem;align-items:center;flex-wrap:wrap;">
        <select name="period" class="period-select" onchange="this.form.submit()">
            @foreach($periods as $p)
                <option value="{{ $p }}" {{ $p === $period ? 'selected' : '' }}>Data Dasar: {{ \Carbon\Carbon::parse($p.'-01')->translatedFormat('F Y') }}</option>
            @endforeach
        </select>
        <select name="principal" class="period-select" style="max-width:220px;" onchange="this.form.submit()">
            <option value="all">Semua Principal</option>
            @foreach($forecasts->pluck('principal')->unique()->sort() as $pr)
                <option value="{{ $pr }}" {{ $selectedPrincipal === $pr ? 'selected' : '' }}>{{ Str::limit(str_replace('PT. ', '', $pr), 25) }}</option>
            @endforeach
        </select>
        @include('components.export-button')
    </form>
</div>

{{-- HEADER --}}
<div class="card" style="margin-bottom:1.5rem;background:linear-gradient(135deg,rgba(16,185,129,0.15),rgba(59,130,246,0.1));border:1px solid rgba(16,185,129,0.3);">
    <div style="display:flex;align-items:center;gap:1rem;">
        <div style="width:48px;height:48px;min-width:48px;border-radius:12px;background:linear-gradient(135deg,#10b981,#3b82f6);display:flex;align-items:center;justify-content:center;font-size:22px;">🔮</div>
        <div>
            <h2 style="font-size:1.15rem;font-weight:700;margin:0;">Demand Forecast untuk {{ $targetPeriodLabel }}</h2>
            <p style="color:var(--text-muted);font-size:0.8rem;margin:0;">Prediksi murni berbasis histori penjualan aktual (WMA & Seasonality) tanpa mempertimbangkan stok berjalan.
            </p>
        </div>
    </div>
</div>

{{-- KPI --}}
<div class="kpi-grid" style="margin-bottom:1.5rem;">
    <div class="card kpi-card">
        <div class="card-header"><span class="card-title">Total SKU Dianalisis</span></div>
        <div class="kpi-value">{{ number_format($totalItemsAnalyzed) }} <span style="font-size:1rem;color:var(--text-muted);">SKU</span></div>
    </div>
    <div class="card kpi-card">
        <div class="card-header"><span class="card-title">Total Kuantitas Diprediksi</span></div>
        <div class="kpi-value">{{ number_format($totalQtyForecast) }} <span style="font-size:1rem;color:var(--text-muted);">Karton</span></div>
    </div>
</div>

{{-- TABLE --}}
<div class="card">
    <div class="card-header">
        <span class="card-title">Proyeksi Penjualan 1 Bulan Kedepan</span>
    </div>
    
    <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Produk / Principal</th>
                    <th class="text-right">Toko Aktif (Beli)<br><span style="font-size:0.7rem;font-weight:normal;">Order Freq (T-1)</span></th>
                    <th class="text-right">Penjualan T-1<br><span style="font-size:0.7rem;font-weight:normal;">Aktual</span></th>
                    <th class="text-right">Baseline WMA<br><span style="font-size:0.7rem;font-weight:normal;">(Avg Bersih)</span></th>
                    <th class="text-center">Deteksi<br>Anomali</th>
                    <th class="text-right" style="background:rgba(16,185,129,0.1); border-left:1px solid rgba(255,255,255,0.1);">Prediksi<br><span style="font-size:0.7rem;color:var(--accent-green);font-weight:bold;">{{ $targetPeriodLabel }}</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach($forecasts as $f)
                <tr>
                    <td>
                        <div style="font-weight:600;">{{ Str::limit($f->item_name, 35) }}</div>
                        <div style="font-size:0.75rem;color:var(--text-muted);">{{ Str::limit(str_replace('PT. ', '', $f->principal), 25) }}</div>
                    </td>
                    <td class="text-right font-mono" style="color:var(--accent-blue);">
                        {{ number_format($f->t1_outlets) }} <span style="font-size:0.7rem;color:var(--text-muted);">Toko</span>
                    </td>
                    <td class="text-right font-mono">{{ number_format($f->t1_actual) }}</td>
                    <td class="text-right font-mono" style="color:var(--text-muted);">{{ number_format($f->wma_base) }}</td>
                    <td class="text-center">
                        @if(empty($f->flags))
                            <span style="color:var(--text-muted);">-</span>
                        @else
                            @if(in_array('stockout_drop', $f->flags)) <span title="Bulan lalu penjualan & toko aktif anjlok drastis" style="cursor:help;">📉 <span style="font-size:0.6rem;color:var(--text-muted);">Drop</span></span> @endif
                            @if(in_array('promo_spike', $f->flags)) <span title="Bulan lalu penjualan melonjak drastis, dipangkas oleh algoritma" style="cursor:help;">🧨 <span style="font-size:0.6rem;color:var(--text-muted);">Promo</span></span> @endif
                            @if(in_array('seasonal_up', $f->flags)) <span title="Bulan ini berpotensi naik tajam (Musiman)" style="cursor:help;">📈 <span style="font-size:0.6rem;color:var(--text-muted);">Season</span></span> @endif
                        @endif
                    </td>
                    <td class="text-right font-mono font-bold" style="background:rgba(16,185,129,0.02); border-left:1px solid rgba(255,255,255,0.05); color:var(--accent-green); font-size:1.1rem;">
                        {{ number_format($f->forecast_qty) }}
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@endif
@endsection
