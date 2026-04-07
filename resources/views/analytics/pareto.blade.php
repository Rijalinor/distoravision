@extends('layouts.app')
@section('page-title', 'Analisa Pareto (80/20)')
@section('top-bar-actions')
<form method="GET" style="display:flex;gap:0.75rem;align-items:center;">
    <select name="type" class="period-select" onchange="this.form.submit()" style="max-width:150px;background:var(--bg-dark);">
        <option value="product" {{ isset($type) && $type === 'product' ? 'selected' : '' }}>Pareto Produk</option>
        <option value="outlet" {{ isset($type) && $type === 'outlet' ? 'selected' : '' }}>Pareto Outlet</option>
    </select>
    @include('components.filter')
</form>
@endsection

@section('content')
<div class="kpi-grid" style="grid-template-columns: repeat(4, 1fr);">
    <div class="card kpi-card">
        <div class="card-title" title="Total omset dari semua item/outlet yang masuk dalam analisa (Kelas A, B, C).">Total Rev. Paretonisasi</div>
        <div class="kpi-value" title="Rp {{ number_format($totalRevenue, 0, ',', '.') }}">Rp {{ number_format($totalRevenue/1000000, 1, ',', '.') }}M</div>
    </div>
    <div class="card kpi-card" style="border-top: 3px solid var(--accent-green);">
        <div class="card-title" title="Mewakili 80% pertama dari total omset perusahaan. Inilah kontributor utama bisnis Anda (Prioritas VVIP).">Kelas A (Top 80%)</div>
        <div class="kpi-value text-green">{{ count($classA) }}</div>
        <div class="kpi-label">Item / Outlet</div>
    </div>
    <div class="card kpi-card" style="border-top: 3px solid var(--accent-yellow);">
        <div class="card-title" title="Mewakili 15% omset tambahan berikutnya. Penting untuk dijaga kestabilannya.">Kelas B (80% - 95%)</div>
        <div class="kpi-value text-yellow">{{ count($classB) }}</div>
        <div class="kpi-label">Item / Outlet</div>
    </div>
    <div class="card kpi-card" style="border-top: 3px solid var(--accent-red);">
        <div class="card-title" title="Mewakili 5% omset terendah perusahaan. Seringkali membebani biaya logistik atau operasional.">Kelas C (Atasan 95%)</div>
        <div class="kpi-value text-red">{{ count($classC) }}</div>
        <div class="kpi-label">Item / Outlet</div>
    </div>
</div>

<div class="card" style="margin-bottom:1.5rem;">
    <div class="card-header"><span class="card-title">Kurva Pareto (Top 50)</span></div>
    <div id="paretoChart"></div>
</div>

<div class="grid-2">
    <div class="card" style="grid-column: span 2;">
        <div class="card-header"><span class="card-title">Detail Kontributor</span></div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nama</th>
                    <th class="text-right">Total Sales</th>
                    <th class="text-right">% Kontribusi</th>
                    <th class="text-right">% Kumulatif</th>
                    <th>Kelas</th>
                </tr>
            </thead>
            <tbody>
            @foreach(array_slice($paretoData, 0, 100) as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td class="font-bold">{{ Str::limit($item['name'], 40) }}</td>
                    <td class="text-right font-mono text-green">Rp {{ number_format($item['sales'], 0, ',', '.') }}</td>
                    <td class="text-right font-mono">{{ number_format($item['percent'], 2) }}%</td>
                    <td class="text-right font-mono">{{ number_format($item['cumulative'], 2) }}%</td>
                    <td>
                        @if($item['cumulative'] <= 80)
                            <span class="badge badge-green">A</span>
                        @elseif($item['cumulative'] <= 95)
                            <span class="badge badge-yellow">B</span>
                        @else
                            <span class="badge badge-red">C</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        @if(count($paretoData) > 100)
            <div style="font-size:0.75rem; text-align:center; padding-top:1rem; color:var(--text-muted);">Menampilkan 100 data teratas dari total {{ count($paretoData) }} data.</div>
        @endif
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    var chartData = @json($chartData);
    new ApexCharts(document.querySelector("#paretoChart"), {
        series: [{
            name: 'Sales (Rp)',
            type: 'column',
            data: chartData.map(d => d.sales)
        }, {
            name: 'Cumulative %',
            type: 'line',
            data: chartData.map(d => parseFloat(d.cumulative.toFixed(2)))
        }],
        chart: { height: 350, type: 'line', toolbar: { show: false }, background: 'transparent' },
        stroke: { width: [0, 4] },
        colors: ['#6366f1', '#10b981'],
        xaxis: { categories: chartData.map(d => d.name.substring(0,15)), labels: { style: { colors: '#94a3b8', fontSize: '9px' }, rotate: -45 } },
        yaxis: [{
            title: { text: 'Sales', style: { color: '#6366f1' } },
            labels: { style: { colors: '#94a3b8' }, formatter: v => (v/1000000).toFixed(0)+'M' }
        }, {
            opposite: true,
            title: { text: 'Cumulative %', style: { color: '#10b981' } },
            labels: { style: { colors: '#94a3b8' }, formatter: v => v.toFixed(0)+'%' },
            min: 0, max: 100
        }],
        annotations: {
            yAxis: [{ y: 80, yAxisIndex: 1, borderColor: '#ef4444', label: { text: '80% Mark', style: { color: '#fff', background: '#ef4444' } } }]
        },
        theme: { mode: 'dark' }, grid: { borderColor: '#334155' }
    }).render();
});
</script>
@endsection
