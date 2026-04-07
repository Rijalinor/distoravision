@extends('layouts.app')
@section('page-title', 'Efektifitas Diskon')
@section('top-bar-actions')
<form method="GET" style="display:flex;gap:0.75rem;align-items:center;">
    @include('components.filter')
</form>
@endsection

@section('content')
<!-- KPIs -->
<div class="kpi-grid" style="grid-template-columns: repeat(4, 1fr);">
    <div class="card kpi-card">
        <div class="card-title" title="Total akumulasi penjualan kotor sebelum dipotong diskon apapun.">Gross Sales</div>
        <div class="kpi-value text-blue" title="Rp {{ number_format($totalGross, 0, ',', '.') }}">Rp {{ number_format($totalGross / 1000000, 0, ',', '.') }}M</div>
        <div class="kpi-label">Total Omset Kotor</div>
    </div>
    
    <div class="card kpi-card">
        <div class="card-title" title="Jumlah uang yang 'dibakar' sebagai potongan harga atau promosi kepada pelanggan.">Total Diskon</div>
        <div class="kpi-value text-yellow" title="Rp {{ number_format($totalDiscount, 0, ',', '.') }}">Rp {{ number_format($totalDiscount / 1000000, 1, ',', '.') }}M</div>
        <div class="kpi-label">Potongan Harga Diberikan</div>
    </div>

    <div class="card kpi-card" style="{{ $avgDiscountPercent > 10 ? 'border-color:var(--accent-red); box-shadow:0 0 10px rgba(239,68,68,0.2);' : '' }}">
        <div class="card-title" title="Rasio 'bakar uang' dibandingkan penjualan kotor. Semakin kecil angkanya, semakin efektif diskon yang diberikan.">Average Discount %</div>
        <div class="kpi-value {{ $avgDiscountPercent > 10 ? 'text-red' : 'text-green' }}">{{ number_format($avgDiscountPercent, 2) }}%</div>
        <div class="kpi-label">Dari Total Gross Sales</div>
    </div>

    <div class="card kpi-card">
        <div class="card-title" title="Nominal sebenarnya yang ditagihkan kepada pelanggan (Gross dipotong Diskon).">Net Sales (AR)</div>
        <div class="kpi-value text-green" title="Rp {{ number_format($totalNet, 0, ',', '.') }}">Rp {{ number_format($totalNet / 1000000, 0, ',', '.') }}M</div>
        <div class="kpi-label">Sales Bersih Tertagih</div>
    </div>
</div>

<div class="grid-2">
    <!-- Chart -->
    <div class="card">
        <div class="card-header"><span class="card-title">Distribusi Diskon per Principal</span></div>
        <div id="principalDiscountChart"></div>
        <p class="text-sm text-muted mt-1">Mengukur budget diskon mana yang memakan porsi terbesar secara nilai Rupiah.</p>
    </div>

    <!-- Top Discounted Products -->
    <div class="card">
        <div class="card-header"><span class="card-title">Produk Haus Diskon (Top 20)</span></div>
        <div style="overflow-y:auto; max-height: 400px; padding-right:0.5rem;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Gross</th>
                        <th class="text-right">Diskon</th>
                        <th class="text-right">%</th>
                    </tr>
                </thead>
                <tbody>
                @foreach($topDiscountedProducts as $product)
                    @php 
                        $isHigh = $product->discount_percent > 10;
                        $isExtreme = $product->discount_percent > 20; 
                    @endphp
                    <tr>
                        <td>
                            <div class="font-bold">{{ Str::limit($product->product_name, 25) }}</div>
                            <div style="font-size:0.65rem; color:var(--text-muted);">{{ Str::limit($product->principal_name, 20) }}</div>
                        </td>
                        <td class="text-right font-mono">{{ number_format($product->qty_sold) }}</td>
                        <td class="text-right font-mono">Rp {{ number_format($product->gross_sales / 1000000, 1) }}M</td>
                        <td class="text-right font-mono text-yellow">Rp {{ number_format($product->discount_given / 1000000, 1) }}M</td>
                        <td class="text-right">
                            <span class="badge {{ $isExtreme ? 'badge-red' : ($isHigh ? 'badge-yellow' : 'badge-blue') }}">
                                {{ number_format($product->discount_percent, 1) }}%
                            </span>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card" style="margin-top: 1.5rem;">
    <div class="card-header"><span class="card-title">Rincian Efektifitas Diskon per Principal</span></div>
    <div style="overflow-x:auto; max-height: 500px;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nama Principal</th>
                    <th class="text-right">Gross Sales</th>
                    <th class="text-right">Total Diskon</th>
                    <th class="text-right">Net Sales (AR)</th>
                    <th class="text-right">Discount Depth (%)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($principalDiscounts as $index => $principal)
                    @php 
                        $isHigh = $principal->discount_percent > 10;
                        $isExtreme = $principal->discount_percent > 20; 
                    @endphp
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td class="font-bold">{{ $principal->principal_name }}</td>
                        <td class="text-right font-mono">Rp {{ number_format($principal->gross_sales, 0, ',', '.') }}</td>
                        <td class="text-right font-mono text-yellow">Rp {{ number_format($principal->discount_given, 0, ',', '.') }}</td>
                        <td class="text-right font-mono text-green">Rp {{ number_format($principal->net_sales, 0, ',', '.') }}</td>
                        <td class="text-right">
                            <span class="badge {{ $isExtreme ? 'badge-red' : ($isHigh ? 'badge-yellow' : 'badge-blue') }}">
                                {{ number_format($principal->discount_percent, 2) }}%
                            </span>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var principalData = @json($principalDiscounts);
    
    // Sort by Discount amount
    principalData.sort((a,b) => b.discount_given - a.discount_given);
    
    // Take Top 15 Principals for clear visualization
    const chartData = principalData.slice(0, 15);

    var options = {
        chart: {
            type: 'bar',
            height: 380,
            toolbar: { show: false },
            background: 'transparent',
            stacked: false
        },
        series: [
            {
                name: 'Net Sales',
                type: 'column',
                data: chartData.map(p => parseFloat(p.net_sales))
            },
            {
                name: 'Total Diskon',
                type: 'column',
                data: chartData.map(p => parseFloat(p.discount_given))
            },
            {
                name: 'Discount Depth (%)',
                type: 'line',
                data: chartData.map(p => parseFloat(p.discount_percent))
            }
        ],
        xaxis: {
            categories: chartData.map(p => p.principal_name.substring(0, 15)),
            labels: { style: { colors: '#94a3b8', fontSize: '10px' }, rotate: -45 }
        },
        yaxis: [
            {
                title: { text: 'Sales & Diskon (Rp)', style:{color:'#94a3b8'} },
                labels: { formatter: (value) => 'Rp ' + (value / 1000000).toFixed(0) + 'M', style:{colors:'#94a3b8'} }
            },
            {
                show: false, // share the primary Y axis for Diskon
            },
            {
                opposite: true,
                title: { text: 'Discount Depth (%)', style:{color:'#a855f7'} },
                max: 100,
                labels: { formatter: (value) => value.toFixed(1) + '%', style:{colors:'#a855f7'} }
            }
        ],
        colors: ['#3b82f6', '#f59e0b', '#a855f7'],
        stroke: {
            width: [0, 0, 3],
            curve: 'smooth'
        },
        theme: { mode: 'dark' },
        grid: { borderColor: '#334155' },
        dataLabels: { enabled: false },
        plotOptions: { bar: { borderRadius: 3, columnWidth: '60%' } },
        tooltip: {
            y: {
                formatter: function (val, { seriesIndex }) {
                    if(seriesIndex === 2) return val.toFixed(2) + '%';
                    return 'Rp ' + new Intl.NumberFormat('id-ID').format(val);
                }
            }
        }
    };

    new ApexCharts(document.querySelector("#principalDiscountChart"), options).render();
});
</script>
@endsection
