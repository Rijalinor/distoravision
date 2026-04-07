@extends('layouts.app')
@section('page-title', 'Performa Salesman')
@section('top-bar-actions')
<form method="GET" style="display:flex;gap:0.75rem;align-items:center;">
    @include('components.filter')
</form>
@endsection

@section('content')
<div class="card">
    <div class="card-header"><span class="card-title">Ranking Salesman - {{ \Carbon\Carbon::parse($period.'-01')->translatedFormat('F Y') }}</span></div>
    <table class="data-table">
        <thead><tr><th>#</th><th>Nama</th><th>Kode</th><th class="text-right">Sales</th><th class="text-right">Returns</th><th class="text-right">Return Rate</th><th class="text-right">Invoice</th><th>Aksi</th></tr></thead>
        <tbody>
        @foreach($salesmen as $i => $s)
            @php $rr = $s->total_sales > 0 ? ($s->total_returns / $s->total_sales * 100) : 0; @endphp
            <tr>
                <td>{{ $salesmen->firstItem() + $i }}</td>
                <td class="font-bold">{{ $s->name }}</td>
                <td><span class="badge badge-blue">{{ $s->sales_code }}</span></td>
                <td class="text-right font-mono text-green">Rp {{ number_format($s->total_sales, 0, ',', '.') }}</td>
                <td class="text-right font-mono text-red">Rp {{ number_format($s->total_returns ?? 0, 0, ',', '.') }}</td>
                <td class="text-right"><span class="badge {{ $rr > 10 ? 'badge-red' : 'badge-green' }}">{{ number_format($rr, 1) }}%</span></td>
                <td class="text-right font-mono">{{ number_format($s->invoice_count) }}</td>
                <td><a href="{{ route('salesmen.show', [$s, 'period' => $period]) }}" class="btn btn-secondary" style="padding:0.2rem 0.5rem;font-size:0.7rem;">Detail →</a></td>
            </tr>
        @endforeach
        </tbody>
    </table>
    <div class="pagination-wrapper">{{ $salesmen->links() }}</div>
</div>
@endsection
