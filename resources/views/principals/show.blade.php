@extends('layouts.app')
@section('page-title', str_replace('PT. ', '', $principal->name))

@section('content')
<div class="kpi-grid">
    <div class="card kpi-card"><div class="card-title">Total Sales</div><div class="kpi-value" title="Rp {{ number_format($stats['total_sales'], 0, ',', '.') }}">Rp {{ number_format($stats['total_sales']/1000, 0, ',', '.') }}K</div></div>
    <div class="card kpi-card"><div class="card-title">Returns</div><div class="kpi-value" style="-webkit-text-fill-color:var(--accent-red);" title="Rp {{ number_format($stats['total_returns'], 0, ',', '.') }}">Rp {{ number_format($stats['total_returns']/1000, 0, ',', '.') }}K</div></div>
    <div class="card kpi-card"><div class="card-title">Total SKU</div><div class="kpi-value">{{ $stats['product_count'] }}</div></div>
    <div class="card kpi-card"><div class="card-title">Outlet Reach</div><div class="kpi-value">{{ $stats['outlet_reach'] }}</div></div>
</div>

<div class="grid-2">
    <div class="card"><div class="card-header"><span class="card-title">Top Produk (Sales)</span></div><table class="data-table"><thead><tr><th>#</th><th>Produk</th><th class="text-right">Qty</th><th class="text-right">Sales</th></tr></thead><tbody>@foreach($topProducts as $i=>$p)<tr><td>{{$i+1}}</td><td>{{Str::limit($p->name,35)}}</td><td class="text-right font-mono">{{number_format($p->qty)}}</td><td class="text-right font-mono text-green">Rp {{number_format($p->total,0,',','.')}}</td></tr>@endforeach</tbody></table></div>
    <div class="card"><div class="card-header"><span class="card-title">Produk Return</span></div><table class="data-table"><thead><tr><th>#</th><th>Produk</th><th class="text-right">Qty</th><th class="text-right">Total</th></tr></thead><tbody>@foreach($returnedProducts as $i=>$p)<tr><td>{{$i+1}}</td><td>{{Str::limit($p->name,35)}}</td><td class="text-right font-mono">{{number_format($p->qty)}}</td><td class="text-right font-mono text-red">Rp {{number_format($p->total,0,',','.')}}</td></tr>@endforeach</tbody></table></div>
</div>
<div style="margin-top:1.5rem;"><a href="{{ route('principals.index', ['period'=>$period]) }}" class="btn btn-secondary">← Kembali</a></div>
@endsection
