@extends('layouts.app')
@section('page-title', 'Principal Intelligence')
@section('top-bar-actions')
<form method="GET" style="display:flex;gap:0.75rem;align-items:center;">
    @include('components.filter')
</form>
@endsection

@section('content')
<div class="card" style="margin-bottom:1.5rem;">
    <div class="card-header"><span class="card-title">Perbandingan Principal</span></div>
    <div id="principalBar"></div>
</div>

<div class="card">
    <div class="card-header"><span class="card-title">Detail per Principal</span></div>
    <table class="data-table">
        <thead><tr><th>#</th><th>Principal</th><th class="text-right">Sales</th><th class="text-right">Returns</th><th class="text-right">Return Rate</th><th class="text-right">Outlet Reach</th><th>Aksi</th></tr></thead>
        <tbody>
        @foreach($principals as $i => $p)
            @php $rr = $p->total_sales > 0 ? ($p->total_returns / $p->total_sales * 100) : 0; @endphp
            <tr>
                <td>{{ $i + 1 }}</td>
                <td class="font-bold">{{ str_replace('PT. ', '', $p->name) }}</td>
                <td class="text-right font-mono text-green">Rp {{ number_format($p->total_sales, 0, ',', '.') }}</td>
                <td class="text-right font-mono text-red">Rp {{ number_format($p->total_returns, 0, ',', '.') }}</td>
                <td class="text-right"><span class="badge {{ $rr > 10 ? 'badge-red' : 'badge-green' }}">{{ number_format($rr, 1) }}%</span></td>
                <td class="text-right font-mono">{{ number_format($p->outlet_reach) }}</td>
                <td><a href="{{ route('principals.show', [$p, 'period'=>$period]) }}" class="btn btn-secondary" style="padding:0.2rem 0.5rem;font-size:0.7rem;">Detail →</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    var data = @json($principals);
    new ApexCharts(document.querySelector("#principalBar"),{
        chart:{type:'bar',height:350,toolbar:{show:false},background:'transparent'},
        series:[
            {name:'Sales',data:data.map(p=>parseFloat(p.total_sales))},
            {name:'Returns',data:data.map(p=>parseFloat(p.total_returns))}
        ],
        xaxis:{categories:data.map(p=>p.name.replace('PT. ','').substring(0,15)),labels:{style:{colors:'#94a3b8',fontSize:'10px'},rotate:-45}},
        colors:['#6366f1','#ef4444'],theme:{mode:'dark'},grid:{borderColor:'#334155'},
        dataLabels:{enabled:false},plotOptions:{bar:{borderRadius:4,columnWidth:'60%'}},
        yaxis:{labels:{formatter:v=>'Rp '+(v/1000000).toFixed(0)+'M'}},
        tooltip:{y:{formatter:v=>'Rp '+new Intl.NumberFormat('id-ID').format(v)}}
    }).render();
});
</script>
@endsection
