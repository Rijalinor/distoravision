@extends('layouts.app')
@section('page-title', 'Profitabilitas per Salesman')

@section('top-bar-actions')
<form method="GET" style="display:flex;gap:0.75rem;align-items:center;">
    @include('components.filter')
    @include('components.export-button')
</form>
@endsection

@section('content')
@include('components.salesman-tabs')
@include('components.ai-insight')

<div class="kpi-grid" style="grid-template-columns: repeat(3, 1fr);">
    <div class="card kpi-card" style="border-top: 4px solid var(--accent-blue);">
        <div class="card-title">Total Pendapatan Bersih (Net Sales)</div>
        <div class="kpi-value text-blue">Rp {{ number_format($totalNetSales, 0, ',', '.') }}</div>
    </div>
    
    <div class="card kpi-card" style="border-top: 4px solid var(--accent-green);">
        <div class="card-title">Total Keuntungan Kotor (Gross Profit)</div>
        <div class="kpi-value text-green">Rp {{ number_format($totalGrossProfit, 0, ',', '.') }}</div>
    </div>

    <div class="card kpi-card" style="border-top: 4px solid var(--accent-yellow);">
        <div class="card-title">Rata-rata Margin Tim</div>
        <div class="kpi-value text-yellow">{{ number_format($avgMargin, 2) }}%</div>
    </div>
</div>

<div class="card" style="margin-top: 1.5rem;">
    <div class="card-header" style="display:flex; justify-content:space-between; align-items:center;">
        <span class="card-title">🏆 Ranking Profitabilitas Salesman</span>
        <form method="GET" style="display:flex; gap:0.5rem;">
            <input type="hidden" name="period" value="{{ request('period', $period) }}">
            <input type="hidden" name="start_period" value="{{ request('start_period', $period) }}">
            <input type="hidden" name="end_period" value="{{ request('end_period', $period) }}">
            <input type="hidden" name="principal_id" value="{{ request('principal_id', 'all') }}">
            <select name="sort" class="form-input" style="padding:0.25rem 0.5rem;font-size:0.8rem;" onchange="this.form.submit()">
                <option value="gross_profit" {{ $sortBy === 'gross_profit' ? 'selected' : '' }}>Urut Laba Terbesar</option>
                <option value="net_sales" {{ $sortBy === 'net_sales' ? 'selected' : '' }}>Urut Omset Terbesar</option>
                <option value="margin_percent" {{ $sortBy === 'margin_percent' ? 'selected' : '' }}>Urut % Margin</option>
                <option value="efficiency_ratio" {{ $sortBy === 'efficiency_ratio' ? 'selected' : '' }}>Urut Efisiensi Diskon</option>
            </select>
        </form>
    </div>
    
    <p style="padding:0.75rem 1rem 0;font-size:0.75rem;color:var(--text-muted);">
        <strong>Efisiensi:</strong> >1x berarti salesman menyumbang lebih banyak laba secara proporsional dibanding pangsa omsetnya (sehat). <0.8x berarti terlalu banyak obral diskon.
    </p>

    <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Salesman</th>
                    <th class="text-center">Pergeseran Rank<br><span style="font-size:0.65rem;font-weight:400;">(Rank Omset → Rank Laba)</span></th>
                    <th class="text-right">Net Sales</th>
                    <th class="text-right">Laba Kotor (Gross)</th>
                    <th class="text-right">Margin %</th>
                    <th class="text-right">Kedalaman Diskon</th>
                    <th class="text-right">Rasio Efisiensi</th>
                </tr>
            </thead>
            <tbody>
                @forelse($salesmen as $s)
                <tr>
                    <td>
                        <div style="font-weight:600;">{{ Str::limit($s->salesman_name, 25) }}</div>
                        <div style="font-size:0.7rem;color:var(--text-muted);">
                            {{ number_format($s->outlet_count) }} toko | Avg Rp {{ number_format($s->avg_per_outlet, 0, ',', '.') }}/toko
                        </div>
                    </td>
                    <td class="text-center">
                        <div style="display:flex;align-items:center;justify-content:center;gap:0.25rem;font-size:0.8rem;">
                            <span class="text-muted">#{{ $s->revenue_rank }}</span>
                            @if($s->rank_shift > 0)
                                <span class="text-green">↑</span>
                            @elseif($s->rank_shift < 0)
                                <span class="text-red">↓</span>
                            @else
                                <span class="text-muted">→</span>
                            @endif
                            <span class="font-bold">#{{ $s->profit_rank }}</span>
                        </div>
                        @if($s->rank_shift > 0)
                            <div style="font-size:0.65rem;color:var(--accent-green);">Naik {{ $s->rank_shift }} peringkat!</div>
                        @elseif($s->rank_shift < 0)
                            <div style="font-size:0.65rem;color:var(--accent-red);">Turun {{ abs($s->rank_shift) }} peringkat</div>
                        @endif
                    </td>
                    <td class="text-right font-mono" style="color:var(--text-muted);">
                        Rp {{ number_format($s->net_sales, 0, ',', '.') }}<br>
                        <span style="font-size:0.65rem;">({{ number_format($s->revenue_contribution, 1) }}% dr tim)</span>
                    </td>
                    <td class="text-right font-mono font-bold" style="color:var(--accent-green);font-size:1.1rem;">
                        Rp {{ number_format($s->gross_profit, 0, ',', '.') }}<br>
                        <span style="font-size:0.65rem;">({{ number_format($s->profit_contribution, 1) }}% dr tim)</span>
                    </td>
                    <td class="text-right">
                        <span class="badge {{ $s->margin_percent > 15 ? 'badge-green' : ($s->margin_percent > 10 ? 'badge-yellow' : 'badge-red') }}" style="font-size:0.85rem;">
                            {{ number_format($s->margin_percent, 1) }}%
                        </span>
                    </td>
                    <td class="text-right font-mono" style="color:var(--text-muted);">
                        {{ number_format($s->discount_depth, 1) }}%
                    </td>
                    <td class="text-right">
                        @if($s->efficiency_ratio > 1)
                            <span class="badge badge-green" style="font-size:0.85rem;">{{ number_format($s->efficiency_ratio, 2) }}x</span>
                        @elseif($s->efficiency_ratio >= 0.8)
                            <span class="badge badge-blue" style="font-size:0.85rem;">{{ number_format($s->efficiency_ratio, 2) }}x</span>
                        @else
                            <span class="badge badge-red" style="font-size:0.85rem;border:1px solid rgba(239,68,68,0.5);" title="Diskon/return menggerus margin lebih banyak daripada proporsi omset yang dibawa">
                                {{ number_format($s->efficiency_ratio, 2) }}x
                            </span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" style="text-align:center;padding:2rem;color:var(--text-muted);">Tidak ada data</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
