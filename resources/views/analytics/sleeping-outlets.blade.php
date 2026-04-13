@extends('layouts.app')
@section('page-title', 'Toko Berhenti Transaksi')
@section('top-bar-actions')
<form method="GET" style="display:flex;gap:0.75rem;align-items:center;">
    @include('components.filter')
</form>
@endsection

@section('content')
@include('components.ai-insight')
@php
    $isPreviousRange = str_contains($previousPeriod, ' s/d ');
    $resolvedCurrentPeriod = $currentPeriodLabel ?? $period;
    $isCurrentRange = str_contains($resolvedCurrentPeriod, ' s/d ');
    $previousPeriodLong = $isPreviousRange
        ? $previousPeriod
        : \Carbon\Carbon::parse($previousPeriod . '-01')->translatedFormat('F Y');
    $previousPeriodShort = $isPreviousRange
        ? $previousPeriod
        : \Carbon\Carbon::parse($previousPeriod . '-01')->format('M Y');
    $currentPeriodLong = $isCurrentRange
        ? $resolvedCurrentPeriod
        : \Carbon\Carbon::parse($resolvedCurrentPeriod . '-01')->translatedFormat('F Y');
@endphp

<div class="alert alert-error" style="background: rgba(239, 68, 68, 0.15); border-color: rgba(239, 68, 68, 0.4);">
    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
    <div>
        <strong>Peringatan Kehilangan Sales!</strong><br>
        Toko-toko berikut tercatat memiliki transaksi pada periode <strong>{{ $previousPeriodLong }}</strong>, namun <u>sama sekali tidak melakukan transaksi (Faktur = 0)</u> pada periode <strong>{{ $currentPeriodLong }}</strong>.
    </div>
</div>

<div class="kpi-grid" style="grid-template-columns: 1fr 1fr;">
    <div class="card kpi-card">
        <div class="card-title" title="Total jumlah toko yang tidak melakukan order apapun di periode ini padahal bulan lalu belanja.">Jumlah Toko Churn</div>
        <div class="kpi-value text-red">{{ count($sleepingOutletsList) }}</div>
        <div class="kpi-label">Outlet berhenti order</div>
    </div>
    <div class="card kpi-card">
        <div class="card-title" title="Total Rp omset yang dihasilkan oleh toko-toko ini pada bulan sebelumnya. Asumsinya kita kehilangan Rp segini akibat mereka tidak repeat order bulan ini.">Potensi Sales Hilang (Opportunity Loss)</div>
        <div class="kpi-value text-red">Rp {{ number_format($totalLostRevenue, 0, ',', '.') }}</div>
        <div class="kpi-label">Berdasarkan total kontribusi mereka di bulan lalu</div>
    </div>
</div>

<div class="card">
    <div class="card-header"><span class="card-title">Daftar Toko Berhenti (Urut dari yang Terbesar)</span></div>
    
    @if(empty($sleepingOutletsList))
        <div style="text-align:center; padding: 4rem;">
            <div style="font-size:3rem;margin-bottom:1rem;">🎉</div>
            <h2>Luar Biasa!</h2>
            <p class="text-muted">Tidak ada toko yang churn/berhenti order pada periode ini.</p>
        </div>
    @else
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nama Toko / Outlet</th>
                    <th>Kota</th>
                    <th>Rute</th>
                    <th>Tgl Transaksi Terakhir</th>
                    <th class="text-right">Sales Terakhir ({{ $previousPeriodShort }})</th>
                </tr>
            </thead>
            <tbody>
                @foreach($sleepingOutletsList as $i => $item)
                <tr>
                    <td>{{ $i + 1 }}</td>
                    <td class="font-bold">{{ $item->outlet->name }} <br><span class="badge" style="font-size:0.6rem;">{{ $item->outlet->code }}</span></td>
                    <td><span class="badge badge-blue">{{ $item->outlet->city }}</span></td>
                    <td class="text-muted">{{ $item->outlet->route }}</td>
                    <td class="font-mono text-muted">{{ $item->last_order ? \Carbon\Carbon::parse($item->last_order)->format('d M Y') : '-' }}</td>
                    <td class="text-right font-mono text-red font-bold">Rp {{ number_format($item->prev_sales, 0, ',', '.') }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
