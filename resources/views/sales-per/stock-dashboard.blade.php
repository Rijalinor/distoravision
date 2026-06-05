@extends('layouts.app')
@section('page-title', 'Stok Gudang — Analisis')

@section('content')

@include('components.inventory-tabs')

@if(!$hasData)
<div class="card" style="text-align:center;padding:4rem 2rem;">
    <div style="font-size:3rem;margin-bottom:1rem;">📦</div>
    <h2 style="font-size:1.2rem;margin-bottom:0.5rem;">Belum Ada Data Stok</h2>
    <p style="color:var(--text-muted);margin-bottom:1.5rem;">Upload file Excel "Sales Per" yang berisi sheet stok gudang untuk mulai analisis.</p>
    <a href="{{ route('sales-per.imports.create') }}" class="btn btn-primary">Upload File Sales Per</a>
</div>
@else

{{-- FILTER --}}
<div class="card" style="margin-bottom:1.5rem;padding:0.75rem 1.25rem;">
    <form method="GET" style="display:flex;gap:0.75rem;align-items:center;flex-wrap:wrap;">
        <select name="period" class="period-select" onchange="this.form.submit()">
            @foreach($periods as $p)
                <option value="{{ $p }}" {{ $p === $period ? 'selected' : '' }}>{{ \Carbon\Carbon::parse($p.'-01')->translatedFormat('F Y') }}</option>
            @endforeach
        </select>
        <select name="warehouse" class="period-select" style="max-width:200px;" onchange="this.form.submit()">
            <option value="all">🏢 Semua Gudang</option>
            @foreach($warehouseList as $wh)
                <option value="{{ $wh }}" {{ $selectedWarehouse === $wh ? 'selected' : '' }}>{{ $wh }}</option>
            @endforeach
        </select>
        <select name="principal" class="period-select" style="max-width:220px;" onchange="this.form.submit()">
            <option value="all">Semua Principal</option>
            @foreach($principalList as $pr)
                <option value="{{ $pr }}" {{ $selectedPrincipal === $pr ? 'selected' : '' }}>{{ Str::limit(str_replace('PT. ', '', $pr), 25) }}</option>
            @endforeach
        </select>
    </form>
</div>

{{-- HEADER --}}
<div class="card" style="margin-bottom:1.5rem;background:linear-gradient(135deg,rgba(168,85,247,0.15),rgba(59,130,246,0.1));border:1px solid rgba(168,85,247,0.3);">
    <div style="display:flex;align-items:center;gap:1rem;">
        <div style="width:48px;height:48px;min-width:48px;border-radius:12px;background:linear-gradient(135deg,#a855f7,#3b82f6);display:flex;align-items:center;justify-content:center;font-size:22px;">📦</div>
        <div>
            <h2 style="font-size:1.15rem;font-weight:700;margin:0;">Analisis Stok Gudang — {{ $warehouseLabel }} · {{ $periodLabel }}</h2>
            <p style="color:var(--text-muted);font-size:0.8rem;margin:0;">Monitoring stok dan coverage produk
                @if($selectedPrincipal && $selectedPrincipal !== 'all') · <span class="text-blue font-bold">{{ Str::limit(str_replace('PT. ', '', $selectedPrincipal), 30) }}</span>@endif
            </p>
        </div>
    </div>
</div>

{{-- TAB NAVIGATION --}}
<div class="tabs-container" style="margin-bottom:1.5rem;display:flex;gap:0.5rem;border-bottom:1px solid rgba(255,255,255,0.1);padding-bottom:0.5rem;overflow-x:auto;">
    <button class="btn btn-primary tab-btn active" onclick="switchTab('ringkasan', this)" style="white-space:nowrap;">📊 Ringkasan Eksekutif</button>
    <button class="btn btn-secondary tab-btn" onclick="switchTab('kritis', this)" style="white-space:nowrap;">🚨 Data Kritis ({{ $criticalLow }})</button>
    <button class="btn btn-secondary tab-btn" onclick="switchTab('tertahan', this)" style="white-space:nowrap;">🛑 Modal Tertahan ({{ $slowMovingCount }})</button>
    <button class="btn btn-secondary tab-btn" onclick="switchTab('semua', this)" style="white-space:nowrap;">📦 Semua Data Stok ({{ number_format($totalSKU) }})</button>
</div>

{{-- TAB 1: RINGKASAN EKSEKUTIF --}}
<div id="tab-ringkasan" class="tab-content active" style="display:block;">
    {{-- KPI CARDS --}}
    <div class="kpi-grid">
        <div class="card kpi-card"><div class="card-header"><span class="card-title">Total SKU</span><div class="kpi-icon blue"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg></div></div><div class="kpi-value">{{ number_format($totalSKU) }}</div>
        <div class="kpi-label" style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.5rem;">
            <span>Stok: {{ number_format($totalOnHand) }} unit</span>
            @if($hasPrevPeriod && $trends['sku'])
                @php $t = $trends['sku']; @endphp
                <span style="color: {{ $t['is_good'] ? 'var(--accent-green)' : 'var(--accent-red)' }}; font-weight: bold; font-size: 0.7rem; background: rgba({{ $t['is_good'] ? '16, 185, 129' : '239, 68, 68' }}, 0.1); padding: 2px 6px; border-radius: 4px;" title="Dibanding bulan sebelumnya">
                    {!! $t['dir'] === 'up' ? '↗' : ($t['dir'] === 'down' ? '↘' : '→') !!} {{ $t['pct'] }}%
                </span>
            @endif
        </div></div>

        <div class="card kpi-card"><div class="card-header"><span class="card-title">Nilai Stok</span><div class="kpi-icon green"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg></div></div><div class="kpi-value" title="Rp {{ number_format($totalStockValue, 0, ',', '.') }}">Rp {{ number_format($totalStockValue / 1000000, 1, ',', '.') }}Jt</div>
        <div class="kpi-label" style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.5rem;">
            <span>Modal tertanam</span>
            @if($hasPrevPeriod && $trends['value'])
                @php $t = $trends['value']; @endphp
                <span style="color: {{ $t['is_good'] ? 'var(--accent-green)' : 'var(--accent-red)' }}; font-weight: bold; font-size: 0.7rem; background: rgba({{ $t['is_good'] ? '16, 185, 129' : '239, 68, 68' }}, 0.1); padding: 2px 6px; border-radius: 4px;" title="Dibanding bulan sebelumnya">
                    {!! $t['dir'] === 'up' ? '↗' : ($t['dir'] === 'down' ? '↘' : '→') !!} {{ $t['pct'] }}%
                </span>
            @endif
        </div></div>

        <div class="card kpi-card"><div class="card-header"><span class="card-title">⚠️ Stok Kritis</span><div class="kpi-icon red"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.999L13.732 4.001c-.77-1.333-2.694-1.333-3.464 0L3.34 16.001C2.57 17.333 3.532 19 5.072 19z"></path></svg></div></div><div class="kpi-value" style="-webkit-text-fill-color:{{ $criticalLow > 0 ? 'var(--accent-red)' : 'var(--accent-green)' }};">{{ $criticalLow }}</div>
        <div class="kpi-label" style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.5rem;">
            <span>SWC ≤ 2 minggu</span>
            @if($hasPrevPeriod && $trends['critical'])
                @php $t = $trends['critical']; @endphp
                <span style="color: {{ $t['is_good'] ? 'var(--accent-green)' : 'var(--accent-red)' }}; font-weight: bold; font-size: 0.7rem; background: rgba({{ $t['is_good'] ? '16, 185, 129' : '239, 68, 68' }}, 0.1); padding: 2px 6px; border-radius: 4px;" title="Dibanding bulan sebelumnya">
                    {!! $t['dir'] === 'up' ? '↗' : ($t['dir'] === 'down' ? '↘' : '→') !!} {{ $t['pct'] }}%
                </span>
            @endif
        </div></div>

        <div class="card kpi-card"><div class="card-header"><span class="card-title">🛑 Modal Tertahan</span><div class="kpi-icon red"><svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.999L13.732 4.001c-.77-1.333-2.694-1.333-3.464 0L3.34 16.001C2.57 17.333 3.532 19 5.072 19z"></path></svg></div></div><div class="kpi-value" style="-webkit-text-fill-color:{{ $slowMovingCount > 0 ? 'var(--accent-red)' : 'var(--accent-green)' }};">{{ $slowMovingCount }}</div>
        <div class="kpi-label" style="display: flex; justify-content: space-between; align-items: center; margin-top: 0.5rem;">
            <span>SWC > 8 mgg / mati</span>
            @if($hasPrevPeriod && $trends['slow'])
                @php $t = $trends['slow']; @endphp
                <span style="color: {{ $t['is_good'] ? 'var(--accent-green)' : 'var(--accent-red)' }}; font-weight: bold; font-size: 0.7rem; background: rgba({{ $t['is_good'] ? '16, 185, 129' : '239, 68, 68' }}, 0.1); padding: 2px 6px; border-radius: 4px;" title="Dibanding bulan sebelumnya">
                    {!! $t['dir'] === 'up' ? '↗' : ($t['dir'] === 'down' ? '↘' : '→') !!} {{ $t['pct'] }}%
                </span>
            @endif
        </div></div>
    </div>

    {{-- PARETO CAPITAL ALLOCATION (80/20 RULE) --}}
    <div class="card" style="margin-bottom:1.5rem; border-left: 4px solid {{ $paretoCapital['is_healthy'] ? 'var(--accent-green)' : 'var(--accent-red)' }}; padding: 1.5rem;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
            <div>
                <h3 style="margin:0; font-size:1rem; display:flex; align-items:center; gap:0.5rem;">
                    🎯 Alokasi Modal Stok (Aturan 80/20 Bos)
                    @if($paretoCapital['is_healthy'])
                        <span class="badge badge-green" style="font-size:0.7rem;">SEHAT</span>
                    @else
                        <span class="badge badge-red" style="font-size:0.7rem;">TIDAK SEHAT</span>
                    @endif
                </h3>
                <p style="margin:0.2rem 0 0 0; font-size:0.75rem; color:var(--text-muted);">Target: 80% dari total nilai stok (uang) harus tertanam di produk Fast-Moving (SWC 1-8 minggu).</p>
            </div>
            <div style="text-align:right;">
                <div style="font-size:1.5rem; font-weight:800; font-family:monospace; color:{{ $paretoCapital['is_healthy'] ? 'var(--accent-green)' : 'var(--accent-red)' }};">{{ number_format($paretoCapital['fast_pct'], 1) }}%</div>
                <div style="font-size:0.7rem; color:var(--text-muted);">Tercapai di Fast-Moving</div>
            </div>
        </div>
        
        <div style="height: 24px; background: rgba(255,255,255,0.05); border-radius: 12px; overflow: hidden; display: flex; box-shadow: inset 0 2px 4px rgba(0,0,0,0.2);">
            <div style="width: {{ $paretoCapital['fast_pct'] }}%; background: linear-gradient(90deg, #10b981, #059669); display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: bold; color: white;">
                {{ number_format($paretoCapital['fast_pct'], 1) }}% Fast-Moving
            </div>
            <div style="width: {{ 100 - $paretoCapital['fast_pct'] }}%; background: linear-gradient(90deg, #ef4444, #b91c1c); display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: bold; color: white;">
                {{ number_format(100 - $paretoCapital['fast_pct'], 1) }}% Slow-Moving
            </div>
        </div>
        
        <div style="display:flex; justify-content:space-between; margin-top: 0.5rem; font-size: 0.75rem; font-family:monospace; font-weight: 600;">
            <span style="color:var(--accent-green);">Rp {{ number_format($paretoCapital['fast_value'] / 1000000, 1, ',', '.') }} Juta (Cepat Jadi Uang)</span>
            <span style="color:var(--accent-red);">Rp {{ number_format($paretoCapital['slow_value'] / 1000000, 1, ',', '.') }} Juta (Uang Tertahan)</span>
        </div>
    </div>

    {{-- EXTRA METRICS --}}
    <div class="grid-2" style="margin-bottom:1.5rem;">
        <div class="card" style="border-top:4px solid var(--accent-blue);">
            <div class="card-header"><span class="card-title">📊 Distribusi SWC (Sales Week Coverage)</span></div>
            <div id="swcChart" style="height:250px;"></div>
        </div>
        <div class="card" style="border-top:4px solid var(--accent-purple,#a855f7);">
            <div class="card-header"><span class="card-title">🏢 Stok per Principal</span></div>
            <table class="data-table"><thead><tr><th>Principal</th><th class="text-right">SKU</th><th class="text-right">Nilai</th><th class="text-right">Avg SWC</th><th class="text-right">🚨 Kritis</th><th class="text-right" style="color:var(--accent-red);">🛑 Tertahan</th></tr></thead><tbody>
            @foreach($stockByPrincipal as $sp)
            <tr>
                <td>{{ Str::limit(str_replace('PT. ', '', $sp->principal_name), 25) }}</td>
                <td class="text-right">{{ $sp->sku_count }}</td>
                <td class="text-right font-mono" title="Rp {{ number_format($sp->total_value, 0, ',', '.') }}">{{ number_format($sp->total_value / 1000000, 1, ',', '.') }}Jt</td>
                <td class="text-right"><span class="badge {{ ($sp->avg_swc ?? 0) <= 2 ? 'badge-red' : (($sp->avg_swc ?? 0) >= 12 ? 'badge-yellow' : 'badge-green') }}">{{ number_format($sp->avg_swc ?? 0, 1) }}w</span></td>
                <td class="text-right text-red font-bold">{{ $sp->critical_count ?: '-' }}</td>
                <td class="text-right text-red font-bold">{{ $sp->slow_count ?: '-' }}</td>
            </tr>
            @endforeach
            </tbody></table>
        </div>
    </div>

    {{-- TOP FAST MOVING --}}
    @if($fastMovingItems->count())
    <div class="card" style="margin-bottom:1.5rem;border-top:4px solid var(--accent-green);">
        <div class="card-header"><span class="card-title">🚀 Top Fast-Moving (Pergerakan Tercepat)</span><span class="badge badge-green">Top 20</span></div>
        <p style="padding:0.75rem 1rem 0;font-size:0.75rem;color:var(--text-muted);">Daftar 20 barang dengan angka penjualan rata-rata mingguan (WAS) tertinggi.</p>
        <table class="data-table"><thead><tr><th>Produk</th><th>Principal</th><th>Gudang</th><th class="text-right">On Hand</th><th class="text-right">SWC</th><th class="text-right" style="color:var(--accent-green);">WAS (Laku per Minggu)</th></tr></thead><tbody>
        @foreach($fastMovingItems as $item)
        <tr style="background: rgba(16, 185, 129, 0.02);">
            <td>{{ Str::limit($item->item_name, 30) }}</td>
            <td style="font-size:0.75rem;">{{ Str::limit(str_replace('PT. ', '', $item->principal_name), 18) }}</td>
            <td><span class="badge badge-blue" style="font-size:0.65rem;">{{ Str::limit($item->warehouse_name, 15) }}</span></td>
            <td class="text-right font-mono">{{ number_format($item->on_hand_base) }}</td>
            <td class="text-right"><span class="badge badge-green">{{ $item->swc }}w</span></td>
            <td class="text-right font-mono font-bold" style="color:var(--accent-green); font-size:1.05rem;">🔥 {{ number_format($item->was, 0) }} <span style="font-size:0.75rem; color:var(--text-muted); font-weight:normal;">karton/mgg</span></td>
        </tr>
        @endforeach
        </tbody></table>
    </div>
    @endif
</div>

{{-- TAB 2: DATA KRITIS --}}
<div id="tab-kritis" class="tab-content" style="display:none;">
    <div class="card" style="margin-bottom:1.5rem; padding: 1rem;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                <input type="text" id="search-kritis" placeholder="Cari nama/kode produk kritis..." class="period-select" style="width:300px;" onkeyup="if(event.key==='Enter') loadKritis(1)">
                <button class="btn btn-primary" onclick="loadKritis(1)">Cari</button>
            </div>
            <div><button class="btn btn-secondary" onclick="document.getElementById('search-kritis').value=''; loadKritis(1)">Reset</button></div>
        </div>
    </div>
    <div id="kritis-container">
        <div style="text-align:center; padding:3rem; color:var(--text-muted);">Memuat data stok kritis... <div style="margin-top:1rem;font-size:2rem;">⏳</div></div>
    </div>
</div>

{{-- TAB 3: MODAL TERTAHAN --}}
<div id="tab-tertahan" class="tab-content" style="display:none;">
    <div class="card" style="margin-bottom:1.5rem; padding: 1rem;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                <input type="text" id="search-tertahan" placeholder="Cari nama/kode produk tertahan..." class="period-select" style="width:300px;" onkeyup="if(event.key==='Enter') loadTertahan(1)">
                <button class="btn btn-primary" onclick="loadTertahan(1)">Cari</button>
            </div>
            <div><button class="btn btn-secondary" onclick="document.getElementById('search-tertahan').value=''; loadTertahan(1)">Reset</button></div>
        </div>
    </div>
    <div id="tertahan-container">
        <div style="text-align:center; padding:3rem; color:var(--text-muted);">Memuat data modal tertahan... <div style="margin-top:1rem;font-size:2rem;">⏳</div></div>
    </div>
</div>

{{-- TAB 4: SEMUA DATA STOK --}}
<div id="tab-semua" class="tab-content" style="display:none;">
    <div class="card" style="margin-bottom:1.5rem; padding: 1rem;">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <div style="display:flex; gap:0.5rem; flex-wrap:wrap;">
                <input type="text" id="search-semua" placeholder="Cari nama/kode produk..." class="period-select" style="width:300px;" onkeyup="if(event.key==='Enter') loadSemuaData(1)">
                <button class="btn btn-primary" onclick="loadSemuaData(1)">Cari</button>
            </div>
            <div><button class="btn btn-secondary" onclick="document.getElementById('search-semua').value=''; loadSemuaData(1)">Reset</button></div>
        </div>
    </div>
    <div id="semua-container">
        <div style="text-align:center; padding:3rem; color:var(--text-muted);">Memuat seluruh data stok... <div style="margin-top:1rem;font-size:2rem;">⏳</div></div>
    </div>
</div>

<script>
    function switchTab(tabId, btnElement) {
        document.querySelectorAll('.tab-content').forEach(el => el.style.display = 'none');
        document.querySelectorAll('.tab-btn').forEach(el => {
            el.classList.remove('btn-primary');
            el.classList.add('btn-secondary');
        });
        
        document.getElementById('tab-' + tabId).style.display = 'block';
        btnElement.classList.remove('btn-secondary');
        btnElement.classList.add('btn-primary');

        if(tabId === 'kritis' && document.getElementById('kritis-container').innerHTML.includes('Memuat')) {
            loadKritis(1);
        }
        if(tabId === 'tertahan' && document.getElementById('tertahan-container').innerHTML.includes('Memuat')) {
            loadTertahan(1);
        }
        if(tabId === 'semua' && document.getElementById('semua-container').innerHTML.includes('Memuat')) {
            loadSemuaData(1);
        }
    }

    function loadKritis(page = 1) {
        const search = document.getElementById('search-kritis').value;
        const container = document.getElementById('kritis-container');
        container.innerHTML = '<div style="text-align:center; padding:3rem; color:var(--text-muted);">Memuat data stok kritis... <div style="margin-top:1rem;font-size:2rem;">⏳</div></div>';
        
        fetch(`{{ route('sales-per.stock.tab-kritis') }}?page=${page}&period={{ $period }}&principal={{ urlencode($selectedPrincipal) }}&warehouse={{ urlencode($selectedWarehouse) }}&search=${encodeURIComponent(search)}`)
            .then(res => res.text())
            .then(html => {
                container.innerHTML = html;
                // Hijack pagination links
                container.querySelectorAll('.pagination a').forEach(a => {
                    a.addEventListener('click', function(e) {
                        e.preventDefault();
                        const url = new URL(this.href);
                        loadKritis(url.searchParams.get('page'));
                    });
                });
            })
            .catch(err => { container.innerHTML = '<div style="color:var(--accent-red);padding:2rem;">Gagal memuat data.</div>'; });
    }

    function loadTertahan(page = 1) {
        const search = document.getElementById('search-tertahan').value;
        const container = document.getElementById('tertahan-container');
        container.innerHTML = '<div style="text-align:center; padding:3rem; color:var(--text-muted);">Memuat data modal tertahan... <div style="margin-top:1rem;font-size:2rem;">⏳</div></div>';
        
        fetch(`{{ route('sales-per.stock.tab-tertahan') }}?page=${page}&period={{ $period }}&principal={{ urlencode($selectedPrincipal) }}&warehouse={{ urlencode($selectedWarehouse) }}&search=${encodeURIComponent(search)}`)
            .then(res => res.text())
            .then(html => {
                container.innerHTML = html;
                // Hijack pagination links
                container.querySelectorAll('.pagination a').forEach(a => {
                    a.addEventListener('click', function(e) {
                        e.preventDefault();
                        const url = new URL(this.href);
                        loadTertahan(url.searchParams.get('page'));
                    });
                });
            })
            .catch(err => { container.innerHTML = '<div style="color:var(--accent-red);padding:2rem;">Gagal memuat data.</div>'; });
    }

    function loadSemuaData(page = 1) {
        const search = document.getElementById('search-semua').value;
        const container = document.getElementById('semua-container');
        container.innerHTML = '<div style="text-align:center; padding:3rem; color:var(--text-muted);">Memuat seluruh data stok... <div style="margin-top:1rem;font-size:2rem;">⏳</div></div>';
        
        fetch(`{{ route('sales-per.stock.tab-semua') }}?page=${page}&period={{ $period }}&principal={{ urlencode($selectedPrincipal) }}&warehouse={{ urlencode($selectedWarehouse) }}&search=${encodeURIComponent(search)}`)
            .then(res => res.text())
            .then(html => {
                container.innerHTML = html;
                // Hijack pagination links
                container.querySelectorAll('.pagination a').forEach(a => {
                    a.addEventListener('click', function(e) {
                        e.preventDefault();
                        const url = new URL(this.href);
                        loadSemuaData(url.searchParams.get('page'));
                    });
                });
            })
            .catch(err => { container.innerHTML = '<div style="color:var(--accent-red);padding:2rem;">Gagal memuat data.</div>'; });
    }
</script>


{{-- CHART --}}
<script>
document.addEventListener('DOMContentLoaded', function(){
    var swcData = @json($swcDistribution);
    var labels = Object.keys(swcData);
    var values = Object.values(swcData);
    var colors = ['#64748b','#ef4444','#f59e0b','#10b981','#3b82f6','#a855f7'];
    if(typeof ApexCharts!=='undefined'){
        new ApexCharts(document.querySelector("#swcChart"),{
            chart:{type:'donut',height:250,background:'transparent',foreColor:'#94a3b8'},
            series:values, labels:labels, colors:colors,
            plotOptions:{pie:{donut:{size:'60%',labels:{show:true,total:{show:true,label:'Total SKU',formatter:()=>'{{ $totalSKU }}'}}}}},
            legend:{position:'bottom',fontSize:'11px'},
            dataLabels:{enabled:true,formatter:function(v){return Math.round(v)+'%'}},
            tooltip:{theme:'dark'}
        }).render();
    }
});
</script>
@endif
@endsection
