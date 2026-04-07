@extends('layouts.app')
@section('page-title', $outlet->name)

@section('content')
<div class="kpi-grid" style="grid-template-columns:repeat(3,1fr);">
    <div class="card kpi-card"><div class="card-title">Total Sales</div><div class="kpi-value" title="Rp {{ number_format($stats['total_sales'], 0, ',', '.') }}">Rp {{ number_format($stats['total_sales']/1000, 0, ',', '.') }}K</div></div>
    <div class="card kpi-card"><div class="card-title">Returns</div><div class="kpi-value" style="-webkit-text-fill-color:var(--accent-red);" title="Rp {{ number_format($stats['total_returns'], 0, ',', '.') }}">Rp {{ number_format($stats['total_returns']/1000, 0, ',', '.') }}K</div></div>
    <div class="card kpi-card"><div class="card-title">Jumlah Produk</div><div class="kpi-value">{{ $stats['product_count'] }}</div></div>
</div>

<div class="card" style="margin-bottom:1rem;">
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;font-size:0.85rem;">
        <div><span class="text-muted">Kode:</span> <strong>{{ $outlet->code }}</strong></div>
        <div><span class="text-muted">Kota:</span> <span class="badge badge-blue">{{ $outlet->city }}</span></div>
        <div><span class="text-muted">Route:</span> {{ $outlet->route }}</div>
        <div style="grid-column:span 3;"><span class="text-muted">Alamat:</span> {{ $outlet->address }}</div>
    </div>
</div>

<div class="card">
    <div class="card-header"><span class="card-title">Riwayat Pembelian</span></div>
    <table class="data-table">
        <thead><tr><th>Tanggal</th><th>Type</th><th>Principal</th><th>Produk</th><th class="text-right">Qty</th><th class="text-right">Amount</th></tr></thead>
        <tbody>
        @foreach($purchaseHistory as $h)
        <tr>
            <td>{{ $h->so_date ? \Carbon\Carbon::parse($h->so_date)->format('d M') : '-' }}</td>
            <td><span class="badge {{ $h->type === 'I' ? 'badge-green' : 'badge-red' }}">{{ $h->type === 'I' ? 'Invoice' : 'Return' }}</span></td>
            <td class="text-sm">{{ Str::limit(str_replace('PT. ', '', $h->principal_name), 20) }}</td>
            <td>{{ Str::limit($h->product_name, 30) }}</td>
            <td class="text-right font-mono">{{ $h->qty_base }}</td>
            <td class="text-right font-mono {{ $h->type === 'I' ? 'text-green' : 'text-red' }}">Rp {{ number_format(abs($h->ar_amt), 0, ',', '.') }}</td>
        </tr>
        @endforeach
        </tbody>
    </table>
</div>
<div style="margin-top:1.5rem;"><a href="{{ route('outlets.index', ['period'=>$period]) }}" class="btn btn-secondary">← Kembali</a></div>
@endsection
