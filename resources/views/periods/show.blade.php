@extends('layouts.app')
@section('page-title', 'Snapshot — ' . $period->label)

@section('content')
@php $s = $period->snapshot; @endphp

<div style="margin-bottom: 1.5rem; display:flex; align-items:center; gap:1rem; flex-wrap:wrap;">
    <a href="{{ route('periods.index') }}" class="btn btn-secondary" style="font-size:0.8rem;">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
        Kembali
    </a>
    <div>
        <span class="badge badge-red" style="font-size:0.75rem;">🔒 CLOSED</span>
        <span style="color:var(--text-muted); font-size:0.8rem; margin-left:0.5rem;">
            Ditutup oleh {{ $period->closedByUser?->name ?? '-' }} pada {{ $period->closed_at?->format('d M Y, H:i') }}
        </span>
    </div>
</div>

@if($period->notes)
<div class="alert" style="background:rgba(99,102,241,0.1); border:1px solid rgba(99,102,241,0.3); color:var(--primary-light); margin-bottom:1.5rem;">
    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"></path></svg>
    <strong>Catatan:</strong> {{ $period->notes }}
</div>
@endif

{{-- ═══════════════════════════════════════════════════════════════
     SECTION 1: SALES SNAPSHOT
     ═══════════════════════════════════════════════════════════════ --}}
<h2 style="font-size:1rem; font-weight:700; margin-bottom:1rem; color:var(--primary-light);">
    📊 Sales Performance — {{ $period->label }}
</h2>

<div class="kpi-grid" style="margin-bottom:1.5rem;">
    <div class="card kpi-card">
        <div class="card-header">
            <span class="card-title">Total Sales</span>
            <div class="kpi-icon green"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg></div>
        </div>
        <div class="kpi-value">Rp {{ number_format($s->total_sales / 1000, 0, ',', '.') }}K</div>
        <div class="kpi-label">{{ number_format($s->invoice_count) }} invoice</div>
    </div>
    <div class="card kpi-card">
        <div class="card-header">
            <span class="card-title">Total Returns</span>
            <div class="kpi-icon red"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path></svg></div>
        </div>
        <div class="kpi-value" style="-webkit-text-fill-color:var(--accent-red);">Rp {{ number_format($s->total_returns / 1000, 0, ',', '.') }}K</div>
        <div class="kpi-label">{{ number_format($s->return_count) }} return</div>
    </div>
    <div class="card kpi-card">
        <div class="card-header">
            <span class="card-title">Net Sales</span>
            <div class="kpi-icon blue"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></div>
        </div>
        <div class="kpi-value">Rp {{ number_format($s->net_sales / 1000, 0, ',', '.') }}K</div>
        <div class="kpi-label">Setelah return</div>
    </div>
    <div class="card kpi-card">
        <div class="card-header">
            <span class="card-title">Margin & Return Rate</span>
            <div class="kpi-icon yellow"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg></div>
        </div>
        <div class="kpi-value" style="-webkit-text-fill-color:{{ $s->return_rate > 10 ? 'var(--accent-red)' : 'var(--accent-green)' }};">{{ number_format($s->return_rate, 1) }}%</div>
        <div class="kpi-label">Margin: {{ number_format($s->margin, 1) }}%</div>
    </div>
</div>

<div class="grid-2" style="margin-bottom: 2rem;">
    {{-- Top Products --}}
    <div class="card">
        <div class="card-header"><span class="card-title">Top 10 Produk</span></div>
        @if(!empty($s->top_products))
        <table class="data-table">
            <thead><tr><th>#</th><th>Produk</th><th class="text-right">Sales</th></tr></thead>
            <tbody>
            @foreach($s->top_products as $i => $p)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ Str::limit($p['name'] ?? '-', 35) }}</td>
                    <td class="text-right font-mono">Rp {{ number_format($p['total_sales'] ?? 0, 0, ',', '.') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @else
        <p style="color:var(--text-muted); font-size:0.85rem; text-align:center; padding:2rem;">Tidak ada data</p>
        @endif
    </div>

    {{-- Top Outlets --}}
    <div class="card">
        <div class="card-header"><span class="card-title">Top 10 Outlet</span></div>
        @if(!empty($s->top_outlets))
        <table class="data-table">
            <thead><tr><th>#</th><th>Outlet</th><th>Kota</th><th class="text-right">Sales</th></tr></thead>
            <tbody>
            @foreach($s->top_outlets as $i => $o)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td>{{ Str::limit($o['name'] ?? '-', 25) }}</td>
                    <td><span class="badge badge-blue">{{ $o['city'] ?? '-' }}</span></td>
                    <td class="text-right font-mono">Rp {{ number_format($o['total_sales'] ?? 0, 0, ',', '.') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @else
        <p style="color:var(--text-muted); font-size:0.85rem; text-align:center; padding:2rem;">Tidak ada data</p>
        @endif
    </div>
</div>

{{-- Principal Breakdown --}}
@if(!empty($s->principal_breakdown))
<div class="card" style="margin-bottom:2rem;">
    <div class="card-header"><span class="card-title">Revenue per Principal</span></div>
    <table class="data-table">
        <thead><tr><th>#</th><th>Principal</th><th class="text-right">Total Sales</th><th class="text-right">Kontribusi</th></tr></thead>
        <tbody>
        @php $grandTotal = collect($s->principal_breakdown)->sum('total_sales'); @endphp
        @foreach($s->principal_breakdown as $i => $pb)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $pb['name'] ?? '-' }}</td>
                <td class="text-right font-mono">Rp {{ number_format($pb['total_sales'] ?? 0, 0, ',', '.') }}</td>
                <td class="text-right">
                    <span class="badge badge-blue">{{ $grandTotal > 0 ? number_format(($pb['total_sales'] / $grandTotal) * 100, 1) : 0 }}%</span>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- ═══════════════════════════════════════════════════════════════
     SECTION 2: AR (PIUTANG) SNAPSHOT
     ═══════════════════════════════════════════════════════════════ --}}
<h2 style="font-size:1rem; font-weight:700; margin-bottom:1rem; color:var(--accent-yellow);">
    💰 AR (Piutang) Snapshot — {{ $period->label }}
</h2>

<div class="kpi-grid" style="margin-bottom:1.5rem;">
    <div class="card kpi-card">
        <div class="card-header">
            <span class="card-title">Total Outstanding</span>
            <div class="kpi-icon red"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></div>
        </div>
        <div class="kpi-value" style="-webkit-text-fill-color:var(--accent-red);">Rp {{ number_format($s->total_outstanding / 1000000, 1, ',', '.') }}M</div>
        <div class="kpi-label">{{ number_format($s->ar_invoice_count) }} invoice · {{ number_format($s->ar_outlet_count) }} outlet</div>
    </div>
    <div class="card kpi-card">
        <div class="card-header">
            <span class="card-title">Total Overdue</span>
            <div class="kpi-icon yellow"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></div>
        </div>
        <div class="kpi-value" style="-webkit-text-fill-color:var(--accent-yellow);">Rp {{ number_format($s->total_overdue / 1000000, 1, ',', '.') }}M</div>
        <div class="kpi-label">Rata-rata {{ $s->avg_overdue_days }} hari · Max {{ $s->max_overdue_days }} hari</div>
    </div>
    <div class="card kpi-card">
        <div class="card-header">
            <span class="card-title">Total AR Amount</span>
            <div class="kpi-icon blue"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21l-7-5-7 5V5a2 2 0 012-2h10a2 2 0 012 2v16z"></path></svg></div>
        </div>
        <div class="kpi-value">Rp {{ number_format($s->total_ar_amount / 1000000, 1, ',', '.') }}M</div>
        <div class="kpi-label">Total tagihan</div>
    </div>
    <div class="card kpi-card">
        <div class="card-header">
            <span class="card-title">Total Terbayar</span>
            <div class="kpi-icon green"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></div>
        </div>
        <div class="kpi-value" style="-webkit-text-fill-color:var(--accent-green);">Rp {{ number_format($s->total_ar_paid / 1000000, 1, ',', '.') }}M</div>
        <div class="kpi-label">Sudah dibayar</div>
    </div>
</div>

{{-- Aging Distribution --}}
@if(!empty($s->aging_data))
<div class="card" style="margin-bottom:1.5rem;">
    <div class="card-header"><span class="card-title">Aging Distribution</span></div>
    <div style="display:grid; grid-template-columns:repeat(5, 1fr); gap:0.75rem; text-align:center;">
        @php
            $agingColors = ['Current' => 'green', '1-30' => 'blue', '31-60' => 'yellow', '61-90' => 'red', '>90' => 'red'];
            $agingTotal = collect($s->aging_data)->sum('total');
        @endphp
        @foreach($s->aging_data as $bucket => $data)
        <div class="card" style="padding:1rem;">
            <div style="font-size:0.7rem; font-weight:600; color:var(--text-muted); text-transform:uppercase; margin-bottom:0.5rem;">{{ $bucket }} Hari</div>
            <div style="font-size:1.1rem; font-weight:800; color:var(--accent-{{ $agingColors[$bucket] ?? 'blue' }});">
                Rp {{ number_format(($data['total'] ?? 0) / 1000000, 1, ',', '.') }}M
            </div>
            <div style="font-size:0.7rem; color:var(--text-muted); margin-top:0.25rem;">
                {{ number_format($data['count'] ?? 0) }} invoice
                @if($agingTotal > 0)
                · {{ number_format((($data['total'] ?? 0) / $agingTotal) * 100, 1) }}%
                @endif
            </div>
        </div>
        @endforeach
    </div>
</div>
@endif

{{-- Salesman AR Data --}}
@if(!empty($s->salesman_ar_data))
<div class="card" style="margin-bottom:2rem;">
    <div class="card-header"><span class="card-title">AR per Salesman</span></div>
    <table class="data-table">
        <thead>
            <tr>
                <th>#</th>
                <th>Salesman</th>
                <th class="text-right">Outstanding</th>
                <th class="text-right">Outlet</th>
                <th class="text-right">Invoice</th>
                <th class="text-right">Max Overdue</th>
            </tr>
        </thead>
        <tbody>
        @foreach($s->salesman_ar_data as $i => $sd)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ $sd['salesman_name'] ?? '-' }}</td>
                <td class="text-right font-mono">Rp {{ number_format($sd['total_balance'] ?? 0, 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($sd['outlet_count'] ?? 0) }}</td>
                <td class="text-right">{{ number_format($sd['invoice_count'] ?? 0) }}</td>
                <td class="text-right">
                    <span class="badge {{ ($sd['max_overdue'] ?? 0) > 60 ? 'badge-red' : (($sd['max_overdue'] ?? 0) > 30 ? 'badge-yellow' : 'badge-green') }}">
                        {{ $sd['max_overdue'] ?? 0 }} hari
                    </span>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- Snapshot metadata --}}
<div style="text-align:center; padding:1rem; color:var(--text-muted); font-size:0.75rem;">
    Snapshot dibuat pada {{ $s->snapshot_at?->format('d M Y, H:i:s') ?? '-' }} · Data beku, tidak berubah walau data live ter-update.
</div>
@endsection
