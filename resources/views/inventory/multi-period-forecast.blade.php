@extends('layouts.app')
@section('page-title', 'Pure Sales Forecast')

@section('content')

@include('components.inventory-tabs')

@if(!$hasData)
<div class="card" style="text-align:center;padding:4rem 2rem;">
    <div style="font-size:3rem;margin-bottom:1rem;">🔮</div>
    <h2 style="font-size:1.2rem;margin-bottom:0.5rem;">Belum Ada Histori Transaksi</h2>
    <p style="color:var(--text-muted);margin-bottom:1.5rem;">Sistem membutuhkan histori transaksi penjualan untuk memprediksi kebutuhan masa depan.</p>
</div>
@else

{{-- FILTER --}}
<div class="card" style="margin-bottom:1.5rem;padding:0.75rem 1.25rem;">
    <form method="GET" action="{{ route('inventory.forecast.multi-period') }}" style="display:flex;gap:0.75rem;align-items:center;flex-wrap:wrap;">
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

{{-- HEADER & AI INSIGHT --}}
<div class="card" style="margin-bottom:1.5rem;background:linear-gradient(135deg,rgba(99,102,241,0.1),rgba(168,85,247,0.1));border:1px solid rgba(168,85,247,0.3);">
    <div style="display:flex;align-items:flex-start;gap:1rem;">
        <div style="width:48px;height:48px;min-width:48px;border-radius:12px;background:linear-gradient(135deg,#6366f1,#a855f7);display:flex;align-items:center;justify-content:center;font-size:22px;">🤖</div>
        <div>
            <h2 style="font-size:1.15rem;font-weight:700;margin:0;">Distora AI: Multi-Period Analyzer (T+6)</h2>
            <p style="color:var(--text-primary);font-size:0.85rem;margin:0.5rem 0 0 0; line-height:1.5; white-space:pre-wrap;">{{ $aiNarrative }}</p>
        </div>
    </div>
</div>

{{-- KPI --}}
<div class="kpi-grid" style="margin-bottom:1.5rem;">
    <div class="card kpi-card">
        <div class="card-header"><span class="card-title">Total Produk Dianalisa</span></div>
        <div class="kpi-value">{{ number_format($totalAnalyzed) }} <span style="font-size:1rem;color:var(--text-muted);">SKU</span></div>
    </div>
    <div class="card kpi-card" style="border-top:4px solid var(--accent-blue); background:rgba(59,130,246,0.05);">
        <div class="card-header"><span class="card-title">📈 Peluang Naik Daun (Trending Up)</span></div>
        <div class="kpi-value" style="color:var(--accent-blue);">{{ number_format($trendingUpCount) }} <span style="font-size:1rem;color:var(--text-muted);">SKU</span></div>
        <div class="kpi-label">Proyeksi lonjakan tinggi di 6 bulan ke depan</div>
    </div>
</div>

{{-- TABLE --}}
<div class="card">
    <div class="card-header">
        <span class="card-title">Prediksi & Peringkat Produk Terlaris (6 Bulan ke Depan)</span>
    </div>
    
    <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Produk / Principal</th>
                    <th class="text-right" style="min-width:70px;">Toko Aktif<br><span style="font-weight:normal;font-size:0.7rem;">Bulan Lalu</span></th>
                    <th class="text-right" style="min-width:70px;background:rgba(255,255,255,0.05);border-right:2px solid var(--border-color);">Total 6 Bln<br>Proyeksi Qty</th>
                    
                    @for($i = 1; $i <= 6; $i++)
                        <th class="text-right" style="min-width:70px; font-size:0.75rem; font-weight:normal;">
                            <strong style="display:block;font-size:0.85rem;margin-bottom:2px;">{{ $targetPeriods[$i]['label'] }}</strong>
                            (T+{{ $i }})
                        </th>
                    @endfor
                    
                    <th class="text-center" style="min-width:120px;">Trend / Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach($forecasts as $f)
                <tr>
                    <td>
                        <div style="font-weight:600;">{{ Str::limit($f->item_name, 30) }}</div>
                        <div style="font-size:0.75rem;color:var(--text-muted);">{{ Str::limit(str_replace('PT. ', '', $f->principal), 25) }}</div>
                    </td>
                    
                    <td class="text-right font-mono" style="color:var(--accent-blue);">
                        {{ number_format($f->t1_outlets) }} <span style="font-size:0.7rem;color:var(--text-muted);">Toko</span>
                    </td>
                    
                    <td class="text-right font-mono font-bold" style="background:rgba(255,255,255,0.02);border-right:2px solid var(--border-color); color:var(--accent-green); font-size:1.1rem;">
                        {{ number_format($f->total_6_month) }}
                    </td>
                    
                    @for($i = 1; $i <= 6; $i++)
                        <td class="text-right font-mono text-muted">
                            {{ number_format($f->multi_forecast[$i]) }}
                        </td>
                    @endfor
                    
                    <td class="text-center" style="white-space:nowrap;font-weight:600;font-size:0.85rem;">
                        {{ $f->icon }} 
                        <span style="color: {{ $f->status == 'Trending Up' ? 'var(--accent-blue)' : ($f->status == 'Trending Down' ? 'var(--accent-red)' : 'var(--text-muted)') }}">
                            {{ $f->status }}
                        </span>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

@endif
@endsection
