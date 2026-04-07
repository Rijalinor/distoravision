@extends('layouts.app')
@section('page-title', 'Produk Analytics')
@section('top-bar-actions')
<form method="GET" style="display:flex;gap:0.75rem;align-items:center;">
    @include('components.filter')
    <input type="text" name="search" class="form-input" placeholder="Cari produk..." value="{{ $search }}" style="width:200px;padding:0.3rem 0.6rem;font-size:0.8rem;">
</form>
@endsection

@section('content')
<div class="card">
    <div class="card-header"><span class="card-title">Ranking Produk ({{ $products->total() }} SKU)</span></div>
    <table class="data-table">
        <thead><tr><th>#</th><th>Item No</th><th>Nama</th><th>Principal</th><th class="text-right">Qty</th><th class="text-right">Sales</th><th class="text-right">Returns</th><th class="text-right">Return Rate</th></tr></thead>
        <tbody>
        @foreach($products as $i => $p)
            @php $rr = $p->total_sales > 0 ? ($p->total_returns / $p->total_sales * 100) : 0; @endphp
            <tr>
                <td>{{ $products->firstItem() + $i }}</td>
                <td><span class="badge badge-blue" style="font-size:0.65rem;">{{ $p->item_no }}</span></td>
                <td class="font-bold">{{ Str::limit($p->name, 35) }}</td>
                <td class="text-sm text-muted">{{ Str::limit(str_replace('PT. ', '', $p->principal_name), 20) }}</td>
                <td class="text-right font-mono">{{ number_format($p->total_qty) }}</td>
                <td class="text-right font-mono text-green">Rp {{ number_format($p->total_sales, 0, ',', '.') }}</td>
                <td class="text-right font-mono text-red">Rp {{ number_format($p->total_returns, 0, ',', '.') }}</td>
                <td class="text-right"><span class="badge {{ $rr > 10 ? 'badge-red' : ($rr > 0 ? 'badge-yellow' : 'badge-green') }}">{{ number_format($rr, 1) }}%</span></td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <div class="pagination-wrapper">{{ $products->links() }}</div>
</div>
@endsection
