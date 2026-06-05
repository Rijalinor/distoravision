@extends('layouts.app')
@section('page-title', 'Product Growth Trajectory (Trend 6 Bulan)')

@section('top-bar-actions')
<form method="GET" style="display:flex;gap:0.75rem;align-items:center;">
    @include('components.filter')
    @include('components.export-button')
</form>
@endsection

@section('content')
@include('components.ai-insight')

<div class="kpi-grid" style="grid-template-columns: repeat(5, 1fr);">
    <a href="{{ route('analytics.product-trajectory', ['segment' => 'Growing', 'period' => $period, 'principal_id' => request('principal_id', 'all')]) }}" 
       class="card kpi-card" style="text-decoration:none; border-top: 4px solid var(--accent-green); {{ $segment === 'Growing' ? 'background:rgba(16,185,129,0.1);' : '' }}">
        <div class="card-title text-center">📈 Growing</div>
        <div class="kpi-value text-green text-center">{{ $segments['Growing'] ?? 0 }}</div>
        <div class="kpi-label text-center">Tumbuh Positif</div>
    </a>
    
    <a href="{{ route('analytics.product-trajectory', ['segment' => 'Stable', 'period' => $period, 'principal_id' => request('principal_id', 'all')]) }}" 
       class="card kpi-card" style="text-decoration:none; border-top: 4px solid var(--accent-blue); {{ $segment === 'Stable' ? 'background:rgba(59,130,246,0.1);' : '' }}">
        <div class="card-title text-center">➡️ Stable</div>
        <div class="kpi-value text-blue text-center">{{ $segments['Stable'] ?? 0 }}</div>
        <div class="kpi-label text-center">Stabil / Konsisten</div>
    </a>

    <a href="{{ route('analytics.product-trajectory', ['segment' => 'Declining', 'period' => $period, 'principal_id' => request('principal_id', 'all')]) }}" 
       class="card kpi-card" style="text-decoration:none; border-top: 4px solid var(--accent-yellow); {{ $segment === 'Declining' ? 'background:rgba(245,158,11,0.1);' : '' }}">
        <div class="card-title text-center">📉 Declining</div>
        <div class="kpi-value text-yellow text-center" style="font-weight:900;">{{ $segments['Declining'] ?? 0 }}</div>
        <div class="kpi-label text-center">Menurun (Warning)</div>
    </a>

    <a href="{{ route('analytics.product-trajectory', ['segment' => 'New', 'period' => $period, 'principal_id' => request('principal_id', 'all')]) }}" 
       class="card kpi-card" style="text-decoration:none; border-top: 4px solid var(--primary-light); {{ $segment === 'New' ? 'background:rgba(99,102,241,0.1);' : '' }}">
        <div class="card-title text-center">🆕 New</div>
        <div class="kpi-value text-center" style="color:var(--primary-light);">{{ $segments['New'] ?? 0 }}</div>
        <div class="kpi-label text-center">SKU Baru</div>
    </a>

    <a href="{{ route('analytics.product-trajectory', ['segment' => 'Dead', 'period' => $period, 'principal_id' => request('principal_id', 'all')]) }}" 
       class="card kpi-card" style="text-decoration:none; border-top: 4px solid var(--accent-red); {{ $segment === 'Dead' ? 'background:rgba(239,68,68,0.1);' : '' }}">
        <div class="card-title text-center">💀 Dead</div>
        <div class="kpi-value text-red text-center">{{ $segments['Dead'] ?? 0 }}</div>
        <div class="kpi-label text-center">Mati / Dead-Stock</div>
    </a>
</div>

<div class="card" style="margin-top: 1.5rem;">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
        <span class="card-title">
            Katalog Produk (SKU)
            @if($segment !== 'all')
                <span class="badge" style="margin-left:0.5rem; background:var(--bg-dark); color:var(--text-primary); border:1px solid var(--border-color);">
                    Filter: {{ $segment }}
                </span>
                <a href="{{ route('analytics.product-trajectory', ['period' => $period, 'principal_id' => request('principal_id', 'all')]) }}" style="font-size:0.75rem; margin-left:0.5rem; color:var(--text-muted); text-decoration:underline;">Clear Filter</a>
            @endif
        </span>
    </div>
    
    <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Produk (SKU)</th>
                    <th class="text-center">Trend Class</th>
                    <th class="text-center" style="min-width: 150px;">Mini Trend (6 Bln)</th>
                    <th class="text-right">Sales Terakhir</th>
                    <th class="text-right">Rata-rata 6 Bln</th>
                    <th class="text-right">Total 6 Bln</th>
                </tr>
            </thead>
            <tbody>
                @forelse($trajectories as $t)
                <tr>
                    <td>
                        <div style="font-weight:600;">{{ Str::limit($t->product_name, 40) }}</div>
                        <div style="font-size:0.7rem;color:var(--text-muted);">
                            {{ $t->product_code }} | {{ $t->principal_name }} | Laku {{ $t->active_months }} bln
                        </div>
                    </td>
                    <td class="text-center">
                        <div style="font-size:1.5rem; line-height:1;">{{ $t->icon }}</div>
                        <div style="font-size:0.7rem; font-weight:600; margin-top:0.25rem;
                            color: {{ $t->classification === 'Declining' ? 'var(--accent-yellow)' : ($t->classification === 'Growing' ? 'var(--accent-green)' : ($t->classification === 'Dead' ? 'var(--accent-red)' : 'var(--text-muted)')) }}">
                            {{ $t->classification }}
                        </div>
                        @if(!in_array($t->classification, ['New', 'Dead']))
                            <div style="font-size:0.6rem; color:var(--text-muted);">Slope: {{ $t->slope_pct > 0 ? '+' : '' }}{{ $t->slope_pct }}%</div>
                        @endif
                    </td>
                    <td class="text-center" style="vertical-align: middle;">
                        <div style="display:flex; align-items:flex-end; justify-content:center; gap:2px; height:30px;">
                            @php
                                $maxVal = max(array_values($t->series));
                            @endphp
                            @foreach($periodRange as $p)
                                @php
                                    $val = $t->series[$p] ?? 0;
                                    $heightPct = $maxVal > 0 ? ($val / $maxVal) * 100 : 0;
                                    $color = 'var(--primary-light)';
                                    if ($val == 0) $color = 'var(--border-color)';
                                @endphp
                                <div style="width:18px; height:{{ max($heightPct, 5) }}%; background:{{ $color }}; border-radius:2px 2px 0 0;" 
                                     title="{{ \Carbon\Carbon::parse($p.'-01')->format('M y') }}: Rp {{ number_format($val, 0, ',', '.') }}"></div>
                            @endforeach
                        </div>
                        <div style="display:flex; justify-content:space-between; font-size:0.5rem; color:var(--text-muted); margin-top:4px; max-width: 120px; margin-left:auto; margin-right:auto;">
                            <span>{{ \Carbon\Carbon::parse($periodRange[0].'-01')->format('M') }}</span>
                            <span>{{ \Carbon\Carbon::parse($periodRange[5].'-01')->format('M') }}</span>
                        </div>
                    </td>
                    <td class="text-right font-mono font-bold" style="color: {{ $t->latest_sales <= 0 ? 'var(--accent-red)' : 'var(--text-primary)' }}">
                        Rp {{ number_format($t->latest_sales, 0, ',', '.') }}
                    </td>
                    <td class="text-right font-mono text-muted">
                        Rp {{ number_format($t->avg_sales, 0, ',', '.') }}
                    </td>
                    <td class="text-right font-mono text-muted">
                        Rp {{ number_format($t->total_sales, 0, ',', '.') }}
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--text-muted);">Tidak ada data produk</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
