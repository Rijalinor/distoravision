@extends('layouts.app')
@section('page-title', 'Regional Analytics')
@section('top-bar-actions')
<form method="GET" style="display:flex;gap:0.75rem;align-items:center;">
    @include('components.filter')
</form>
@endsection

@section('content')
<div class="card" style="margin-bottom:1.5rem;">
    <div class="card-header"><span class="card-title">Sales per Kota</span></div>
    <div id="cityChart"></div>
</div>

<div class="card">
    <div class="card-header"><span class="card-title">Detail per Wilayah (Prefix)</span></div>
    <table class="data-table">
        <thead><tr><th>#</th><th>Wilayah (Prefix)</th><th class="text-right">Sales</th><th class="text-right">Returns</th><th class="text-right">Return Rate</th><th class="text-right">Outlet</th><th class="text-right">Salesman</th><th class="text-right">Transaksi</th></tr></thead>
        <tbody>
        @foreach($cities as $i => $c)
            @php $rr = $c->total_sales > 0 ? ($c->total_returns / $c->total_sales * 100) : 0; @endphp
            <tr>
                <td>{{ $i + 1 }}</td>
                <td class="font-bold">{{ strtoupper($c->region_code) }}</td>
                <td class="text-right font-mono text-green">Rp {{ number_format($c->total_sales, 0, ',', '.') }}</td>
                <td class="text-right font-mono text-red">Rp {{ number_format($c->total_returns, 0, ',', '.') }}</td>
                <td class="text-right"><span class="badge {{ $rr > 10 ? 'badge-red' : 'badge-green' }}">{{ number_format($rr, 1) }}%</span></td>
                <td class="text-right font-mono">{{ number_format($c->outlet_count) }}</td>
                <td class="text-right font-mono">{{ number_format($c->salesman_count) }}</td>
                <td class="text-right font-mono">{{ number_format($c->trx_count) }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    var data = @json($cities);
    new ApexCharts(document.querySelector("#cityChart"),{
        chart:{type:'bar',height:350,toolbar:{show:false},background:'transparent'},
        series:[{name:'Sales',data:data.map(c=>parseFloat(c.total_sales))},{name:'Returns',data:data.map(c=>parseFloat(c.total_returns))}],
        xaxis:{categories:data.map(c=>c.region_code ? c.region_code.toUpperCase() : 'N/A'),labels:{style:{colors:'#94a3b8',fontSize:'10px'},rotate:-45}},
        colors:['#10b981','#ef4444'],theme:{mode:'dark'},grid:{borderColor:'#334155'},
        dataLabels:{enabled:false},plotOptions:{bar:{borderRadius:4,columnWidth:'60%'}},
        yaxis:{labels:{formatter:v=>'Rp '+(v/1000000).toFixed(0)+'M'}},
        tooltip:{y:{formatter:v=>'Rp '+new Intl.NumberFormat('id-ID').format(v)}}
    }).render();
});
</script>
@endsection
