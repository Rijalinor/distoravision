@extends('layouts.app')
@section('page-title', 'Dashboard Eksekutif')
@section('top-bar-actions')
<form method="GET" action="{{ route('dashboard') }}" style="display:flex;gap:0.75rem;align-items:center;">
    @include('components.filter')
</form>
@endsection

@section('content')
@if($periods->isEmpty())
    <div class="card" style="text-align:center;padding:4rem;">
        <div style="font-size:3rem;margin-bottom:1rem;">📊</div>
        <h2 style="margin-bottom:0.5rem;">Belum Ada Data</h2>
        <p style="color:var(--text-muted);margin-bottom:1.5rem;">Import data secondary sales untuk mulai melihat analytics.</p>
        <a href="{{ route('imports.create') }}" class="btn btn-primary">Import Data Sekarang</a>
    </div>
@else
    @include('components.ai-insight')

    <!-- SYSTEM ALERTS -->
    @if($criticalStockCount > 0 || $overstockCount > 0 || $overdueArAmount > 0)
    <div style="display: flex; gap: 1rem; margin-bottom: 1.5rem; flex-wrap: wrap;">
        @if($criticalStockCount > 0)
        <a href="{{ route('sales-per.stock', ['period' => $period]) }}" class="card" style="flex: 1; border-left: 4px solid var(--accent-red); background: rgba(239, 68, 68, 0.05); display: flex; align-items: center; gap: 1rem; min-width: 250px; text-decoration: none; transition: transform 0.2s;">
            <div style="padding: 0.5rem; background: rgba(239, 68, 68, 0.15); border-radius: 8px; color: var(--accent-red);">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            </div>
            <div>
                <div style="font-size: 0.75rem; color: var(--accent-red); font-weight: 700; letter-spacing: 0.05em;">STOK KRITIS</div>
                <div style="font-size: 1.1rem; font-weight: 700; color: var(--text-primary);">{{ $criticalStockCount }} SKU Hampir Habis</div>
            </div>
        </a>
        @endif
        
        @if($overstockCount > 0)
        <a href="{{ route('sales-per.stock', ['period' => $period]) }}" class="card" style="flex: 1; border-left: 4px solid var(--accent-yellow); background: rgba(245, 158, 11, 0.05); display: flex; align-items: center; gap: 1rem; min-width: 250px; text-decoration: none; transition: transform 0.2s;">
            <div style="padding: 0.5rem; background: rgba(245, 158, 11, 0.15); border-radius: 8px; color: var(--accent-yellow);">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
            </div>
            <div>
                <div style="font-size: 0.75rem; color: var(--accent-yellow); font-weight: 700; letter-spacing: 0.05em;">OVERSTOCK</div>
                <div style="font-size: 1.1rem; font-weight: 700; color: var(--text-primary);">{{ $overstockCount }} SKU Macet</div>
            </div>
        </a>
        @endif
        
        @if($overdueArAmount > 0)
        <a href="{{ route('ar.dashboard') }}" class="card" style="flex: 1; border-left: 4px solid var(--accent-red); background: rgba(239, 68, 68, 0.05); display: flex; align-items: center; gap: 1rem; min-width: 250px; text-decoration: none; transition: transform 0.2s;">
            <div style="padding: 0.5rem; background: rgba(239, 68, 68, 0.15); border-radius: 8px; color: var(--accent-red);">
                <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <div>
                <div style="font-size: 0.75rem; color: var(--accent-red); font-weight: 700; letter-spacing: 0.05em;">PIUTANG JATUH TEMPO</div>
                <div style="font-size: 1.1rem; font-weight: 700; color: var(--text-primary);">Rp {{ number_format($overdueArAmount / 1000000, 1, ',', '.') }} Juta</div>
            </div>
        </a>
        @endif
    </div>
    @endif

    <!-- TARGET TRACKER GLOBAL -->
    <div class="card" style="margin-bottom: 1.5rem; padding: 1.5rem; background: linear-gradient(to right, rgba(99, 102, 241, 0.05), rgba(30, 41, 59, 0));">
        <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 0.75rem;">
            <div>
                <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 700; letter-spacing: 0.05em; text-transform: uppercase;">🎯 Target Penjualan Global ({{ \Carbon\Carbon::parse($period.'-01')->translatedFormat('F Y') }})</div>
                <div style="font-size: 1.75rem; font-weight: 800; font-family: monospace; color: var(--text-primary); margin-top: 0.25rem;">
                    Rp {{ number_format($totalSales, 0, ',', '.') }} <span style="font-size: 1.1rem; color: var(--text-muted); font-weight: 500;">/ Rp {{ number_format($globalTarget, 0, ',', '.') }}</span>
                </div>
            </div>
            <div style="text-align: right;">
                <div style="font-size: 1.5rem; font-weight: 800; color: {{ $globalProgress >= 100 ? 'var(--accent-green)' : 'var(--accent-blue)' }};">{{ number_format($globalProgress, 1) }}%</div>
            </div>
        </div>
        <div style="width: 100%; background: rgba(0,0,0,0.2); border-radius: 10px; height: 16px; overflow: hidden; border: 1px solid var(--border-color);">
            <div style="height: 100%; background: {{ $globalProgress >= 100 ? 'var(--accent-green)' : 'linear-gradient(90deg, var(--accent-blue), #818cf8)' }}; width: {{ min(100, $globalProgress) }}%; border-radius: 10px; transition: width 1s ease; position: relative; overflow: hidden;">
            </div>
        </div>
    </div>

    <!-- KPI Cards -->
    <div class="kpi-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
        <div class="card kpi-card" style="border-top: 3px solid var(--accent-blue);">
            <div class="card-header">
                <span class="card-title" title="Omset khusus transaksi hari ini (berdasarkan tanggal dokumen)">Omset Hari Ini ({{ \Carbon\Carbon::parse($displayDate)->format('d/m') }})</span>
                <div class="kpi-icon blue"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></div>
            </div>
            <div class="kpi-value" style="color: var(--accent-blue);" title="Rp {{ number_format($todaySales, 0, ',', '.') }}">Rp {{ number_format($todaySales / 1000, 0, ',', '.') }}K</div>
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div class="kpi-label">Faktur tgl dokumen {{ \Carbon\Carbon::parse($displayDate)->format('d M') }}</div>
            </div>
        </div>
        <div class="card kpi-card">
            <div class="card-header">
                <span class="card-title" title="Total Nilai Transaksi Kotor (Belum potong diskon/retur)">Total Sales</span>
                <div class="kpi-icon green"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg></div>
            </div>
            <div class="kpi-value" title="Rp {{ number_format($totalSales, 0, ',', '.') }}">Rp {{ number_format($totalSales / 1000, 0, ',', '.') }}K</div>
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div class="kpi-label">{{ number_format($invoiceCount) }} transaksi</div>
                @if(isset($momSales) && $momSales !== 0)
                <div class="badge {{ $momSales > 0 ? 'badge-green' : 'badge-red' }}" style="font-size:0.6rem;">{!! $momSales > 0 ? '↑' : '↓' !!} {{ number_format(abs($momSales), 1) }}%</div>
                @endif
            </div>
        </div>
        <div class="card kpi-card">
            <div class="card-header">
                <span class="card-title" title="Total Nilai Barang Retur BAST (Batal, Rusak, atau Pengembalian)">Total Returns</span>
                <div class="kpi-icon red"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path></svg></div>
            </div>
            <div class="kpi-value text-red" style="-webkit-text-fill-color:var(--accent-red);" title="Rp {{ number_format($totalReturns, 0, ',', '.') }}">Rp {{ number_format($totalReturns / 1000, 0, ',', '.') }}K</div>
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div class="kpi-label">{{ number_format($returnCount) }} return</div>
                @if(isset($momReturns) && $momReturns !== 0)
                <div class="badge {{ $momReturns < 0 ? 'badge-green' : 'badge-red' }}" style="font-size:0.6rem;" title="MoM Growth">{!! $momReturns > 0 ? '↑' : '↓' !!} {{ number_format(abs($momReturns), 1) }}%</div>
                @endif
            </div>
        </div>
        <div class="card kpi-card">
            <div class="card-header">
                <span class="card-title" title="Sales Bersih Aktual dengan rumus: Taxed Amount - Return.">Net Sales</span>
                <div class="kpi-icon blue"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></div>
            </div>
            <div class="kpi-value" title="Rp {{ number_format($netSales, 0, ',', '.') }}">Rp {{ number_format($netSales / 1000, 0, ',', '.') }}K</div>
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div class="kpi-label">Setelah return</div>
                @if(isset($momNetSales) && $momNetSales !== 0)
                <div class="badge {{ $momNetSales > 0 ? 'badge-green' : 'badge-red' }}" style="font-size:0.6rem;">{!! $momNetSales > 0 ? '↑' : '↓' !!} {{ number_format(abs($momNetSales), 1) }}%</div>
                @endif
            </div>
        </div>
        <div class="card kpi-card">
            <div class="card-header">
                <span class="card-title">Return Rate</span>
                <div class="kpi-icon yellow"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg></div>
            </div>
            <div class="kpi-value" style="-webkit-text-fill-color:{{ $returnRate > 10 ? 'var(--accent-red)' : 'var(--accent-green)' }};">{{ number_format($returnRate, 1) }}%</div>
            <div class="kpi-label">Margin: {{ number_format($margin, 1) }}%</div>
        </div>
    </div>

    <!-- Charts -->
    <div class="chart-grid">
        <div class="card">
            <div class="card-header"><span class="card-title">Trend Mingguan</span></div>
            <div id="weeklyChart"></div>
        </div>
        <div class="card">
            <div class="card-header"><span class="card-title">Revenue per Principal</span></div>
            <div id="principalChart"></div>
        </div>
    </div>

    <!-- Tables -->
    <div class="grid-2">
        <div class="card">
            <div class="card-header"><span class="card-title">Top 10 Produk</span></div>
            <table class="data-table">
                <thead><tr><th>#</th><th>Produk</th><th class="text-right">Sales</th></tr></thead>
                <tbody>
                @foreach($topProducts as $i => $product)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ Str::limit($product->name, 35) }}</td>
                        <td class="text-right font-mono">Rp {{ number_format($product->total_sales, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <div class="card">
            <div class="card-header"><span class="card-title">Top 10 Outlet</span></div>
            <table class="data-table">
                <thead><tr><th>#</th><th>Outlet</th><th>Kota</th><th class="text-right">Sales</th></tr></thead>
                <tbody>
                @foreach($topOutlets as $i => $outlet)
                    <tr>
                        <td>{{ $i + 1 }}</td>
                        <td>{{ Str::limit($outlet->name, 25) }}</td>
                        <td><span class="badge badge-blue">{{ $outlet->city }}</span></td>
                        <td class="text-right font-mono">Rp {{ number_format($outlet->total_sales, 0, ',', '.') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

<script>
document.addEventListener('DOMContentLoaded', function() {
    @if($periods->isNotEmpty())
    // Weekly Trend Chart
    var weeklyData = @json($weeklyTrend);
    var weeks = Object.keys(weeklyData).sort();
    var salesData = weeks.map(w => {
        var found = weeklyData[w].find(d => d.type === 'I');
        return found ? parseFloat(found.total) : 0;
    });
    var returnData = weeks.map(w => {
        var found = weeklyData[w].find(d => d.type === 'R');
        return found ? parseFloat(found.total) : 0;
    });

    new ApexCharts(document.querySelector("#weeklyChart"), {
        chart: { type: 'area', height: 300, toolbar: { show: false }, background: 'transparent' },
        series: [
            { name: 'Sales', data: salesData },
            { name: 'Returns', data: returnData }
        ],
        xaxis: { categories: weeks.map(w => 'Week ' + w) },
        colors: ['#8991c2', '#ef4444'],
        fill: { type: 'gradient', gradient: { opacityFrom: 0.4, opacityTo: 0 } },
        stroke: { curve: 'smooth', width: 2 },
        theme: { mode: 'dark' },
        grid: { borderColor: '#243156' },
        dataLabels: { enabled: false },
        yaxis: { labels: { formatter: v => 'Rp ' + (v/1000).toFixed(0) + 'K' } },
        tooltip: { y: { formatter: v => 'Rp ' + new Intl.NumberFormat('id-ID').format(v) } }
    }).render();

    // Principal Chart
    var principalData = @json($principalBreakdown);
    new ApexCharts(document.querySelector("#principalChart"), {
        chart: { type: 'donut', height: 300, background: 'transparent' },
        series: principalData.map(p => parseFloat(p.total_sales)),
        labels: principalData.map(p => p.name.replace('PT. ', '').substring(0, 20)),
        colors: ['#8991c2', '#10b981', '#f59e0b', '#ef4444', '#3b82f6', '#8b5cf6', '#ec4899', '#14b8a6', '#f97316', '#06b6d4', '#84cc16'],
        theme: { mode: 'dark' },
        legend: { position: 'bottom', labels: { colors: '#94a3b8' } },
        dataLabels: { enabled: false },
        plotOptions: { pie: { donut: { size: '60%' } } },
        tooltip: { y: { formatter: v => 'Rp ' + new Intl.NumberFormat('id-ID').format(v) } }
    }).render();
    @endif
});
</script>
@endsection
