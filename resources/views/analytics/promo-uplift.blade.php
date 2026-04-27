@extends('layouts.app')
@section('page-title', 'Evaluasi Efek Promo & ROI')
@section('top-bar-actions')
<form method="GET" style="display:flex;gap:0.75rem;align-items:center;">
    @include('components.filter')
</form>
@endsection

@section('content')
<div x-data="{ tooltip: '', show: false, cx: 0, cy: 0 }" @mousemove.window="cx = $event.clientX; cy = $event.clientY">
{{-- AI Narrative --}}
<div class="card" style="margin-bottom:1.5rem; border-left: 4px solid var(--primary); background: linear-gradient(135deg, rgba(99,102,241,0.08), rgba(168,85,247,0.05));">
    <div class="card-header"><span class="card-title">🤖 Distora AI — Evaluasi Promo</span></div>
    <div style="font-size:0.85rem; line-height:1.7; white-space:pre-line; color:var(--text-secondary);">{{ $aiNarrative }}</div>
</div>

{{-- KPI Cards --}}
<div class="kpi-grid" style="grid-template-columns: repeat(4, 1fr);">
    <div class="card kpi-card">
        <div class="card-title" title="Jumlah produk yang memiliki pergeseran diskon signifikan antar periode.">Produk Dianalisa</div>
        <div class="kpi-value">{{ count($results) }}</div>
        <div class="kpi-label">Produk</div>
    </div>
    <div class="card kpi-card" style="border-top: 3px solid var(--accent-green);">
        <div class="card-title" title="Promo yang menghasilkan laba tambahan dibanding bulan normal.">Promo Sukses</div>
        <div class="kpi-value text-green">{{ $successCount }}</div>
        <div class="kpi-label">Menghasilkan Cuan</div>
    </div>
    <div class="card kpi-card" style="border-top: 3px solid var(--accent-red);">
        <div class="card-title" title="Promo yang justru mengurangi laba perusahaan dibanding bulan normal.">Promo Gagal</div>
        <div class="kpi-value text-red">{{ $failCount }}</div>
        <div class="kpi-label">Rugi Bandar</div>
    </div>
    <div class="card kpi-card" style="border-top: 3px solid var(--accent-yellow);">
        <div class="card-title" title="Total uang subsidi diskon yang dibakar di bulan-bulan promo.">Total Subsidi</div>
        <div class="kpi-value text-yellow" title="Rp {{ number_format($totalSubsidy, 0, ',', '.') }}">Rp {{ number_format($totalSubsidy / 1000000, 1, ',', '.') }}M</div>
        <div class="kpi-label">Uang Bakar Promo</div>
    </div>
</div>

{{-- Chart --}}
<div class="card" style="margin-bottom:1.5rem;">
    <div class="card-header"><span class="card-title">📊 Top 15 — Selisih Laba Promo vs Normal</span></div>
    <div id="upliftChart"></div>
</div>

{{-- Detail Table --}}
<div class="card" style="overflow:visible;">
    <div class="card-header">
        <span class="card-title">Detail Evaluasi Per Produk</span>
        @if($anomalyCount > 0)
            <span class="badge badge-red">⚠️ {{ $anomalyCount }} Anomali Terdeteksi</span>
        @endif
    </div>
    <div style="overflow-x:visible;">
        <table class="data-table" style="overflow:visible;">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Produk (Hover)</th>
                    <th>Principal</th>
                    <th class="text-right">Bln Normal</th>
                    <th class="text-right">Disc Normal</th>
                    <th class="text-right">Qty Normal</th>
                    <th class="text-right">Bln Promo</th>
                    <th class="text-right">Disc Promo</th>
                    <th class="text-right">Qty Promo</th>
                    <th class="text-right">Uplift</th>
                    <th class="text-right">Subsidi</th>
                    <th class="text-right">Selisih Laba</th>
                    <th>Status</th>
                    <th>Flag</th>
                </tr>
            </thead>
            <tbody>
            @foreach(array_slice($results, 0, 100) as $i => $item)
                @php
                    $isSuccess = $item['is_success'];
                    $upliftDir = $item['uplift_pct'] >= 0 ? 'naik' : 'anjlok';
                    $profitWord = $isSuccess ? 'MENAMBAH Laba' : 'MENGGERUS Laba';

                    // Prediksi Masa Depan & Saran Bisnis
                    $dampakText = "";
                    $saranText = "";
                    
                    if (in_array('STOCKOUT', $item['anomaly_flags'])) {
                        $dampakText = "Terjadi Loss Opportunity (kehilangan potensi laba dari toko yang mau beli tapi barang kosong). Menimbulkan citra buruk suplai perusahaan.";
                        $saranText = "Jangan pernah lempar promo besar tanpa stok minimal 1.5x dari ekspektasi sales! Tinjau manajemen inventori gudang.";
                    } elseif (in_array('FORWARD BUY', $item['anomaly_flags'])) {
                        $dampakText = "Toko akan stop belanja berbulan-bulan ke depan karena mereka cuma 'menimbun' pesanan di harga promo ini dan belum terjual keluar.";
                        $saranText = "Ubah skema diskon potong harga awal menjadi sistem Cashback / Rabat di akhir bulan dengan target pencapaian.";
                    } elseif ($isSuccess) {
                        $dampakText = "Program ini berhasil membangun daya beli pasar dan sangat ideal untuk penetrasi atau menghabiskan stok lambat tanpa mengorbankan cashflow.";
                        $saranText = "Lanjutkan program ini! Jadikan sebagai opsi kampanye triwulanan dan replikasi model promonya untuk barang serupa yang sedang macet.";
                    } else {
                        $dampakText = "Promo membakar subsidi tunai tapi gagal menarik volume. Pada akhirnya hanya akan mendidik pasar menjadi manja dan pelit saat barang tidak promo.";
                        $saranText = "Segera bekukan program diskon produk ini. Ganti strategi Hard-Selling (Diskon Harga) menjadi Push-Selling (Bundling Barang / Souvenir).";
                    }
                @endphp
                <tr>
                    <td>{{ $i + 1 }}</td>
                    
                    {{-- Tooltip Area --}}
                                        {{-- Alpine Tooltip Trigger --}}
                    <td class="font-bold text-[var(--accent-blue)]" style="cursor:help; border-bottom: 1px dashed var(--border-color);"
                        @mouseenter="tooltip = $refs.tip_{{$i}}.innerHTML; show = true"
                        @mouseleave="show = false; tooltip = ''">
                        {{ Str::limit($item['product_name'], 30) }}
                        
                        {{-- Hidden Template for Alpine Portal --}}
                        <template x-ref="tip_{{$i}}">
                            <div class="flex items-center gap-2 mb-3 border-b border-slate-600 pb-2">
                                <span class="text-xl">🤖</span>
                                <span class="font-bold text-white text-[13px] tracking-wide uppercase">AI Insight: Evaluasi Strategi</span>
                            </div>
                            
                            <div class="text-[12px] text-slate-300 space-y-3 font-medium">
                                <p>Di bulan <strong class="text-white">{{ $item['promo_period'] }}</strong> diskon dinaikkan jadi <strong class="text-yellow-400">{{ $item['promo_disc_pct'] }}%</strong> (Bakar Subsidi: <strong class="text-yellow-400 font-mono">Rp {{ number_format($item['promo_subsidy'], 0, ',', '.') }}</strong>).</p>
                                
                                <p>Akibatnya, volume <strong class="{{ $isSuccess ? 'text-emerald-400' : 'text-rose-400' }}">{{ $upliftDir }} {{ number_format(abs($item['uplift_pct']), 1) }}%</strong> ({{ $item['uplift_pct'] >= 0 ? '+' : '' }}{{ number_format($item['uplift_qty']) }} qty dibanding bulan normal {{ $item['baseline_period'] }}).</p>
                                
                                <p class="font-bold {{ $isSuccess ? 'text-emerald-400' : 'text-rose-400' }}">
                                    Keseluruhan promo ini {{ $profitWord }} bersih Rp {{ number_format(abs($item['profit_diff']), 0, ',', '.') }}.
                                </p>

                                {{-- AI Business Direction Layer --}}
                                <div class="mt-4 pt-3 border-t border-slate-700 bg-slate-900/50 p-3 rounded-lg border-l-4 {{ $isSuccess && empty($item['anomaly_flags']) ? 'border-emerald-500' : 'border-amber-500' }}">
                                    <div class="mb-2">
                                        <div class="text-white font-bold opacity-80 mb-0.5">🔮 Dampak Prediktif:</div>
                                        <div class="leading-relaxed text-slate-400">{{ $dampakText }}</div>
                                    </div>
                                    <div class="">
                                        <div class="text-white font-bold opacity-80 mb-0.5">💡 Rekomendasi Aksi:</div>
                                        <div class="leading-relaxed {{ $isSuccess && empty($item['anomaly_flags']) ? 'text-emerald-200' : 'text-amber-200' }}">{{ $saranText }}</div>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </td>

                    <td style="font-size:0.75rem;">{{ Str::limit($item['principal_name'], 18) }}</td>
                    <td class="text-right font-mono" style="font-size:0.75rem;">{{ $item['baseline_period'] }}</td>
                    <td class="text-right font-mono">{{ $item['baseline_disc_pct'] }}%</td>
                    <td class="text-right font-mono">{{ number_format($item['baseline_qty']) }}</td>
                    <td class="text-right font-mono" style="font-size:0.75rem;">{{ $item['promo_period'] }}</td>
                    <td class="text-right font-mono text-yellow">{{ $item['promo_disc_pct'] }}%</td>
                    <td class="text-right font-mono">{{ number_format($item['promo_qty']) }}</td>
                    <td class="text-right font-mono {{ $item['uplift_pct'] >= 0 ? 'text-green' : 'text-red' }}">
                        {{ $item['uplift_pct'] >= 0 ? '+' : '' }}{{ number_format($item['uplift_pct'], 1) }}%
                    </td>
                    <td class="text-right font-mono text-yellow" title="Rp {{ number_format($item['promo_subsidy'], 0, ',', '.') }}">
                        {{ number_format($item['promo_subsidy']/1000000, 1, ',', '.') }}M
                    </td>
                    <td class="text-right font-mono {{ $item['is_success'] ? 'text-green' : 'text-red' }} font-bold">
                        Rp {{ number_format($item['profit_diff'], 0, ',', '.') }}
                    </td>
                    <td>
                        @if($item['is_success'])
                            <span class="badge badge-green">SUKSES</span>
                        @else
                            <span class="badge badge-red">GAGAL</span>
                        @endif
                    </td>
                    <td>
                        @foreach($item['anomaly_flags'] as $flag)
                            @if($flag === 'STOCKOUT')
                                <span class="badge badge-red" title="Indikasi Stockout">⚠️ OOS</span>
                            @elseif($flag === 'FORWARD BUY')
                                <span class="badge badge-yellow" title="Indikasi Menimbun">⚠️ F. BUY</span>
                            @endif
                        @endforeach
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @if(count($results) > 100)
        <div style="font-size:0.75rem; text-align:center; padding-top:1rem; color:var(--text-muted);">Menampilkan 100 data teratas dari total {{ count($results) }} data.</div>
    @endif
</div>
    {{-- GLOBAL TOOLTIP PORTAL --}}
    <template x-teleport="body">
        <div x-show="show" x-html="tooltip" x-transition.opacity.duration.100ms
             class="fixed z-[9999999] pointer-events-none w-[420px] p-5 rounded-xl bg-slate-800 border border-slate-600 shadow-2xl"
             :style="`left: ${(cx + 440 > window.innerWidth ? cx - 435 : cx + 25)}px; top: ${(cy + 250 > window.innerHeight ? cy - 200 : cy + 25)}px;`" style="display: none; box-shadow: 0 10px 40px -10px rgba(0,0,0,1);">
        </div>
    </template>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
    var chartData = @json($chartData);

    var categories = chartData.map(d => d.product_name.substring(0, 18));
    var values = chartData.map(d => Math.round(d.profit_diff));
    var colors = chartData.map(d => d.profit_diff >= 0 ? '#10b981' : '#ef4444');

    new ApexCharts(document.querySelector("#upliftChart"), {
        series: [{
            name: 'Selisih Laba (Rp)',
            data: values
        }],
        chart: { height: 380, type: 'bar', toolbar: { show: false }, background: 'transparent' },
        plotOptions: {
            bar: {
                borderRadius: 6,
                columnWidth: '55%',
                distributed: true,
                dataLabels: { position: 'top' }
            }
        },
        colors: colors,
        dataLabels: {
            enabled: true,
            formatter: function(val) {
                if (Math.abs(val) >= 1000000) return (val / 1000000).toFixed(1) + 'M';
                if (Math.abs(val) >= 1000) return (val / 1000).toFixed(0) + 'K';
                return val;
            },
            offsetY: -20,
            style: { fontSize: '10px', colors: ['#94a3b8'] }
        },
        xaxis: {
            categories: categories,
            labels: { style: { colors: '#94a3b8', fontSize: '9px' }, rotate: -45 }
        },
        yaxis: {
            labels: {
                style: { colors: '#94a3b8' },
                formatter: function(v) {
                    if (Math.abs(v) >= 1000000) return (v / 1000000).toFixed(0) + 'M';
                    return (v / 1000).toFixed(0) + 'K';
                }
            }
        },
        annotations: {
            yaxis: [{ y: 0, borderColor: '#64748b', strokeDashArray: 4, label: { text: 'Break-Even', style: { color: '#f1f5f9', background: '#64748b', fontSize: '10px' } } }]
        },
        legend: { show: false },
        theme: { mode: 'dark' },
        grid: { borderColor: '#334155' },
        tooltip: {
            y: {
                formatter: function(val) { return 'Rp ' + val.toLocaleString('id-ID'); }
            }
        }
    }).render();
});
</script>
@endsection
