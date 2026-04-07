@extends('layouts.app')
@section('page-title', 'Buku Rapor')

@section('top-bar-actions')
<form method="GET" style="display:flex;gap:0.75rem;align-items:center;" class="no-print">
    @include('components.filter')
    {{-- Export to Excel --}}
    <a href="{{ request()->fullUrlWithQuery(['export' => 'excel']) }}"
       class="btn btn-primary"
       style="display:flex;align-items:center;gap:0.5rem;background:#16a34a;border-color:#16a34a;text-decoration:none;"
       title="Download Laporan Excel 360° (8 Sheet)">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
        Excel 360°
    </a>
    {{-- Print PDF --}}
    <button type="button" onclick="window.print()" class="btn btn-primary" style="display:flex;align-items:center;gap:0.5rem;background:#3b82f6;">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
        Cetak PDF
    </button>
</form>
@endsection

@section('content')

<style>
/* Cetak PDF Specific Styles */
@media print {
    @page {
        size: A4 portrait;
        margin: 1cm;
    }
    body {
        background-color: #ffffff !important; 
        color: #000000 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    h1, h2, h3, .card-title, .kpi-value, .kpi-label, td, th {
        color: #000000 !important;
    }
    .text-muted { color: #475569 !important; }
    .sidebar, .top-bar, .no-print, .alert {
        display: none !important;
    }
    .main-content, .content-area {
        margin: 0 !important;
        padding: 0 !important;
        width: 100% !important;
    }
    /* Force page break avoidance inside cards */
    .card {
        background-color: #ffffff !important;
        break-inside: avoid;
        page-break-inside: avoid;
        border: 1px solid #cbd5e1 !important; 
    }
    .kpi-grid {
        display: grid !important;
        grid-template-columns: repeat(4, 1fr) !important;
    }
    #report-container {
        padding: 2rem !important;
    }
}
</style>

<div id="report-container">
    
    <!-- Cover/Header Laporan -->
    <div style="text-align: center; margin-bottom: 2rem;">
        <h1 style="font-size: 2rem; margin-bottom: 0.5rem;">Buku Rapor Penjualan Eksklusif</h1>
        <h3 style="color: var(--text-muted); font-weight:normal;">DistoraVision Enterprise</h3>
        
        <div style="display:inline-flex; gap:2rem; margin-top:1.5rem; padding: 1rem 2rem; background: rgba(59,130,246,0.1); border-radius: 8px; border: 1px solid rgba(59,130,246,0.3);">
            <div>
                <div style="font-size:0.8rem; color:var(--text-muted);">Periode Laporan</div>
                <div style="font-weight:bold; font-size:1.1rem; color:white;">{{ \Carbon\Carbon::parse($period.'-01')->translatedFormat('F Y') }}</div>
            </div>
            <div style="border-left: 1px solid rgba(255,255,255,0.1);"></div>
            <div>
                <div style="font-size:0.8rem; color:var(--text-muted);">Principal / Brand</div>
                <div style="font-weight:bold; font-size:1.1rem; color:var(--accent-blue);">{{ $principalName }}</div>
            </div>
        </div>
    </div>

    <!-- Section 1: Kinerja Finansial -->
    <h2 style="font-size: 1.2rem; margin-bottom: 1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem; color:var(--text-color);">1. Ringkasan Kinerja Eksekutif</h2>
    <div class="kpi-grid" style="grid-template-columns: repeat(4, 1fr); margin-bottom: 2rem;">
        <div class="card kpi-card" style="border-top: 4px solid var(--accent-blue);">
            <div class="card-title">Net Sales (AR)</div>
            <div class="kpi-value text-blue" style="font-size:1.25rem; font-weight:800; white-space:nowrap; letter-spacing:-0.5px;">Rp {{ number_format($netSales, 0, ',', '.') }}</div>
            <div class="kpi-label">Sales Bersih Aktual</div>
        </div>
        <div class="card kpi-card" style="border-top: 4px solid var(--accent-red);">
            <div class="card-title">Cost of Goods Sold</div>
            <div class="kpi-value text-red" style="font-size:1.25rem; font-weight:800; white-space:nowrap; letter-spacing:-0.5px;">Rp {{ number_format($totalCogs, 0, ',', '.') }}</div>
            <div class="kpi-label">HPP Bersih</div>
        </div>
        <div class="card kpi-card" style="border-top: 4px solid var(--accent-green);">
            <div class="card-title">Gross Profit</div>
            <div class="kpi-value text-green" style="font-size:1.25rem; font-weight:800; white-space:nowrap; letter-spacing:-0.5px;">Rp {{ number_format($grossProfit, 0, ',', '.') }}</div>
            <div class="kpi-label">Laba Kotor Utama</div>
        </div>
        <div class="card kpi-card" style="border-top: 4px solid var(--accent-yellow);">
            <div class="card-title">Blended Margin</div>
            <div class="kpi-value text-yellow" style="font-size:1.25rem; font-weight:800; white-space:nowrap; letter-spacing:-0.5px;">{{ number_format($blendedMargin, 2) }}%</div>
            <div class="kpi-label" style="font-size:0.7rem; white-space:nowrap;">Diskon: Rp {{ number_format($totalDiscount, 0, ',', '.') }}</div>
        </div>
    </div>

    <!-- Section 2: Toko Berisiko -->
    <h2 style="font-size: 1.2rem; margin-bottom: 1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem; color:var(--text-color);">2. Peringatan Dini (Outlet Churn)</h2>
    <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 1.5rem; margin-bottom: 2rem;">
        <div class="card" style="background: rgba(239, 68, 68, 0.05); border-color: rgba(239, 68, 68, 0.3);">
            <div class="card-header"><span class="card-title text-red">🚨 Toko Hilang (Berhenti)</span></div>
            <div style="padding: 1.5rem; text-align:center;">
                <div style="font-size: 2.5rem; font-weight:800; color:var(--accent-red); margin-bottom:0.5rem;">{{ $sleepingOutletsCount }}</div>
                <div class="text-muted text-sm">Outlet yang aktif bulan lalu namun tidak belanja sama sekali bulan ini.</div>
            </div>
        </div>
        <div class="card" style="display:flex; flex-direction:column; justify-content:center;">
            <div style="padding: 1.5rem;">
                <div class="card-title">Estimasi Kerugian (Opportunity Loss)</div>
                <div style="font-size: 1.6rem; font-weight:800; font-family:monospace; color:var(--text-color); margin: 0.5rem 0; letter-spacing:-0.5px;">
                    Rp {{ number_format($sleepingOutletsLoss, 0, ',', '.') }}
                </div>
                <p class="text-muted" style="font-size:0.75rem;">Ini adalah nilai penjualan yang dihasilkan {{ $sleepingOutletsCount }} toko tersebut bulan lalu. Segera minta tim Salesman turun meninjau kenapa mereka pindah/berhenti bulan ini.</p>
            </div>
        </div>
    </div>

    <!-- Section 3: Produk Penopang Utama -->
    <h2 style="font-size: 1.2rem; margin-bottom: 1rem; border-bottom: 1px solid var(--border-color); padding-bottom: 0.5rem; color:var(--text-color);">3. Top 10 Produk Meriam (Ujung Tombak)</h2>
    <div class="card">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>Nama Produk</th>
                    <th class="text-right">Total Revenue Terjual</th>
                    <th class="text-right">Kontribusi Omset (%)</th>
                </tr>
            </thead>
            <tbody>
                @foreach($topProducts as $index => $prod)
                    @php $contrib = $netSales > 0 ? ($prod->revenue / $netSales) * 100 : 0; @endphp
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td class="font-bold text-blue">{{ $prod->product_name }}</td>
                        <td class="text-right font-mono">Rp {{ number_format($prod->revenue, 0, ',', '.') }}</td>
                        <td class="text-right">
                            <span class="badge {{ $contrib > 10 ? 'badge-green' : 'badge-blue' }}">{{ number_format($contrib, 1) }}%</span>
                        </td>
                    </tr>
                @endforeach
                @if(count($topProducts) == 0)
                <tr>
                    <td colspan="4" class="text-center text-muted" style="padding:2rem;">Data tidak tersedia untuk filter ini.</td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>

    <!-- Footer -->
    <div style="text-align: center; margin-top: 3rem; color: var(--text-muted); font-size: 0.8rem; font-family:monospace;">
        Dibuat secara otomatis oleh DistoraVision Intelligence Engine pada {{ now()->translatedFormat('d F Y H:i:s') }}
    </div>

</div>

@endsection
