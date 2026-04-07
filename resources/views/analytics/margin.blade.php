@extends('layouts.app')
@section('page-title', 'Profitabilitas & Margin Intelligence')
@section('top-bar-actions')
<form method="GET" style="display:flex;gap:0.75rem;align-items:center;">
    @include('components.filter')
</form>
@endsection

@section('content')
<div class="kpi-grid" style="grid-template-columns: repeat(4, 1fr);">
    <div class="card kpi-card" style="border-top: 4px solid var(--accent-blue);">
        <div class="card-title" title="Total Pendapatan Kotor (Net Sales) sebelum dikurangi harga modal produksi barang.">Net Sales (Pendapatan)</div>
        <div class="kpi-value text-blue">Rp {{ number_format($totalRevenue, 0, ',', '.') }}</div>
        <div class="kpi-label">Total Penjualan Bersih</div>
    </div>
    
    <div class="card kpi-card" style="border-top: 4px solid var(--accent-red);">
        <div class="card-title" title="Cost of Goods Sold (Harga Pokok Penjualan). Total modal harga dasar dari seluruh barang yang laku terjual.">Total COGS (HPP)</div>
        <div class="kpi-value text-red">Rp {{ number_format($totalCogs, 0, ',', '.') }}</div>
        <div class="kpi-label">Modal Pokok Barang</div>
    </div>

    <div class="card kpi-card" style="border-top: 4px solid var(--accent-green);">
        <div class="card-title" title="Keuntungan murni (Gross Profit) yang didapat dari Net Sales dikurangi COGS. Uang ini belum dipotong biaya operasional perusahaan.">Gross Profit (Laba Kotor)</div>
        <div class="kpi-value text-green">Rp {{ number_format($totalGrossProfit, 0, ',', '.') }}</div>
        <div class="kpi-label">Uang Tunai Murni</div>
    </div>

    <div class="card kpi-card" style="border-top: 4px solid var(--accent-yellow);">
        <div class="card-title" title="Rata-rata persentase margin keuntungan dari keseluruhan omset. Rumus: (Gross Profit ÷ Net Sales) × 100%. Semakin tinggi semakin sehat bisnisnya.">Blended Margin %</div>
        <div class="kpi-value text-yellow">{{ number_format($blendedMargin, 2) }}%</div>
        <div class="kpi-label">Persentase Keuntungan Rata-Rata</div>
    </div>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-top: 1.5rem;">
    
    <!-- Profil Principal -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Ranking Margin Berdasarkan Principal</span>
        </div>
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Principal</th>
                        <th class="text-right">Revenue</th>
                        <th class="text-right">Laba Kotor</th>
                        <th class="text-right">Margin %</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($principalMargins as $pm)
                    <tr>
                        <td class="font-bold">{{ $pm->principal_name }}</td>
                        <td class="text-right font-mono text-muted">Rp {{ number_format($pm->revenue, 0, ',', '.') }}</td>
                        <td class="text-right font-mono text-green font-bold">Rp {{ number_format($pm->gross_profit, 0, ',', '.') }}</td>
                        <td class="text-right">
                            @php
                                $badgeClass = $pm->margin_percent >= 20 ? 'badge-green' : ($pm->margin_percent >= 10 ? 'badge-yellow' : 'badge-red');
                            @endphp
                            <span class="badge {{ $badgeClass }}">{{ number_format($pm->margin_percent, 1) }}%</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <!-- Profil Produk -->
    <div class="card">
        <div class="card-header">
            <span class="card-title">Top 50 Rank Keuntungan Produk (Hero SKUs)</span>
        </div>
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Nama Produk</th>
                        <th class="text-right">Revenue</th>
                        <th class="text-right">Laba Kotor</th>
                        <th class="text-right">Margin %</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($productMargins as $index => $prod)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>
                            <div class="font-bold text-blue" style="max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;" title="{{ $prod->product_name }}">
                                {{ $prod->product_name }}
                            </div>
                            <div style="font-size:0.75rem; color:var(--text-muted);">{{ str_replace('PT. ', '', $prod->principal_name) }}</div>
                        </td>
                        <td class="text-right font-mono text-muted">Rp {{ number_format($prod->revenue, 0, ',', '.') }}</td>
                        <td class="text-right font-mono text-green font-bold">Rp {{ number_format($prod->gross_profit, 0, ',', '.') }}</td>
                        <td class="text-right">
                            @php
                                $badgeClass = $prod->margin_percent >= 25 ? 'badge-green' : ($prod->margin_percent >= 15 ? 'badge-yellow' : 'badge-red');
                            @endphp
                            <span class="badge {{ $badgeClass }}" style="font-size:0.8rem;">{{ number_format($prod->margin_percent, 1) }}%</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
