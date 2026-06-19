@extends('layouts.app')
@section('page-title', 'Buku Rapor')

@section('top-bar-actions')
<form method="GET" style="display:flex;gap:0.75rem;align-items:center;" class="no-print">
    @include('components.filter')
    {{-- Export to Excel --}}
    <a href="{{ request()->fullUrlWithQuery(['export' => 'excel']) }}"
       class="btn btn-primary"
       id="btn-export-excel"
       style="display:flex;align-items:center;gap:0.5rem;background:#16a34a;border-color:#16a34a;text-decoration:none;"
       title="Download Laporan Excel 360° (10 Sheet)">
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
    .report-section {
        break-inside: avoid;
        page-break-inside: avoid;
    }
}
.report-section { margin-bottom: 2.5rem; }
.section-title {
    font-size: 1.15rem;
    margin-bottom: 1rem;
    border-bottom: 1px solid var(--border-color);
    padding-bottom: 0.5rem;
    color: var(--text-color);
    font-weight: 700;
}
.mini-kpi-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 1rem;
}
.mini-kpi {
    padding: 1rem;
    border-radius: 8px;
    text-align: center;
}
.mini-kpi .label { font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 0.25rem; }
.mini-kpi .value { font-size: 1.3rem; font-weight: 800; font-family: 'JetBrains Mono', monospace; }

/* Loading Overlay Styles */
#loading-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(15, 23, 42, 0.85);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    display: none;
    justify-content: center;
    align-items: center;
    z-index: 9999;
}
.loader-card {
    background: #1e293b;
    border: 1px solid #334155;
    padding: 2.5rem;
    border-radius: 16px;
    max-width: 480px;
    width: 90%;
    text-align: center;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    animation: scaleUp 0.3s ease-out;
}
@keyframes scaleUp {
    from { transform: scale(0.95); opacity: 0; }
    to { transform: scale(1); opacity: 1; }
}
.spinner-ring {
    display: inline-block;
    width: 64px;
    height: 64px;
    border: 4px solid rgba(59, 130, 246, 0.1);
    border-radius: 50%;
    border-top-color: #3b82f6;
    animation: spin 1s ease-in-out infinite;
    margin-bottom: 1.5rem;
}
@keyframes spin {
    to { transform: rotate(360deg); }
}
.loader-title {
    font-size: 1.25rem;
    font-weight: 700;
    color: #f8fafc;
    margin-bottom: 0.75rem;
}
.loader-desc {
    font-size: 0.875rem;
    color: #94a3b8;
    line-height: 1.5;
    margin-bottom: 1.5rem;
}
.btn-close-loader {
    background: #334155;
    color: #f8fafc;
    border: none;
    padding: 0.5rem 1.25rem;
    border-radius: 8px;
    font-size: 0.875rem;
    cursor: pointer;
    transition: background 0.2s;
}
.btn-close-loader:hover {
    background: #475569;
}
</style>

<div id="report-container">
    
    <!-- Cover/Header Laporan -->
    <div style="text-align: center; margin-bottom: 2rem;">
        <h1 style="font-size: 2rem; margin-bottom: 0.5rem;">Buku Rapor Penjualan 360°</h1>
        <h3 style="color: var(--text-muted); font-weight:normal;">DistoraVision Enterprise Intelligence</h3>
        
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

    <!-- Section 1: Kinerja Finansial (8 KPI) -->
    <div class="report-section">
        <h2 class="section-title">1. Ringkasan Kinerja Eksekutif</h2>
        <div class="kpi-grid" style="grid-template-columns: repeat(4, 1fr); margin-bottom: 1.25rem;">
            <div class="card kpi-card" style="border-top: 4px solid var(--accent-blue);">
                <div class="card-title">Net Sales (Taxed - Return)</div>
                <div class="kpi-value text-blue" style="font-size:1.25rem; font-weight:800; white-space:nowrap; letter-spacing:-0.5px;">Rp {{ number_format($netSales, 0, ',', '.') }}</div>
                <div class="kpi-label">Omset Bersih</div>
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
        <div class="kpi-grid" style="grid-template-columns: repeat(4, 1fr);">
            <div class="card kpi-card" style="border-top: 4px solid #8b5cf6;">
                <div class="card-title">MoM Growth</div>
                <div class="kpi-value" style="font-size:1.25rem; font-weight:800; color: {{ $momGrowth >= 0 ? 'var(--accent-green)' : 'var(--accent-red)' }};">
                    {{ $momGrowth >= 0 ? '+' : '' }}{{ number_format($momGrowth, 1) }}%
                </div>
                <div class="kpi-label" style="font-size:0.65rem;">vs. Rp {{ number_format($prevNetSales, 0, ',', '.') }}</div>
            </div>
            <div class="card kpi-card" style="border-top: 4px solid #ec4899;">
                <div class="card-title">Return Rate</div>
                <div class="kpi-value" style="font-size:1.25rem; font-weight:800; color: {{ $returnRate > 10 ? 'var(--accent-red)' : 'var(--accent-green)' }};">{{ number_format($returnRate, 2) }}%</div>
                <div class="kpi-label" style="font-size:0.65rem;">Retur: Rp {{ number_format($totalReturns, 0, ',', '.') }}</div>
            </div>
            <div class="card kpi-card" style="border-top: 4px solid #06b6d4;">
                <div class="card-title">Invoice / Faktur</div>
                <div class="kpi-value" style="font-size:1.25rem; font-weight:800; color:var(--accent-blue);">{{ number_format($invoiceCount) }}</div>
                <div class="kpi-label">Transaksi Unik</div>
            </div>
            <div class="card kpi-card" style="border-top: 4px solid #f59e0b;">
                <div class="card-title">Outlet Aktif</div>
                <div class="kpi-value" style="font-size:1.25rem; font-weight:800; color:var(--accent-yellow);">{{ number_format($outletCount) }}</div>
                <div class="kpi-label">Toko Bertransaksi</div>
            </div>
        </div>
    </div>

    <!-- Section 2: Peringatan Dini (Churn) -->
    <div class="report-section">
        <h2 class="section-title">2. Peringatan Dini (Outlet Churn)</h2>
        <div style="display: grid; grid-template-columns: 1fr 2fr; gap: 1.5rem;">
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
    </div>

    <!-- Section 3: Kesehatan Margin per Principal -->
    <div class="report-section">
        <h2 class="section-title">3. Kesehatan Margin per Principal</h2>
        <div class="card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:50px;">#</th>
                        <th>Principal</th>
                        <th class="text-right">Revenue (Rp)</th>
                        <th class="text-right">Gross Profit (Rp)</th>
                        <th class="text-right">Margin (%)</th>
                        <th class="text-right">Kontribusi (%)</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($principalMargins as $index => $pm)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td class="font-bold">{{ $pm->principal_name }}</td>
                            <td class="text-right font-mono">Rp {{ number_format($pm->revenue, 0, ',', '.') }}</td>
                            <td class="text-right font-mono" style="color: {{ $pm->gross_profit >= 0 ? 'var(--accent-green)' : 'var(--accent-red)' }};">Rp {{ number_format($pm->gross_profit, 0, ',', '.') }}</td>
                            <td class="text-right">
                                <span class="badge {{ $pm->margin_percent >= 15 ? 'badge-green' : ($pm->margin_percent >= 5 ? 'badge-blue' : 'badge-red') }}">{{ number_format($pm->margin_percent, 1) }}%</span>
                            </td>
                            <td class="text-right">{{ number_format($pm->contribution, 1) }}%</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-center text-muted" style="padding:2rem;">Data tidak tersedia.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Section 4: Top 10 Produk Meriam -->
    <div class="report-section">
        <h2 class="section-title">4. Top 10 Produk Meriam (Ujung Tombak)</h2>
        <div class="card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th>Nama Produk</th>
                        <th class="text-right">Total Revenue</th>
                        <th class="text-right">Kontribusi (%)</th>
                        <th class="text-right">Margin (%)</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($topProducts as $index => $prod)
                        @php 
                            $contrib = $netSales > 0 ? ($prod->revenue / $netSales) * 100 : 0;
                            $gp = $prod->revenue - $prod->cogs;
                            $margin = $prod->revenue > 0 ? ($gp / $prod->revenue) * 100 : 0;
                        @endphp
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td class="font-bold text-blue">{{ $prod->product_name }}</td>
                            <td class="text-right font-mono">Rp {{ number_format($prod->revenue, 0, ',', '.') }}</td>
                            <td class="text-right">
                                <span class="badge {{ $contrib > 10 ? 'badge-green' : 'badge-blue' }}">{{ number_format($contrib, 1) }}%</span>
                            </td>
                            <td class="text-right">
                                <span class="badge {{ $margin >= 15 ? 'badge-green' : ($margin >= 5 ? 'badge-blue' : 'badge-red') }}">{{ number_format($margin, 1) }}%</span>
                            </td>
                        </tr>
                    @endforeach
                    @if(count($topProducts) == 0)
                    <tr>
                        <td colspan="5" class="text-center text-muted" style="padding:2rem;">Data tidak tersedia untuk filter ini.</td>
                    </tr>
                    @endif
                </tbody>
            </table>
        </div>
    </div>

    <!-- Section 5: Rapor Salesman Top 10 -->
    <div class="report-section">
        <h2 class="section-title">5. Rapor Salesman — Top 10 Kontributor Laba</h2>
        <div class="card">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:50px;">#</th>
                        <th>Salesman</th>
                        <th class="text-right">Net Sales (Rp)</th>
                        <th class="text-right">Gross Profit (Rp)</th>
                        <th class="text-right">Margin (%)</th>
                        <th class="text-right">Disc. Depth (%)</th>
                        <th class="text-right">Outlet</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($topSalesmen as $index => $sm)
                        <tr>
                            <td>{{ $index + 1 }}</td>
                            <td class="font-bold">{{ $sm->salesman_name }}</td>
                            <td class="text-right font-mono">Rp {{ number_format($sm->net_sales, 0, ',', '.') }}</td>
                            <td class="text-right font-mono" style="color: {{ $sm->gross_profit >= 0 ? 'var(--accent-green)' : 'var(--accent-red)' }};">Rp {{ number_format($sm->gross_profit, 0, ',', '.') }}</td>
                            <td class="text-right">
                                <span class="badge {{ $sm->margin_percent >= 15 ? 'badge-green' : ($sm->margin_percent >= 5 ? 'badge-blue' : 'badge-red') }}">{{ number_format($sm->margin_percent, 1) }}%</span>
                            </td>
                            <td class="text-right">
                                <span class="badge {{ $sm->discount_depth > 15 ? 'badge-red' : 'badge-blue' }}">{{ number_format($sm->discount_depth, 1) }}%</span>
                            </td>
                            <td class="text-right">{{ $sm->outlet_count }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted" style="padding:2rem;">Data tidak tersedia.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Section 6: Trajektori Outlet -->
    <div class="report-section">
        <h2 class="section-title">6. Trajektori Outlet (6 Bulan Terakhir)</h2>
        <div class="mini-kpi-grid">
            <div class="mini-kpi card" style="border-top: 3px solid #22c55e;">
                <div class="label">📈 Growing</div>
                <div class="value" style="color:#22c55e;">{{ $trajectorySegments['Growing'] }}</div>
            </div>
            <div class="mini-kpi card" style="border-top: 3px solid #3b82f6;">
                <div class="label">➡️ Stable</div>
                <div class="value" style="color:#3b82f6;">{{ $trajectorySegments['Stable'] }}</div>
            </div>
            <div class="mini-kpi card" style="border-top: 3px solid #ef4444;">
                <div class="label">📉 Declining</div>
                <div class="value" style="color:#ef4444;">{{ $trajectorySegments['Declining'] }}</div>
            </div>
            <div class="mini-kpi card" style="border-top: 3px solid #06b6d4;">
                <div class="label">🆕 New</div>
                <div class="value" style="color:#06b6d4;">{{ $trajectorySegments['New'] }}</div>
            </div>
            <div class="mini-kpi card" style="border-top: 3px solid #6b7280;">
                <div class="label">💀 Dead</div>
                <div class="value" style="color:#6b7280;">{{ $trajectorySegments['Dead'] }}</div>
            </div>
        </div>
        <div class="card" style="margin-top:1rem; padding:1rem; font-size:0.8rem; color:var(--text-muted);">
            <strong>Total {{ $totalTrajectoryOutlets }} outlet</strong> dianalisis menggunakan <em>Linear Regression Slope</em> dari data 6 bulan. Growing = slope > +10%, Declining = slope < -10%, Stable = slope di antara ±10%.
        </div>
    </div>

    <!-- Section 7: Konsentrasi Revenue (Pareto) -->
    <div class="report-section">
        <h2 class="section-title">7. Konsentrasi Revenue (Hukum Pareto 80/20)</h2>
        <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 1.25rem;">
            <div class="card" style="padding:1.5rem; text-align:center; border-left: 5px solid #22c55e; background: rgba(34,197,94,0.05);">
                <div style="font-size:0.75rem; color:var(--text-muted); margin-bottom:0.5rem;">KELAS A (VIP)</div>
                <div style="font-size:2.5rem; font-weight:800; color:#22c55e;">{{ $paretoKlasses['A'] }}</div>
                <div style="font-size:0.7rem; color:var(--text-muted); margin-top:0.25rem;">produk = 80% omset</div>
            </div>
            <div class="card" style="padding:1.5rem; text-align:center; border-left: 5px solid #f59e0b; background: rgba(245,158,11,0.05);">
                <div style="font-size:0.75rem; color:var(--text-muted); margin-bottom:0.5rem;">KELAS B</div>
                <div style="font-size:2.5rem; font-weight:800; color:#f59e0b;">{{ $paretoKlasses['B'] }}</div>
                <div style="font-size:0.7rem; color:var(--text-muted); margin-top:0.25rem;">produk = 15% omset</div>
            </div>
            <div class="card" style="padding:1.5rem; text-align:center; border-left: 5px solid #ef4444; background: rgba(239,68,68,0.05);">
                <div style="font-size:0.75rem; color:var(--text-muted); margin-bottom:0.5rem;">KELAS C</div>
                <div style="font-size:2.5rem; font-weight:800; color:#ef4444;">{{ $paretoKlasses['C'] }}</div>
                <div style="font-size:0.7rem; color:var(--text-muted); margin-top:0.25rem;">produk = 5% omset</div>
            </div>
        </div>
        <div class="card" style="margin-top:1rem; padding:1rem; font-size:0.8rem; color:var(--text-muted);">
            Dari <strong>{{ $totalParetoProducts }} produk</strong> total, hanya <strong>{{ $paretoKlasses['A'] }} produk Kelas A</strong> yang menyumbang 80% dari total omset. Fokuslah pada perlindungan produk Kelas A dan evaluasi produk Kelas C yang berjumlah {{ $paretoKlasses['C'] }} item.
        </div>
    </div>

    <!-- Section 8: Efektivitas Promo -->
    <div class="report-section">
        <h2 class="section-title">8. Efektivitas Promo (Uplift & ROI)</h2>
        <div style="display:grid; grid-template-columns: repeat(3, 1fr); gap: 1.25rem;">
            <div class="card" style="padding:1.5rem; text-align:center; border-left: 5px solid #22c55e; background: rgba(34,197,94,0.05);">
                <div style="font-size:0.75rem; color:var(--text-muted); margin-bottom:0.5rem;">✅ PROMO SUKSES</div>
                <div style="font-size:2.5rem; font-weight:800; color:#22c55e;">{{ $promoSuccessCount }}</div>
                <div style="font-size:0.7rem; color:var(--text-muted); margin-top:0.25rem;">Menghasilkan laba tambahan</div>
            </div>
            <div class="card" style="padding:1.5rem; text-align:center; border-left: 5px solid #ef4444; background: rgba(239,68,68,0.05);">
                <div style="font-size:0.75rem; color:var(--text-muted); margin-bottom:0.5rem;">❌ PROMO GAGAL</div>
                <div style="font-size:2.5rem; font-weight:800; color:#ef4444;">{{ $promoFailCount }}</div>
                <div style="font-size:0.7rem; color:var(--text-muted); margin-top:0.25rem;">Rugi bandar / tidak efektif</div>
            </div>
            <div class="card" style="padding:1.5rem; text-align:center; border-left: 5px solid #f59e0b; background: rgba(245,158,11,0.05);">
                <div style="font-size:0.75rem; color:var(--text-muted); margin-bottom:0.5rem;">💰 TOTAL SUBSIDI</div>
                <div style="font-size:1.4rem; font-weight:800; color:#f59e0b; font-family:monospace; letter-spacing:-0.5px;">Rp {{ number_format($promoTotalSubsidy, 0, ',', '.') }}</div>
                <div style="font-size:0.7rem; color:var(--text-muted); margin-top:0.25rem;">Diskon diberikan saat promo</div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div style="text-align: center; margin-top: 3rem; color: var(--text-muted); font-size: 0.8rem; font-family:monospace;">
        Dibuat secara otomatis oleh DistoraVision Intelligence Engine pada {{ now()->translatedFormat('d F Y H:i:s') }}
    </div>

</div>

<!-- Loading Overlay -->
<div id="loading-overlay" class="no-print">
    <div class="loader-card">
        <div class="spinner-ring"></div>
        <div class="loader-title">Menyiapkan Excel Buku Rapor 360°</div>
        <div class="loader-desc">
            Sistem sedang mengompilasi 10 tab analisis mendalam (KPI, Salesman, Produk, Pareto, RFM, Churn, Trajektori, Promo, dll.).<br>
            <span style="color: var(--accent-yellow); font-weight: bold; display: block; margin-top: 0.5rem;">Proses ini membutuhkan waktu sekitar 10-30 detik. Unduhan akan berjalan otomatis setelah siap.</span>
        </div>
        <button type="button" class="btn-close-loader" onclick="closeLoadingOverlay()">Tutup Overlay</button>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const btnExport = document.getElementById('btn-export-excel');
    if (btnExport) {
        btnExport.addEventListener('click', function(e) {
            // Tampilkan loading overlay
            const overlay = document.getElementById('loading-overlay');
            if (overlay) {
                overlay.style.display = 'flex';
            }
            
            // Auto close after 25 seconds (assuming download starts by then)
            setTimeout(function() {
                closeLoadingOverlay();
            }, 25000);
        });
    }
});

function closeLoadingOverlay() {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) {
        overlay.style.display = 'none';
    }
}
</script>

@endsection
