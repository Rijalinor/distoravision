@extends('layouts.app')
@section('page-title', 'Outlet Analytics')
@section('top-bar-actions')
<form method="GET" style="display:flex;gap:0.75rem;align-items:center;">
    @include('components.filter')
    <select name="city" class="period-select" onchange="this.form.submit()"><option value="">Semua Kota</option>@foreach($cities as $c)<option value="{{ $c }}" {{ $city==$c?'selected':'' }}>{{ $c }}</option>@endforeach</select>
    <input type="text" name="search" class="form-input" placeholder="Cari outlet..." value="{{ $search }}" style="width:200px;padding:0.3rem 0.6rem;font-size:0.8rem;">
</form>
@endsection

@section('content')
<div class="card">
    <div class="card-header"><span class="card-title">Daftar Outlet ({{ $outlets->total() }} outlet)</span></div>
    <table class="data-table">
        <thead><tr><th>#</th><th>Kode</th><th>Nama</th><th>Kota</th><th class="text-right">Transaksi</th><th class="text-right">Total Sales</th><th>Aksi</th></tr></thead>
        <tbody>
        @foreach($outlets as $i => $o)
        <tr>
            <td>{{ $outlets->firstItem() + $i }}</td>
            <td><span class="badge badge-blue">{{ $o->code }}</span></td>
            <td class="font-bold">{{ Str::limit($o->name, 30) }}</td>
            <td>{{ $o->city }}</td>
            <td class="text-right font-mono">{{ number_format($o->trx_count ?? 0) }}</td>
            <td class="text-right font-mono text-green">Rp {{ number_format($o->total_sales ?? 0, 0, ',', '.') }}</td>
            <td><a href="{{ route('outlets.show', [$o, 'period'=>$period]) }}" class="btn btn-secondary" style="padding:0.2rem 0.5rem;font-size:0.7rem;">Detail →</a></td>
        </tr>
        @endforeach
        </tbody>
    </table>
    <div class="pagination-wrapper">{{ $outlets->links() }}</div>
</div>
@endsection
