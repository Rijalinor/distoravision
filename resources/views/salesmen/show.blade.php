@extends('layouts.app')
@section('page-title', $salesman->name)
@section('top-bar-actions')
<form method="GET" style="display:flex;gap:0.75rem;align-items:center;">
    @include('components.filter')
</form>
@endsection

@section('content')
<div class="kpi-grid">
    <div class="card kpi-card"><div class="card-header"><span class="card-title">Total Sales</span><div class="kpi-icon green"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8V7m0 1v8m0 0v1"></path></svg></div></div><div class="kpi-value" title="Rp {{ number_format($stats['total_sales'], 0, ',', '.') }}">Rp {{ number_format($stats['total_sales']/1000, 0, ',', '.') }}K</div></div>
    <div class="card kpi-card"><div class="card-header"><span class="card-title">Returns</span><div class="kpi-icon red"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path></svg></div></div><div class="kpi-value" style="-webkit-text-fill-color:var(--accent-red);" title="Rp {{ number_format($stats['total_returns'], 0, ',', '.') }}">Rp {{ number_format($stats['total_returns']/1000, 0, ',', '.') }}K</div></div>
    <div class="card kpi-card"><div class="card-header"><span class="card-title">Outlet Coverage</span><div class="kpi-icon blue"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16"></path></svg></div></div><div class="kpi-value">{{ $stats['outlet_count'] }}</div><div class="kpi-label">outlet aktif</div></div>
    <div class="card kpi-card"><div class="card-header"><span class="card-title">Transaksi</span><div class="kpi-icon yellow"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"></path></svg></div></div><div class="kpi-value">{{ number_format($stats['trx_count']) }}</div><div class="kpi-label">Avg: Rp {{ $stats['trx_count'] > 0 ? number_format($stats['total_sales']/$stats['trx_count'], 0, ',', '.') : 0 }}</div></div>
</div>

<div class="grid-2" style="margin-bottom: 1.5rem;">
    <!-- Personal Target Tracker -->
    <div class="card" style="border-top: 4px solid var(--accent-blue);">
        <div class="card-header"><span class="card-title">Personal Target (S1 Intelligence)</span></div>
        <div style="padding: 1.5rem;">
            <div style="display:flex; justify-content:space-between; margin-bottom: 0.5rem;">
                <span class="text-sm text-muted">Progres Target Bulan Ini</span>
                <span class="text-sm font-bold {{ $targetProgress >= 100 ? 'text-green' : 'text-blue' }}">{{ number_format($targetProgress, 1) }}%</span>
            </div>
            <div style="width:100%; background:var(--bg-darker); border-radius:999px; height:8px; margin-bottom: 1rem; overflow:hidden;">
                <div style="width:{{ min($targetProgress, 100) }}%; background:var(--primary); height:100%; border-radius:999px; transition:width 1s;"></div>
            </div>
            <div style="display: flex; gap: 1rem; font-size: 0.85rem;">
                <div style="flex:1;"><div class="text-muted">Asumsi Target (3Bln)</div><div class="font-bold">Rp {{ number_format($personalTarget, 0, ',', '.') }}</div></div>
                <div style="flex:1;"><div class="text-muted">Shortfall</div><div class="text-red font-bold">Rp {{ number_format($shortfall, 0, ',', '.') }}</div></div>
                <div style="flex:1;"><div class="text-muted">Req. Run Rate / Hari</div><div class="text-yellow font-bold">Rp {{ number_format($dailyRunRateRequired, 0, ',', '.') }}</div></div>
            </div>
        </div>
    </div>

    <!-- Personal Churn & Performance Focus -->
    <div class="card" style="border-top: 4px solid var(--accent-red);">
        <div class="card-header"><span class="card-title">Resiko Toko Churn & Retur</span></div>
        <div style="padding: 1.5rem; display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
            <div style="text-align:center; padding: 1rem; background:rgba(239,68,68,0.1); border-radius:8px;">
                <div style="font-size: 2rem; font-weight:bold; color:var(--accent-red); line-height:1;">{{ $lostOutletsCount }}</div>
                <div class="text-sm text-muted mt-1">Toko Hilang / Churn</div>
                <div class="text-xs text-red" style="margin-top:0.5rem;" title="Opportunity Loss">Loss: Rp {{ number_format($lostOutletsValue,0,',','.') }}</div>
            </div>
            <div style="text-align:center; padding: 1rem; background:rgba(245,158,11,0.1); border-radius:8px;">
                <div style="font-size: 2rem; font-weight:bold; color:var(--accent-yellow); line-height:1;">{{ number_format($returnRate,1) }}%</div>
                <div class="text-sm text-muted mt-1">Personal Return Rate</div>
                <div class="text-xs text-yellow" style="margin-top:0.5rem;">Batas standar: 2%</div>
            </div>
        </div>
    </div>
</div>

<div class="chart-grid">
    <div class="card"><div class="card-header"><span class="card-title">Trend Mingguan</span></div><div id="weeklyChart"></div></div>
</div>

<div class="grid-2">
    <div class="card"><div class="card-header"><span class="card-title">Top Produk</span></div><table class="data-table"><thead><tr><th>#</th><th>Produk</th><th class="text-right">Qty</th><th class="text-right">Sales</th></tr></thead><tbody>@foreach($topProducts as $i=>$p)<tr><td>{{$i+1}}</td><td>{{Str::limit($p->name,30)}}</td><td class="text-right font-mono">{{number_format($p->qty)}}</td><td class="text-right font-mono">Rp {{number_format($p->total,0,',','.')}}</td></tr>@endforeach</tbody></table></div>
    <div class="card"><div class="card-header"><span class="card-title">Top Outlet</span></div><table class="data-table"><thead><tr><th>#</th><th>Outlet</th><th>Kota</th><th class="text-right">Sales</th></tr></thead><tbody>@foreach($topOutlets as $i=>$o)<tr><td>{{$i+1}}</td><td>{{Str::limit($o->name,25)}}</td><td><span class="badge badge-blue">{{$o->city}}</span></td><td class="text-right font-mono">Rp {{number_format($o->total,0,',','.')}}</td></tr>@endforeach</tbody></table></div>
</div>

<div style="margin-top:1.5rem;"><a href="{{ route('salesmen.index', ['period'=>$period]) }}" class="btn btn-secondary">← Kembali ke Ranking</a></div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    var wd=@json($weeklyData); var weeks=Object.keys(wd).sort();
    var sales=weeks.map(w=>{var f=wd[w].find(d=>d.type==='I');return f?parseFloat(f.total):0;});
    var ret=weeks.map(w=>{var f=wd[w].find(d=>d.type==='R');return f?parseFloat(f.total):0;});
    new ApexCharts(document.querySelector("#weeklyChart"),{chart:{type:'bar',height:280,toolbar:{show:false},background:'transparent'},series:[{name:'Sales',data:sales},{name:'Returns',data:ret}],xaxis:{categories:weeks.map(w=>'W'+w)},colors:['#6366f1','#ef4444'],theme:{mode:'dark'},grid:{borderColor:'#334155'},dataLabels:{enabled:false},plotOptions:{bar:{borderRadius:4}},yaxis:{labels:{formatter:v=>'Rp '+(v/1000).toFixed(0)+'K'}},tooltip:{y:{formatter:v=>'Rp '+new Intl.NumberFormat('id-ID').format(v)}}}).render();
});
</script>
@endsection
