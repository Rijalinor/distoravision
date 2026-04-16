@extends('layouts.app')
@section('page-title', $salesman->name)
@section('top-bar-actions')
<form method="GET" style="display:flex;gap:0.75rem;align-items:center;">
    @include('components.filter')
</form>
@endsection

@section('content')
<div class="kpi-grid">
    <div class="card kpi-card"><div class="card-header"><span class="card-title">Total Sales</span><div class="kpi-icon green"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8V7m0 1v8m0 0v1"></path></svg></div></div><div class="kpi-value" title="Rp {{ number_format($stats['total_sales'], 0, ',', '.') }}">Rp {{ number_format($stats['total_sales']/1000, 0, ',', '.') }}K</div></div>
    <div class="card kpi-card"><div class="card-header"><span class="card-title">Returns</span><div class="kpi-icon red"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 17h8m0 0V9m0 8l-8-8-4 4-6-6"></path></svg></div></div><div class="kpi-value" style="-webkit-text-fill-color:var(--accent-red);" title="Rp {{ number_format($stats['total_returns'], 0, ',', '.') }}">Rp {{ number_format($stats['total_returns']/1000, 0, ',', '.') }}K</div></div>
    <div class="card kpi-card"><div class="card-header"><span class="card-title">Outlet Coverage</span><div class="kpi-icon blue"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16"></path></svg></div></div><div class="kpi-value">{{ $stats['outlet_count'] }}</div><div class="kpi-label">outlet aktif</div></div>
    <div class="card kpi-card"><div class="card-header"><span class="card-title">Transaksi</span><div class="kpi-icon yellow"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2"></path></svg></div></div><div class="kpi-value">{{ number_format($stats['trx_count']) }}</div><div class="kpi-label">Avg: Rp {{ $stats['trx_count'] > 0 ? number_format($stats['total_sales']/$stats['trx_count'], 0, ',', '.') : 0 }}</div></div>
</div>

<div class="grid-2" style="margin-bottom: 1.5rem;">
    <!-- Personal Target Tracker -->
    <div class="card" style="border-top: 4px solid var(--accent-blue);">
        <div class="card-header"><span class="card-title">Personal Target (S1 Intelligence)</span></div>
        <div style="padding: 1.5rem;">
            <div style="display:flex; justify-content:space-between; margin-bottom: 0.5rem;">
                <span class="text-sm text-muted">Progres Target Bulan Ini</span>
                <span class="text-sm font-bold {{ $targetProgress >= 100 ? 'text-green' : 'text-blue' }}">{{ number_format($targetProgress, 1) }}%</span>
            </div>
            <div style="width:100%; background:var(--bg-darker); border-radius:999px; height:8px; margin-bottom: 1rem; overflow:hidden;">
                <div style="width:{{ min($targetProgress, 100) }}%; background:var(--primary); height:100%; border-radius:999px; transition:width 1s;"></div>
            </div>
            <div style="display: flex; gap: 1rem; font-size: 0.85rem;">
                <div style="flex:1;"><div class="text-muted">Asumsi Target (3Bln)</div><div class="font-bold">Rp {{ number_format($personalTarget, 0, ',', '.') }}</div></div>
                <div style="flex:1;"><div class="text-muted">Shortfall</div><div class="text-red font-bold">Rp {{ number_format($shortfall, 0, ',', '.') }}</div></div>
                <div style="flex:1;"><div class="text-muted">Req. Run Rate / Hari</div><div class="text-yellow font-bold">Rp {{ number_format($dailyRunRateRequired, 0, ',', '.') }}</div></div>
            </div>
        </div>
    </div>

    <!-- Personal Churn & Performance Focus -->
    <div class="card" style="border-top: 4px solid var(--accent-red);">
        <div class="card-header"><span class="card-title">Resiko Toko Churn & Retur</span></div>
        <div style="padding: 1.5rem; display:grid; grid-template-columns: 1fr 1fr; gap:1rem;">
            <div style="text-align:center; padding: 1rem; background:rgba(239,68,68,0.1); border-radius:8px;">
                <div style="font-size: 2rem; font-weight:bold; color:var(--accent-red); line-height:1;">{{ $lostOutletsCount }}</div>
                <div class="text-sm text-muted mt-1">Toko Hilang / Churn</div>
                <div class="text-xs text-red" style="margin-top:0.5rem;" title="Opportunity Loss">Loss: Rp {{ number_format($lostOutletsValue,0,',','.') }}</div>
            </div>
            <div style="text-align:center; padding: 1rem; background:rgba(245,158,11,0.1); border-radius:8px;">
                <div style="font-size: 2rem; font-weight:bold; color:var(--accent-yellow); line-height:1;">{{ number_format($returnRate,1) }}%</div>
                <div class="text-sm text-muted mt-1">Personal Return Rate</div>
                <div class="text-xs text-yellow" style="margin-top:0.5rem;">Batas standar: 2%</div>
            </div>
        </div>
    </div>
</div>

{{-- AR PIUTANG SECTION --}}
@if($arData)
<div class="card" style="margin-bottom:1.5rem;border-top:4px solid var(--accent-yellow);">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
        <span class="card-title">💰 Kondisi Piutang (AR)</span>
        <span class="badge badge-blue" title="Sumber data piutang">{{ $arData['import']->report_date->format('d M Y') }}</span>
    </div>
    <div style="padding:1rem;">
        {{-- KPI Row --}}
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(130px,1fr));gap:0.75rem;margin-bottom:1rem;">
            <div style="text-align:center;padding:0.75rem;background:var(--bg-darker);border-radius:8px;">
                <div style="font-size:0.7rem;color:var(--text-muted);">Total Piutang</div>
                <div class="font-mono" style="font-size:1.1rem;font-weight:700;color:var(--accent-red);" title="Rp {{ number_format($arData['summary']->total_outstanding, 0, ',', '.') }}">
                    Rp {{ number_format($arData['summary']->total_outstanding / 1000, 0, ',', '.') }}K
                </div>
            </div>
            <div style="text-align:center;padding:0.75rem;background:var(--bg-darker);border-radius:8px;">
                <div style="font-size:0.7rem;color:var(--text-muted);">Overdue</div>
                <div class="font-mono" style="font-size:1.1rem;font-weight:700;color:var(--accent-yellow);" title="Rp {{ number_format($arData['summary']->total_overdue, 0, ',', '.') }}">
                    Rp {{ number_format($arData['summary']->total_overdue / 1000, 0, ',', '.') }}K
                </div>
            </div>
            <div style="text-align:center;padding:0.75rem;background:var(--bg-darker);border-radius:8px;">
                <div style="font-size:0.7rem;color:var(--text-muted);">Outlet Berpiutang</div>
                <div class="font-mono" style="font-size:1.1rem;font-weight:700;">{{ $arData['summary']->outlet_count }}</div>
                <div style="font-size:0.65rem;color:var(--text-muted);">{{ $arData['summary']->invoice_count }} invoice</div>
            </div>
            <div style="text-align:center;padding:0.75rem;background:var(--bg-darker);border-radius:8px;">
                <div style="font-size:0.7rem;color:var(--text-muted);">Outlet Bandel (CM≥3)</div>
                <div class="font-mono" style="font-size:1.1rem;font-weight:700;color:{{ $arData['summary']->stubborn_count > 0 ? 'var(--accent-red)' : 'var(--accent-green)' }};">
                    {{ $arData['summary']->stubborn_count }}
                </div>
            </div>
            <div style="text-align:center;padding:0.75rem;background:var(--bg-darker);border-radius:8px;">
                <div style="font-size:0.7rem;color:var(--text-muted);">Rata-rata Overdue</div>
                <div class="font-mono" style="font-size:1.1rem;font-weight:700;">{{ round($arData['summary']->avg_overdue ?? 0) }} hari</div>
            </div>
            <div style="text-align:center;padding:0.75rem;background:var(--bg-darker);border-radius:8px;">
                <div style="font-size:0.7rem;color:var(--text-muted);">Collection Rate</div>
                @php $colRate = $arData['summary']->total_invoiced > 0 ? ($arData['summary']->total_paid / $arData['summary']->total_invoiced * 100) : 0; @endphp
                <div class="font-mono" style="font-size:1.1rem;font-weight:700;color:{{ $colRate > 50 ? 'var(--accent-green)' : 'var(--accent-red)' }};">{{ number_format($colRate, 1) }}%</div>
            </div>
        </div>

        {{-- Outlet Piutang --}}
        @if($arData['topOutlets']->count() > 0)
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.5rem;" id="outletHeader">
            <div style="font-size:0.8rem;font-weight:600;color:var(--text-primary);">🏪 Daftar Outlet Piutang</div>
            @if($arData['topOutlets']->count() > 5)
            <button type="button" onclick="showAllOutlets(this)" class="btn btn-secondary" style="padding:0.2rem 0.6rem;font-size:0.7rem;background:rgba(99,102,241,0.1);color:var(--primary-light);border:1px solid rgba(99,102,241,0.2);">Lihat Semua ({{ $arData['topOutlets']->count() }}) &raquo;</button>
            @endif
        </div>
        <table class="data-table">
            <thead><tr>
                <th>Outlet</th>
                <th class="text-right" title="Sisa tagihan belum dibayar">AR Balance</th>
                <th class="text-right" title="Jumlah invoice">Inv</th>
                <th class="text-right" title="Keterlambatan terlama">Max OD</th>
                <th class="text-right" title="Berapa kali sudah ditagih">CM</th>
            </tr></thead>
            <tbody>
            @foreach($arData['topOutlets'] as $idx => $ao)
            <tr class="{{ $idx >= 5 ? 'hidden-outlet' : '' }}" style="{{ $idx >= 5 ? 'display:none;' : '' }} background:var(--bg-darker);border-top:1px solid var(--border-color);cursor:pointer;transition:background 0.2s;" onmouseover="this.style.background='rgba(255,255,255,0.05)'" onmouseout="this.style.background='var(--bg-darker)'" onclick="toggleInvoices('inv-group-{{ $idx }}', this)">
                <td>
                    <div style="font-weight:600;display:flex;align-items:center;gap:0.4rem;">
                        <span class="toggle-icon" style="font-size:0.6rem;color:var(--text-muted);display:inline-block;transition:transform 0.2s;">▶</span>
                        {{ Str::limit($ao->outlet_name, 22) }}
                    </div>
                    <div style="font-size:0.65rem;color:var(--text-muted);padding-left:1rem;">{{ $ao->outlet_code }}</div>
                </td>
                <td class="text-right font-mono" style="color:var(--accent-red);font-weight:600;">{{ number_format($ao->total_balance, 0, ',', '.') }}</td>
                <td class="text-right font-mono">{{ $ao->inv_count }}</td>
                <td class="text-right">
                    @if($ao->max_overdue > 90)<span class="badge badge-red">{{ $ao->max_overdue }}hr</span>
                    @elseif($ao->max_overdue > 30)<span class="badge badge-yellow">{{ $ao->max_overdue }}hr</span>
                    @else<span class="badge badge-green">{{ $ao->max_overdue }}hr</span>@endif
                </td>
                <td class="text-right"><span class="badge {{ $ao->max_cm >= 3 ? 'badge-red' : 'badge-blue' }}">{{ $ao->max_cm }}x</span></td>
            </tr>
            @if(isset($arData['topInvoices'][$ao->outlet_code]))
                @foreach($arData['topInvoices'][$ao->outlet_code] as $inv)
                <tr class="inv-group-{{ $idx }} {{ $idx >= 5 ? 'hidden-outlet hidden-inv' : '' }}" style="display:none; font-size:0.75rem; background:rgba(0,0,0,0.15);">
                    <td style="padding-left:2.5rem;position:relative;">
                        <span style="position:absolute;left:1.25rem;top:0;bottom:0;width:2px;background:rgba(255,255,255,0.05);"></span>
                        <div style="display:flex;align-items:center;gap:0.5rem;position:relative;">
                            <span style="position:absolute;left:-1.25rem;top:50%;width:10px;height:2px;background:rgba(255,255,255,0.05);"></span>
                            <code style="background:rgba(99,102,241,0.1);color:var(--primary-light);padding:0.1rem 0.3rem;border-radius:3px;font-size:0.65rem;">{{ $inv->pfi_sn }}</code>
                        </div>
                        <div style="color:var(--text-muted);font-size:0.65rem;margin-top:0.2rem;position:relative;">Tgl: {{ $inv->doc_date?->format('d M Y') }}</div>
                    </td>
                    <td class="text-right font-mono text-muted" style="font-size:0.7rem;">{{ number_format($inv->ar_balance, 0, ',', '.') }}</td>
                    <td class="text-right text-muted">-</td>
                    <td class="text-right">
                        @if($inv->overdue_days > 90)<span class="badge badge-red" style="font-size:0.6rem;padding:0.1rem 0.3rem;">{{ $inv->overdue_days }}hr</span>
                        @elseif($inv->overdue_days > 30)<span class="badge badge-yellow" style="font-size:0.6rem;padding:0.1rem 0.3rem;">{{ $inv->overdue_days }}hr</span>
                        @elseif($inv->overdue_days > 0)<span class="badge badge-blue" style="font-size:0.6rem;padding:0.1rem 0.3rem;">{{ $inv->overdue_days }}hr</span>
                        @else<span class="badge badge-green" style="font-size:0.6rem;padding:0.1rem 0.3rem;">Cur</span>@endif
                    </td>
                    <td class="text-right"><span class="badge {{ $inv->cm >= 3 ? 'badge-red' : 'badge-blue' }}" style="font-size:0.6rem;padding:0.1rem 0.3rem;">{{ $inv->cm }}x</span></td>
                </tr>
                @endforeach
            @endif
            @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>
@endif

<script>
function toggleInvoices(className, row) {
    const rows = document.querySelectorAll('.' + className + ':not(.hidden-inv)');
    const rowsAll = document.querySelectorAll('.' + className);
    const icon = row.querySelector('.toggle-icon');
    
    // Check current state based on first visible or completely hidden
    let isCurrentlyHidden = true;
    for(let r of rowsAll) {
        if(r.style.display !== 'none' && !r.classList.contains('hidden-inv')) {
            isCurrentlyHidden = false;
            break;
        }
    }
    
    if(rowsAll.length > 0) {
        rowsAll.forEach(r => {
            if(!r.classList.contains('hidden-inv')) {
                r.style.display = isCurrentlyHidden ? 'table-row' : 'none';
            }
        });
        icon.style.transform = isCurrentlyHidden ? 'rotate(90deg)' : 'rotate(0deg)';
        icon.style.color = isCurrentlyHidden ? 'var(--primary)' : 'var(--text-muted)';
    }
}

function showAllOutlets(btn) {
    document.querySelectorAll('.hidden-outlet').forEach(el => {
        el.style.display = el.tagName === 'TR' && !el.classList.contains('inv-group') ? 'table-row' : 'none';
        
        // Remove hidden classes so toggleInvoices works normally
        el.classList.remove('hidden-outlet');
        if(el.classList.contains('hidden-inv')) {
            el.classList.remove('hidden-inv');
            // Keep it hidden (display:none) because it's an invoice row, it should only show when parent is clicked
            el.style.display = 'none'; 
        }
    });
    btn.style.display = 'none';
}
</script>

<div class="chart-grid">
    <div class="card"><div class="card-header"><span class="card-title">Trend Mingguan</span></div><div id="weeklyChart"></div></div>
</div>

<div class="grid-2">
    <div class="card"><div class="card-header"><span class="card-title">Top Produk</span></div><table class="data-table"><thead><tr><th>#</th><th>Produk</th><th class="text-right">Qty</th><th class="text-right">Sales</th></tr></thead><tbody>@foreach($topProducts as $i=>$p)<tr><td>{{$i+1}}</td><td>{{Str::limit($p->name,30)}}</td><td class="text-right font-mono">{{number_format($p->qty)}}</td><td class="text-right font-mono">Rp {{number_format($p->total,0,',','.')}}</td></tr>@endforeach</tbody></table></div>
    <div class="card"><div class="card-header"><span class="card-title">Top Outlet</span></div><table class="data-table"><thead><tr><th>#</th><th>Outlet</th><th>Kota</th><th class="text-right">Sales</th></tr></thead><tbody>@foreach($topOutlets as $i=>$o)<tr><td>{{$i+1}}</td><td>{{Str::limit($o->name,25)}}</td><td><span class="badge badge-blue">{{$o->city}}</span></td><td class="text-right font-mono">Rp {{number_format($o->total,0,',','.')}}</td></tr>@endforeach</tbody></table></div>
</div>

<div style="margin-top:1.5rem;"><a href="{{ route('salesmen.index', ['period'=>$period]) }}" class="btn btn-secondary">← Kembali ke Ranking</a></div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    var wd=@json($weeklyData); var weeks=Object.keys(wd).sort();
    var sales=weeks.map(w=>{var f=wd[w].find(d=>d.type==='I');return f?parseFloat(f.total):0;});
    var ret=weeks.map(w=>{var f=wd[w].find(d=>d.type==='R');return f?parseFloat(f.total):0;});
    new ApexCharts(document.querySelector("#weeklyChart"),{chart:{type:'bar',height:280,toolbar:{show:false},background:'transparent'},series:[{name:'Sales',data:sales},{name:'Returns',data:ret}],xaxis:{categories:weeks.map(w=>'W'+w)},colors:['#6366f1','#ef4444'],theme:{mode:'dark'},grid:{borderColor:'#334155'},dataLabels:{enabled:false},plotOptions:{bar:{borderRadius:4}},yaxis:{labels:{formatter:v=>'Rp '+(v/1000).toFixed(0)+'K'}},tooltip:{y:{formatter:v=>'Rp '+new Intl.NumberFormat('id-ID').format(v)}}}).render();
});
</script>
@endsection
