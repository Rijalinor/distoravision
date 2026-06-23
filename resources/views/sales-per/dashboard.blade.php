@extends('layouts.app')
@section('page-title', 'Sales Per — Monitoring Omset')

@section('content')
@if(!$hasData)
<div class="card" style="text-align:center;padding:4rem 2rem;">
    <div style="font-size:3rem;margin-bottom:1rem;">📊</div>
    <h2 style="font-size:1.2rem;margin-bottom:0.5rem;">Belum Ada Data Sales Per</h2>
    <p style="color:var(--text-muted);margin-bottom:1.5rem;">Upload file Excel "Sales Per" terlebih dahulu untuk mulai monitoring omset salesman.</p>
    <a href="{{ route('sales-per.imports.create') }}" class="btn btn-primary">Upload File Sales Per</a>
</div>
@else

{{-- FILTER BAR --}}
<div class="card" style="margin-bottom:1.5rem;padding:0.75rem 1.25rem;">
    <form method="GET" style="display:flex;gap:0.75rem;align-items:center;flex-wrap:wrap;">
        <select name="period" class="period-select" onchange="this.form.submit()">
            @foreach($periods as $p)
                <option value="{{ $p }}" {{ $p === $period ? 'selected' : '' }}>{{ \Carbon\Carbon::parse($p.'-01')->translatedFormat('F Y') }}</option>
            @endforeach
        </select>
        <select name="salesman" class="period-select" onchange="this.form.submit()">
            <option value="">— Semua Salesman —</option>
            @foreach($salesmenList as $sm)
                <option value="{{ $sm->sales_code }}" {{ $selectedSalesCode === $sm->sales_code ? 'selected' : '' }}>{{ $sm->sales_name }} ({{ $sm->sales_code }})</option>
            @endforeach
        </select>
        <select name="principal" class="period-select" style="max-width:220px;" onchange="this.form.submit()">
            <option value="all">Semua Principal</option>
            @foreach($principalList as $pr)
                <option value="{{ $pr }}" {{ $selectedPrincipal === $pr ? 'selected' : '' }}>{{ Str::limit(str_replace('PT. ', '', $pr), 25) }}</option>
            @endforeach
        </select>
    </form>
</div>

{{-- HEADER --}}
<div class="card" style="margin-bottom:1.5rem;background:linear-gradient(135deg,rgba(99,102,241,0.15),rgba(16,185,129,0.1));border:1px solid rgba(99,102,241,0.3);">
    <div style="display:flex;align-items:center;gap:1rem;">
        <div style="width:48px;height:48px;min-width:48px;border-radius:12px;background:linear-gradient(135deg,var(--primary),#10b981);display:flex;align-items:center;justify-content:center;font-size:22px;">📈</div>
        <div>
            <h2 style="font-size:1.15rem;font-weight:700;margin:0;">Monitoring Omset — Sales Per {{ $periodLabel }}</h2>
            <p style="color:var(--text-muted);font-size:0.8rem;margin:0;">
                Data penjualan harian (belum approve)
                @if($selectedSalesName) · Fokus: <span class="text-green font-bold">{{ $selectedSalesName }}</span>@endif
                @if($selectedPrincipal && $selectedPrincipal !== 'all') · Principal: <span class="text-blue font-bold">{{ Str::limit(str_replace('PT. ', '', $selectedPrincipal), 30) }}</span>@endif
            </p>
        </div>
    </div>
</div>

{{-- KPI CARDS --}}
<div class="kpi-grid">
    <div class="card kpi-card"><div class="card-header"><span class="card-title">Total Omset</span><div class="kpi-icon green"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg></div></div><div class="kpi-value" title="Rp {{ number_format($overallSales, 0, ',', '.') }}">Rp {{ number_format($overallSales / 1000000, 1, ',', '.') }}Jt</div><div class="kpi-label">Net: Rp {{ number_format($overallNetSales / 1000000, 1, ',', '.') }}Jt</div></div>
    <div class="card kpi-card"><div class="card-header"><span class="card-title">Total Retur</span><div class="kpi-icon red"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path></svg></div></div><div class="kpi-value" style="-webkit-text-fill-color:var(--accent-red);" title="Rp {{ number_format($overallReturns, 0, ',', '.') }}">Rp {{ number_format($overallReturns / 1000000, 1, ',', '.') }}Jt</div><div class="kpi-label">Rate: <span class="{{ $overallReturnRate > 5 ? 'text-red' : 'text-green' }}">{{ number_format($overallReturnRate, 1) }}%</span></div></div>
    <div class="card kpi-card"><div class="card-header"><span class="card-title">Total Nota</span><div class="kpi-icon blue"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"></path></svg></div></div><div class="kpi-value">{{ number_format($overallNotaCount) }}</div><div class="kpi-label">{{ $overallOutletCount }} outlet · {{ $activeSalesmenCount }} salesman</div></div>
</div>

{{-- SALESMAN DETAIL --}}
@if($salesmanDetail)
<div class="card" style="margin-bottom:1.5rem;border-top:4px solid var(--primary);">
    <div class="card-header"><span class="card-title">📋 Detail: {{ $selectedSalesName }} ({{ $selectedSalesCode }})</span><span class="badge badge-blue">Rank #{{ $salesmanDetail['rank'] }} · Kontribusi {{ number_format($salesmanDetail['contribution'], 1) }}%</span></div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:0.75rem;margin-bottom:1.25rem;">
        <div style="text-align:center;padding:0.75rem;background:var(--bg-darker);border-radius:8px;"><div style="font-size:0.7rem;color:var(--text-muted);">Omset</div><div class="font-mono font-bold" style="font-size:1.1rem;" title="Rp {{ number_format($salesmanDetail['sales'], 0, ',', '.') }}">Rp {{ number_format($salesmanDetail['sales'] / 1000000, 1, ',', '.') }}Jt</div></div>
        <div style="text-align:center;padding:0.75rem;background:var(--bg-darker);border-radius:8px;"><div style="font-size:0.7rem;color:var(--text-muted);">Retur</div><div class="font-mono font-bold text-red" style="font-size:1.1rem;">Rp {{ number_format($salesmanDetail['returns'] / 1000, 0, ',', '.') }}K</div></div>
        <div style="text-align:center;padding:0.75rem;background:var(--bg-darker);border-radius:8px;"><div style="font-size:0.7rem;color:var(--text-muted);">Nota</div><div class="font-mono font-bold" style="font-size:1.1rem;">{{ $salesmanDetail['nota_count'] }}</div></div>
        <div style="text-align:center;padding:0.75rem;background:var(--bg-darker);border-radius:8px;"><div style="font-size:0.7rem;color:var(--text-muted);">Outlet</div><div class="font-mono font-bold" style="font-size:1.1rem;">{{ $salesmanDetail['outlet_count'] }}</div></div>
        <div style="text-align:center;padding:0.75rem;background:var(--bg-darker);border-radius:8px;"><div style="font-size:0.7rem;color:var(--text-muted);">Return Rate</div><div class="font-mono font-bold" style="font-size:1.1rem;color:{{ $salesmanDetail['return_rate'] > 5 ? 'var(--accent-red)' : 'var(--accent-green)' }};">{{ number_format($salesmanDetail['return_rate'], 1) }}%</div></div>
    </div>

    <div class="grid-2">
        <div><div style="font-size:0.75rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:0.5rem;">🏆 Top 10 Produk</div>
        <table class="data-table"><thead><tr><th>#</th><th>Produk</th><th class="text-right">Qty</th><th class="text-right">Sales</th></tr></thead><tbody>
        @foreach($salesmanDetail['top_products'] as $i => $p)<tr><td>{{ $i+1 }}</td><td>{{ Str::limit($p->item_name, 28) }}</td><td class="text-right">{{ number_format($p->total_qty) }}</td><td class="text-right font-mono">{{ number_format($p->total_sales / 1000, 0, ',', '.') }}K</td></tr>@endforeach
        </tbody></table></div>
        <div><div style="font-size:0.75rem;font-weight:600;color:var(--text-muted);text-transform:uppercase;margin-bottom:0.5rem;">🏪 Top 10 Outlet</div>
        <table class="data-table"><thead><tr><th>#</th><th>Outlet</th><th class="text-right">Nota</th><th class="text-right">Sales</th></tr></thead><tbody>
        @foreach($salesmanDetail['top_outlets'] as $i => $o)<tr><td>{{ $i+1 }}</td><td>{{ Str::limit($o->outlet_name, 22) }}</td><td class="text-right">{{ $o->nota_count }}</td><td class="text-right font-mono">{{ number_format($o->total_sales / 1000, 0, ',', '.') }}K</td></tr>@endforeach
        </tbody></table></div>
    </div>
</div>
@endif

{{-- DAILY TREND --}}
<div class="card" style="margin-bottom:1.5rem;border-top:4px solid var(--accent-green);">
    <div class="card-header"><span class="card-title">📈 Tren Omset Harian — {{ $selectedSalesName ?? 'Semua Salesman' }}</span></div>
    <div id="dailyTrendChart" style="height:300px;"></div>
</div>

{{-- LEADERBOARD --}}
<div class="card" style="border-top:4px solid var(--primary);">
    <div class="card-header"><span class="card-title">👥 Performa Salesman — {{ $periodLabel }}</span><span class="badge badge-blue">{{ $leaderboard->count() }} salesman</span></div>
    <table class="data-table">
        <thead><tr><th style="width:40px;">No</th><th>Salesman</th><th class="text-right">Omset</th><th class="text-right">Retur</th><th class="text-right">Net Sales</th><th class="text-right">Nota</th><th class="text-right">Outlet</th><th class="text-right">Ret%</th><th style="width:40px;"></th></tr></thead>
        <tbody>
        @foreach($leaderboard as $i => $s)
        <tr style="{{ $selectedSalesCode === $s->sales_code ? 'background:rgba(99,102,241,0.1);border-left:3px solid var(--primary);' : '' }}">
            <td><span style="color:var(--text-muted);">{{ $i+1 }}</span></td>
            <td><div style="font-weight:600;">{{ $s->sales_name }}</div><div style="font-size:0.65rem;color:var(--text-muted);">{{ $s->sales_code }}</div></td>
            <td class="text-right font-mono font-bold" title="Rp {{ number_format($s->total_sales, 0, ',', '.') }}">{{ number_format($s->total_sales / 1000000, 1, ',', '.') }}Jt</td>
            <td class="text-right font-mono text-red">{{ number_format($s->total_returns / 1000, 0, ',', '.') }}K</td>
            <td class="text-right font-mono">{{ number_format($s->net_sales / 1000000, 1, ',', '.') }}Jt</td>
            <td class="text-right">{{ $s->nota_count }}</td>
            <td class="text-right">{{ $s->outlet_count }}</td>
            <td class="text-right"><span class="badge {{ $s->return_rate > 5 ? 'badge-red' : ($s->return_rate > 2 ? 'badge-yellow' : 'badge-green') }}">{{ number_format($s->return_rate, 1) }}%</span></td>
            <td><a href="?period={{ $period }}&salesman={{ $s->sales_code }}&principal={{ $selectedPrincipal ?? 'all' }}" title="Detail {{ $s->sales_name }}" style="color:var(--primary-light);text-decoration:none;">🔍</a></td>
        </tr>
        @endforeach
        </tbody>
    </table>
</div>

{{-- CHART --}}
<script>
document.addEventListener('DOMContentLoaded', function(){
    var rawData = @json($dailyTrend);
    var dates = Object.keys(rawData).sort();
    var salesArr = [], retArr = [];
    dates.forEach(function(d){
        var s=0, r=0;
        rawData[d].forEach(function(item){
            if(item.type==='I') s=parseFloat(item.total);
            else r=parseFloat(item.total);
        });
        salesArr.push(s); retArr.push(r);
    });
    if(typeof ApexCharts!=='undefined' && dates.length>0){
        new ApexCharts(document.querySelector("#dailyTrendChart"),{
            chart:{type:'area',height:300,toolbar:{show:false},background:'transparent',foreColor:'#94a3b8',zoom:{enabled:true}},
            series:[{name:'Sales',data:salesArr},{name:'Returns',data:retArr}],
            xaxis:{categories:dates.map(d=>d.substring(5)),labels:{rotate:-45,style:{fontSize:'10px'}}},
            colors:['#10b981','#ef4444'],
            stroke:{curve:'smooth',width:2},
            fill:{type:'gradient',gradient:{opacityFrom:0.4,opacityTo:0.05}},
            dataLabels:{enabled:false},
            grid:{borderColor:'#334155',strokeDashArray:3},
            yaxis:{labels:{formatter:v=>'Rp '+(v/1000000).toFixed(1)+'Jt'}},
            tooltip:{theme:'dark',y:{formatter:v=>'Rp '+new Intl.NumberFormat('id-ID').format(v)}},
            legend:{position:'top',horizontalAlign:'right'}
        }).render();
    }
});
</script>
@endif
@endsection
