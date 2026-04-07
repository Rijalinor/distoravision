@extends('layouts.app')
@section('page-title', 'Peluang Keranjang (Cross-Selling)')
@section('top-bar-actions')
<form method="GET" style="display:flex;gap:0.75rem;align-items:center;">
    @include('components.filter')
</form>
@endsection

@section('content')
@include('components.ai-insight')

<div class="alert alert-success">
    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
    <div>
        <strong>Analisa Afinitas Produk.</strong> Sistem mendeteksi toko mana yang rutin membeli Produk (SKU) tertentu, lalu menganalisa apa saja **Produk lain** yang juga dibeli oleh toko-toko tersebut di bulan yang sama. 
        Gunakan data ini untuk merancang paket <em>Bundling</em> yang akurat!
    </div>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">Matriks Peluang Cross-Selling</span>
    </div>
    <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 50px;">#</th>
                    <th style="width: 250px;">Source Product <br><span class="text-xs text-muted" style="text-transform:none;">(Produk/Item Utama)</span></th>
                    <th style="width: 150px;" class="text-right">Toko Pembeli<br><span class="text-xs text-muted" style="text-transform:none;">(Total Keranjang)</span></th>
                    <th>Identifikasi Peluang Bundling<br><span class="text-xs text-muted" style="text-transform:none;">(Persentase toko yang juga membeli Produk/Item berikut)</span></th>
                </tr>
            </thead>
            <tbody>
                @foreach($affinities as $index => $affinity)
                    @if(count($affinity['associations']) > 0)
                    <tr>
                        <td style="vertical-align: top; padding-top: 1.5rem;">{{ $index + 1 }}</td>
                        <td style="vertical-align: top; padding-top: 1.5rem;">
                            <div class="font-bold text-blue" style="font-size:0.9rem;">{{ Str::limit($affinity['item'], 30) }}</div>
                        </td>
                        <td style="vertical-align: top; padding-top: 1.5rem;" class="text-right font-mono font-bold text-green">
                            {{ number_format($affinity['total_baskets']) }} Toko
                        </td>
                        <td style="padding-top: 1rem;">
                            <div style="display:flex; flex-direction:column; gap:0.5rem;">
                            @foreach($affinity['associations'] as $target => $count)
                                @php
                                    $percentage = ($count / $affinity['total_baskets']) * 100;
                                    $barColor = $percentage > 50 ? 'var(--accent-green)' : ($percentage > 25 ? 'var(--accent-blue)' : 'var(--accent-yellow)');
                                @endphp
                                <div style="display:flex; align-items:center; gap: 1rem;">
                                    <div style="width: 200px; text-overflow:ellipsis; overflow:hidden; white-space:nowrap; font-size:0.8rem;">
                                        + {{ Str::limit($target, 35) }}
                                    </div>
                                    <div style="flex:1; background:rgba(255,255,255,0.05); height:8px; border-radius:4px; overflow:hidden; display:flex; align-items:center;">
                                        <div style="width: {{ $percentage }}%; background:{{ $barColor }}; height:100%; border-radius:4px;"></div>
                                    </div>
                                    <div style="width: 50px; text-align:right; font-family:monospace; font-size:0.8rem; font-weight:bold;">
                                        {{ number_format($percentage, 0) }}%
                                    </div>
                                </div>
                            @endforeach
                            </div>
                        </td>
                    </tr>
                    @endif
                @endforeach
                
                @if(count($affinities) == 0)
                <tr>
                    <td colspan="4" class="text-center text-muted" style="padding: 3rem;">Tidak ada transaksi yang bisa dianalisa untuk filter periode ini.</td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>
@endsection
