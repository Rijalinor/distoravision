@extends('layouts.app')
@section('page-title', 'My Dashboard')

@section('top-bar-actions')
<form method="GET" style="display:flex;gap:0.75rem;align-items:center;">
    <select name="period" class="period-select" onchange="this.form.submit()">
        @foreach($periods as $p)
            <option value="{{ $p }}" {{ $p === $period ? 'selected' : '' }}>{{ \Carbon\Carbon::parse($p.'-01')->translatedFormat('F Y') }}</option>
        @endforeach
    </select>
</form>
@endsection

@section('content')
{{-- ═══ GREETING BANNER ═══ --}}
<div class="card" style="margin-bottom:1.5rem; background: linear-gradient(135deg, rgba(99,102,241,0.15), rgba(168,85,247,0.1)); border: 1px solid rgba(99,102,241,0.3);">
    <div style="display:flex; align-items:center; gap:1rem;">
        <div style="width:48px;height:48px;min-width:48px;min-height:48px;flex-shrink:0;border-radius:50%;background:linear-gradient(135deg,var(--primary),#a855f7);display:flex;align-items:center;justify-content:center;font-size:20px;font-weight:800;color:white;">
            {{ strtoupper(substr($salesman->name, 0, 1)) }}
        </div>
        <div>
            <h2 style="font-size:1.15rem;font-weight:700;margin:0;">{{ $greeting }}, {{ $salesman->name }}! 👋</h2>
            <p style="color:var(--text-muted);font-size:0.8rem;margin:0;">
                Dashboard kinerja Anda — {{ \Carbon\Carbon::parse($period.'-01')->translatedFormat('F Y') }}
                @if($momGrowth != 0)
                    · <span class="{{ $momGrowth >= 0 ? 'text-green' : 'text-red' }}">{{ $momGrowth >= 0 ? '📈' : '📉' }} {{ number_format(abs($momGrowth), 1) }}% vs bulan lalu</span>
                @endif
            </p>
        </div>
    </div>
</div>

{{-- ═══ KPI CARDS ═══ --}}
<div class="kpi-grid">
    <div class="card kpi-card">
        <div class="card-header">
            <span class="card-title">Total Sales</span>
            <div class="kpi-icon green"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg></div>
        </div>
        <div class="kpi-value">Rp {{ number_format($totalSales / 1000, 0, ',', '.') }}K</div>
        <div class="kpi-label">{{ number_format($invoiceCount) }} invoice · {{ number_format($outletCount) }} outlet</div>
    </div>
    <div class="card kpi-card">
        <div class="card-header">
            <span class="card-title">Total Returns</span>
            <div class="kpi-icon red"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path></svg></div>
        </div>
        <div class="kpi-value" style="-webkit-text-fill-color:var(--accent-red);">Rp {{ number_format($totalReturns / 1000, 0, ',', '.') }}K</div>
        <div class="kpi-label">Return Rate: <span class="{{ $returnRate > 5 ? 'text-red' : 'text-green' }}">{{ number_format($returnRate, 1) }}%</span></div>
    </div>
    <div class="card kpi-card">
        <div class="card-header">
            <span class="card-title">Net Sales</span>
            <div class="kpi-icon blue"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></div>
        </div>
        <div class="kpi-value">Rp {{ number_format($netSales / 1000, 0, ',', '.') }}K</div>
        <div class="kpi-label">
            @if($momGrowth >= 0)<span class="text-green">▲ {{ number_format($momGrowth, 1) }}%</span>
            @else<span class="text-red">▼ {{ number_format(abs($momGrowth), 1) }}%</span>@endif
            vs bulan lalu
        </div>
    </div>
    <div class="card kpi-card">
        <div class="card-header">
            <span class="card-title">Toko Hilang</span>
            <div class="kpi-icon yellow"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path></svg></div>
        </div>
        <div class="kpi-value" style="-webkit-text-fill-color:{{ $sleepingCount > 0 ? 'var(--accent-red)' : 'var(--accent-green)' }};">{{ $sleepingCount }}</div>
        <div class="kpi-label">Potensi loss: Rp {{ number_format($sleepingValue / 1000, 0, ',', '.') }}K</div>
    </div>
</div>

{{-- ═══ TARGET & WEEKLY TREND ═══ --}}
<div class="grid-2" style="margin-bottom:1.5rem;">
    {{-- Target Progress --}}
    <div class="card" style="border-top:4px solid var(--accent-blue);">
        <div class="card-header"><span class="card-title">🎯 Target & Run Rate</span></div>
        <div style="padding:0.5rem 0;">
            <div style="display:flex;justify-content:space-between;margin-bottom:0.5rem;">
                <span style="font-size:0.8rem;color:var(--text-muted);">Progress ke target</span>
                <span style="font-size:0.85rem;font-weight:700;color:{{ $targetProgress >= 100 ? 'var(--accent-green)' : ($targetProgress >= 70 ? 'var(--accent-yellow)' : 'var(--accent-red)') }};">{{ number_format($targetProgress, 1) }}%</span>
            </div>
            <div style="width:100%;height:12px;background:var(--bg-darker);border-radius:6px;overflow:hidden;margin-bottom:1rem;">
                <div style="width:{{ min($targetProgress, 100) }}%;height:100%;background:linear-gradient(90deg,var(--primary),#a855f7);border-radius:6px;transition:width 0.5s;"></div>
            </div>
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:0.75rem;text-align:center;">
                <div>
                    <div style="font-size:0.65rem;color:var(--text-muted);text-transform:uppercase;">Target</div>
                    <div class="font-mono font-bold" style="font-size:0.95rem;">Rp {{ number_format($targetValue / 1000, 0, ',', '.') }}K</div>
                </div>
                <div>
                    <div style="font-size:0.65rem;color:var(--text-muted);text-transform:uppercase;">Gap</div>
                    <div class="font-mono font-bold text-red" style="font-size:0.95rem;">Rp {{ number_format($gap / 1000, 0, ',', '.') }}K</div>
                </div>
                <div>
                    <div style="font-size:0.65rem;color:var(--text-muted);text-transform:uppercase;">Run Rate/Hari</div>
                    <div class="font-mono font-bold text-yellow" style="font-size:0.95rem;">Rp {{ number_format($dailyRunRate / 1000, 0, ',', '.') }}K</div>
                </div>
            </div>
            <p style="font-size:0.72rem;color:var(--text-muted);margin-top:0.75rem;text-align:center;">Sisa {{ $remainingDays }} hari kerja</p>
        </div>
    </div>

    {{-- Weekly Trend Chart --}}
    <div class="card" style="border-top:4px solid var(--accent-green);">
        <div class="card-header"><span class="card-title">📊 Tren Mingguan</span></div>
        <div id="weeklyChart" style="height:200px;"></div>
    </div>
</div>

{{-- ═══ PIUTANG (AR) ═══ --}}
@if($arData)
<div class="card" style="margin-bottom:1.5rem;border-top:4px solid var(--accent-yellow);">
    <div class="card-header" style="justify-content:flex-start; gap:1rem;">
        <span class="card-title">💰 Kondisi Piutang Saya</span>
        <span class="badge badge-blue">{{ $arData['import']->report_date->format('d M Y') }}</span>
        <div style="flex-grow:1;"></div>
        <a href="{{ route('ar.dashboard') }}" class="btn btn-secondary" style="font-size:0.75rem; padding:0.4rem 0.8rem;">Lihat Semua Detail &rarr;</a>
    </div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:0.75rem;margin-bottom:1rem;">
        <div style="text-align:center;padding:0.75rem;background:var(--bg-darker);border-radius:8px;">
            <div style="font-size:0.7rem;color:var(--text-muted);">Outstanding</div>
            <div class="font-mono font-bold text-red" style="font-size:1.1rem;">Rp {{ number_format($arData['summary']->total_outstanding / 1000, 0, ',', '.') }}K</div>
        </div>
        <div style="text-align:center;padding:0.75rem;background:var(--bg-darker);border-radius:8px;">
            <div style="font-size:0.7rem;color:var(--text-muted);">Overdue</div>
            <div class="font-mono font-bold text-yellow" style="font-size:1.1rem;">Rp {{ number_format($arData['summary']->total_overdue / 1000, 0, ',', '.') }}K</div>
        </div>
        <div style="text-align:center;padding:0.75rem;background:var(--bg-darker);border-radius:8px;">
            <div style="font-size:0.7rem;color:var(--text-muted);">Outlet</div>
            <div class="font-mono font-bold" style="font-size:1.1rem;">{{ $arData['summary']->outlet_count }}</div>
        </div>
        <div style="text-align:center;padding:0.75rem;background:var(--bg-darker);border-radius:8px;">
            <div style="font-size:0.7rem;color:var(--text-muted);">Max Overdue</div>
            <div class="font-mono font-bold" style="font-size:1.1rem;color:{{ ($arData['summary']->max_overdue ?? 0) > 60 ? 'var(--accent-red)' : 'var(--accent-yellow)' }};">{{ $arData['summary']->max_overdue ?? 0 }} hari</div>
        </div>
    </div>

    @if($arData['topOutlets']->count())
    <table class="data-table" style="table-layout: auto;">
        <thead><tr><th>Outlet</th><th class="text-right">AR Balance</th><th class="text-right">Invoice</th><th class="text-right">Overdue</th><th style="width:30px;"></th></tr></thead>
        <tbody>
        @foreach($arData['topOutlets'] as $ao)
        <tr onclick="var el = document.getElementById('inv-{{ md5($ao->outlet_code) }}'); el.style.display = el.style.display === 'none' ? 'table-row' : 'none';" style="cursor:pointer;" title="Klik untuk lihat rincian faktur">
            <td>{{ Str::limit($ao->outlet_name, 30) }} <span class="badge badge-blue" style="font-size:0.6rem;">{{ $ao->outlet_code }}</span></td>
            <td class="text-right font-mono">Rp {{ number_format($ao->total_balance, 0, ',', '.') }}</td>
            <td class="text-right">{{ $ao->inv_count }}</td>
            <td class="text-right"><span class="badge {{ $ao->max_overdue > 60 ? 'badge-red' : ($ao->max_overdue > 30 ? 'badge-yellow' : 'badge-green') }}">{{ $ao->max_overdue }} hari</span></td>
            <td class="text-right" style="color:var(--text-muted);"><svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg></td>
        </tr>
        <tr id="inv-{{ md5($ao->outlet_code) }}" style="display:none; background:rgba(0,0,0,0.2);">
            <td colspan="5" style="padding:0;">
                <div style="padding:0.75rem 1rem 0.75rem 2rem; border-left:3px solid var(--accent-blue);">
                    <div style="font-size:0.75rem; color:var(--text-muted); margin-bottom:0.5rem; text-transform:uppercase; font-weight:600;">Rincian Faktur:</div>
                    <table style="width:100%; border-collapse:collapse;">
                        @foreach($arData['topInvoices'][$ao->outlet_code] ?? [] as $inv)
                        <tr>
                            <td style="padding:0.35rem 0; font-size:0.8rem; border-bottom:1px solid rgba(255,255,255,0.05); color:var(--text-secondary);"><span class="badge badge-blue" style="font-size:0.65rem; background:rgba(59,130,246,0.1); border:1px solid rgba(59,130,246,0.3);">{{ $inv->pfi_sn ?: $inv->faktur_no }}</span></td>
                            <td style="padding:0.35rem 0; font-size:0.8rem; border-bottom:1px solid rgba(255,255,255,0.05); color:var(--text-secondary);">{{ $inv->invoice_date ? \Carbon\Carbon::parse($inv->invoice_date)->format('d/m/Y') : '-' }}</td>
                            <td class="text-right font-mono" style="padding:0.35rem 0; font-size:0.85rem; font-weight:600; border-bottom:1px solid rgba(255,255,255,0.05);">Rp {{ number_format($inv->ar_balance, 0, ',', '.') }}</td>
                            <td class="text-right" style="padding:0.35rem 0; font-size:0.8rem; border-bottom:1px solid rgba(255,255,255,0.05);"><span class="{{ $inv->overdue_days > 30 ? 'text-red' : 'text-yellow' }}">{{ $inv->overdue_days }}d</span></td>
                        </tr>
                        @endforeach
                    </table>
                </div>
            </td>
        </tr>
        @endforeach
        </tbody>
    </table>
    @endif
</div>

@if(count($arData['criticalInvoices'] ?? []) > 0)
<div class="card" style="margin-bottom:1.5rem;border-top:4px solid var(--accent-red);">
    <div class="card-header">
        <span class="card-title">🚨 Piutang Macet / Kritis (> 60 Hari)</span>
        <span class="badge badge-red">{{ count($arData['criticalInvoices']) }} Faktur</span>
    </div>
    <p style="font-size:0.8rem;color:var(--text-muted);margin-bottom:0.75rem;">
        Faktur-faktur ini sudah sangat lama tidak terbayar dan membutuhkan atensi khusus untuk segera ditagih.
    </p>
    <table class="data-table" style="table-layout: auto;">
        <thead><tr><th>Outlet</th><th>Faktur</th><th class="text-right">Balance</th><th class="text-right">Overdue</th></tr></thead>
        <tbody>
        @foreach($arData['criticalInvoices'] as $cv)
        <tr>
            <td>{{ Str::limit($cv->outlet_name, 25) }}</td>
            <td><span class="badge badge-blue" style="font-size:0.6rem;">{{ $cv->pfi_sn ?: $cv->faktur_no }}</span></td>
            <td class="text-right font-mono text-red font-bold">Rp {{ number_format($cv->ar_balance, 0, ',', '.') }}</td>
            <td class="text-right"><span class="badge badge-red">{{ $cv->overdue_days }} hari</span></td>
        </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif

@endif

{{-- ═══ TOP PRODUCTS & OUTLETS ═══ --}}
<div class="grid-2" style="margin-bottom:1.5rem;">
    <div class="card">
        <div class="card-header"><span class="card-title">🏆 Top 10 Produk Saya</span></div>
        @if($topProducts->count())
        <table class="data-table">
            <thead><tr><th>#</th><th>Produk</th><th class="text-right">Sales</th><th class="text-right">Qty</th></tr></thead>
            <tbody>
            @foreach($topProducts as $i => $p)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ Str::limit($p->name, 30) }}</td>
                <td class="text-right font-mono">Rp {{ number_format($p->total_sales, 0, ',', '.') }}</td>
                <td class="text-right">{{ number_format($p->total_qty) }}</td>
            </tr>
            @endforeach
            </tbody>
        </table>
        @else
            <p style="text-align:center;color:var(--text-muted);padding:2rem;">Belum ada transaksi</p>
        @endif
    </div>

    <div class="card">
        <div class="card-header"><span class="card-title">🏪 Top 10 Outlet Saya</span></div>
        @if($topOutlets->count())
        <table class="data-table">
            <thead><tr><th>#</th><th>Outlet</th><th>Kota</th><th class="text-right">Sales</th></tr></thead>
            <tbody>
            @foreach($topOutlets as $i => $o)
            <tr>
                <td>{{ $i + 1 }}</td>
                <td>{{ Str::limit($o->name, 22) }}</td>
                <td><span class="badge badge-blue">{{ $o->city ?? '-' }}</span></td>
                <td class="text-right font-mono">Rp {{ number_format($o->total_sales, 0, ',', '.') }}</td>
            </tr>
            @endforeach
            </tbody>
        </table>
        @else
            <p style="text-align:center;color:var(--text-muted);padding:2rem;">Belum ada transaksi</p>
        @endif
    </div>
</div>

{{-- ═══ SLEEPING OUTLETS ═══ --}}
@if($sleepingCount > 0)
<div class="card" style="margin-bottom:1.5rem;border-top:4px solid var(--accent-red);">
    <div class="card-header">
        <span class="card-title">⚠️ Toko Hilang (vs Bulan Lalu)</span>
        <span class="badge badge-red">{{ $sleepingCount }} outlet</span>
    </div>
    <p style="font-size:0.8rem;color:var(--text-muted);margin-bottom:0.75rem;">
        Outlet ini aktif bulan lalu tapi tidak ada transaksi bulan ini. Potensi kehilangan <span class="text-red font-bold">Rp {{ number_format($sleepingValue / 1000, 0, ',', '.') }}K</span>.
    </p>
    <table class="data-table">
        <thead><tr><th>Outlet</th><th>Kota</th><th class="text-right">Sales Bulan Lalu</th></tr></thead>
        <tbody>
        @foreach($sleepingOutlets as $so)
        <tr>
            <td>{{ Str::limit($so->name, 30) }}</td>
            <td><span class="badge badge-blue">{{ $so->city ?? '-' }}</span></td>
            <td class="text-right font-mono text-red">Rp {{ number_format($so->last_month_sales, 0, ',', '.') }}</td>
        </tr>
        @endforeach
        </tbody>
    </table>
</div>
@endif

{{-- ═══ RECENT TRANSACTIONS ═══ --}}
<div class="card">
    <div class="card-header"><span class="card-title">📋 Riwayat Transaksi Terbaru</span></div>
    @if($recentTransactions->count())
    <table class="data-table">
        <thead><tr><th>Tanggal</th><th>Tipe</th><th>Outlet</th><th>Produk</th><th class="text-right">Qty</th><th class="text-right">Nilai</th></tr></thead>
        <tbody>
        @foreach($recentTransactions as $t)
        <tr>
            <td>{{ $t->so_date ? \Carbon\Carbon::parse($t->so_date)->format('d/m') : '-' }}</td>
            <td>
                @if($t->type === 'I')<span class="badge badge-green">Invoice</span>
                @else<span class="badge badge-red">Return</span>@endif
            </td>
            <td>{{ Str::limit($t->outlet_name, 20) }}</td>
            <td>{{ Str::limit($t->product_name, 22) }}</td>
            <td class="text-right">{{ number_format(abs($t->qty_base)) }}</td>
            <td class="text-right font-mono {{ $t->type === 'R' ? 'text-red' : '' }}">Rp {{ number_format(abs($t->taxed_amt), 0, ',', '.') }}</td>
        </tr>
        @endforeach
        </tbody>
    </table>
    @else
        <p style="text-align:center;color:var(--text-muted);padding:2rem;">Belum ada transaksi di periode ini</p>
    @endif
</div>

{{-- ═══ WEEKLY CHART JS ═══ --}}
<script>
var weeklyData = @json($weeklyTrend);
var categories = [], salesData = [], returnData = [];
for (var w in weeklyData) {
    categories.push('W' + w);
    var sales = 0, ret = 0;
    weeklyData[w].forEach(function(item) {
        if (item.type === 'I') sales = item.total;
        else ret = item.total;
    });
    salesData.push(sales);
    returnData.push(ret);
}
if (typeof ApexCharts !== 'undefined') {
    new ApexCharts(document.querySelector("#weeklyChart"), {
        chart: { type: 'bar', height: 200, stacked: false, toolbar: { show: false },
            background: 'transparent', foreColor: '#94a3b8' },
        dataLabels: { enabled: false },
        series: [
            { name: 'Sales', data: salesData },
            { name: 'Returns', data: returnData }
        ],
        colors: ['#10b981', '#ef4444'],
        plotOptions: { bar: { borderRadius: 4, columnWidth: '50%' } },
        xaxis: { categories: categories },
        yaxis: { labels: { formatter: v => 'Rp ' + (v/1000000).toFixed(1) + 'M' } },
        grid: { borderColor: '#334155', strokeDashArray: 3 },
        legend: { position: 'top', horizontalAlign: 'right' },
        tooltip: { theme: 'dark', y: { formatter: v => 'Rp ' + new Intl.NumberFormat('id-ID').format(v) } }
    }).render();
}
</script>
@endsection
