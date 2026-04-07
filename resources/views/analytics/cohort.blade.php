@extends('layouts.app')
@section('page-title', 'Cohort Analysis (Retention Matriks)')

@section('content')
<div class="alert alert-blue" style="margin-bottom: 1.5rem;">
    <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
    <div>
        <strong>Matriks Kinerja Rentensi Toko Baru.</strong> Sistem mengelompokkan toko berdasarkan <em>Bulan Transaksi Pertama</em> mereka (Cohort). 
        <br>
        <span style="font-size: 0.85rem; opacity: 0.9;">
            <strong>Cara Membaca:</strong> Pilih baris (Cohort), lalu tarik ke kanan untuk melihat performa mereka di tiap bulan kalender.
            <br>
            Contoh: Pada baris <strong>Desember 2025</strong>, lihat kolom <strong>Jan 2026</strong>. Jika tertulis 76%, berarti 76% toko dari cohort Desember belanja kembali di Januari.
        </span>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">Retention Matrix (Berdasarkan Bulan Akuisisi)</span>
    </div>
    <div style="overflow-x:auto;">
        <table class="data-table" style="text-align: center;">
            <thead>
                <tr>
                    <th style="width: 150px; text-align: left;">Bulan Akuisisi (Cohort)</th>
                    <th style="width: 100px;">Total Toko Awal</th>
                    @foreach($periods as $index => $periodLabel)
                        <th style="width: 100px;">
                            {{ \Carbon\Carbon::parse($periodLabel)->translatedFormat('M Y') }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($matrix as $cohortMonth => $periodData)
                    @php
                        $cohortCarbon = \Carbon\Carbon::parse($cohortMonth);
                        $originalSize = $periodData[$cohortMonth] ?? 1; // Prevent division by zero
                        $startIndex = array_search($cohortMonth, $periods);
                    @endphp
                    <tr>
                        <td class="font-bold text-blue" style="text-align: left;">{{ $cohortCarbon->format('F Y') }}</td>
                        <td class="font-mono text-muted">{{ number_format($originalSize) }}</td>
                        
                        @foreach($periods as $index => $colPeriod)
                            @php
                                $colCarbon = \Carbon\Carbon::parse($colPeriod);
                                $monthDiff = $index - $startIndex;
                            @endphp
                            
                            @if($index < $startIndex)
                                <td></td> {{-- Bulan sebelum toko ini lahir --}}
                            @elseif($colPeriod == $cohortMonth)
                                {{-- Bulan 0 --}}
                                <td style="background-color: rgba(34, 197, 94, 0.9); color: white; font-weight: bold; font-family: monospace;" 
                                    title="Bulan Akuisisi: Semua {{ number_format($originalSize) }} toko baru mulai berbelanja.">
                                    100%
                                </td>
                            @else
                                {{-- Bulan-bulan berikutnya --}}
                                @php
                                    $activeCount = $periodData[$colPeriod] ?? 0;
                                    $percentage = ($activeCount / $originalSize) * 100;
                                    
                                    // Heatmap gradient
                                    $alpha = min($percentage / 100, 0.9);
                                    if ($percentage > 0) {
                                        $alpha = max($alpha, 0.1); // minimum visibility
                                    }
                                @endphp
                                <td style="background-color: rgba(59, 130, 246, {{ $alpha }}); color: {{ $percentage > 40 ? 'white' : 'var(--text-color)' }}; font-family: monospace; cursor: help;"
                                    title="Dari {{ number_format($originalSize) }} toko yang lahir di {{ $cohortCarbon->format('M Y') }}, sebanyak {{ number_format($activeCount) }} toko ({{ round($percentage, 1) }}%) tetap aktif berbelanja di {{ $colCarbon->format('M Y') }}.">
                                    @if($activeCount > 0)
                                        {{ number_format($percentage, 0) }}%
                                        <div style="font-size: 0.6rem; opacity: 0.7;">{{ $activeCount }} toko</div>
                                    @else
                                        -
                                    @endif
                                </td>
                            @endif
                        @endforeach
                    </tr>
                @endforeach
                
                @if(count($matrix) == 0)
                <tr>
                    <td colspan="{{ count($periods) + 2 }}" class="text-muted" style="padding: 3rem;">
                        Belum ada data transaksi yang cukup untuk menyusun Cohort Analysis. Masukkan setidaknya 2 bulan data transaksi.
                    </td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>
@endsection
