@extends('layouts.app')
@section('page-title', 'Analisa RFM (Customer Segmentation)')
@section('top-bar-actions')
<form method="GET" style="display:flex;gap:0.75rem;align-items:center;">
    @include('components.filter')
    <select name="segment" class="period-select" onchange="this.form.submit()">
        <option value="all" {{ request('segment') == 'all' ? 'selected' : '' }}>Semua Segmen</option>
        <option value="Champion" {{ request('segment') == 'Champion' ? 'selected' : '' }}>🏆 Champion</option>
        <option value="Loyal" {{ request('segment') == 'Loyal' ? 'selected' : '' }}>🤝 Loyal Customer</option>
        <option value="Need Attention" {{ request('segment') == 'Need Attention' ? 'selected' : '' }}>👀 Need Attention</option>
        <option value="At Risk" {{ request('segment') == 'At Risk' ? 'selected' : '' }}>⚠️ At Risk</option>
    </select>
</form>
@endsection

@section('content')
@include('components.ai-insight')

<!-- KPIs -->
<div class="kpi-grid" style="grid-template-columns: repeat(4, 1fr);">
    <div class="card kpi-card" style="border-top: 4px solid var(--accent-green);">
        <div class="card-title" title="Toko yang sering berbelanja, baru-baru ini berbelanja, dan nominalnya besar.">Champions 🏆</div>
        <div class="kpi-value text-green">{{ number_format($tiers['Champion']) }}</div>
        <div class="kpi-label">Outlet Prioritas Tertinggi</div>
    </div>
    
    <div class="card kpi-card" style="border-top: 4px solid var(--accent-blue);">
        <div class="card-title" title="Toko yang sering berbelanja dan stabil secara nominal.">Loyal Customers 🤝</div>
        <div class="kpi-value text-blue">{{ number_format($tiers['Loyal']) }}</div>
        <div class="kpi-label">Toko Langganan Setia</div>
    </div>

    <div class="card kpi-card" style="border-top: 4px solid var(--accent-yellow);">
        <div class="card-title" title="Toko yang nominal belanja dan frekuensinya mulai menurun atau jarang berbelanja akhir-akhir ini.">Need Attention 👀</div>
        <div class="kpi-value text-yellow">{{ number_format($tiers['Need Attention']) }}</div>
        <div class="kpi-label">Perlu Diaktifkan / Difollow-up</div>
    </div>

    <div class="card kpi-card" style="border-top: 4px solid var(--accent-red);">
        <div class="card-title" title="Toko yang sudah sangat lama tidak berbelanja dan performanya anjlok. Potensi besar hilang ke tangan kompetitor.">At Risk ⚠️</div>
        <div class="kpi-value text-red">{{ number_format($tiers['At Risk']) }}</div>
        <div class="kpi-label">Risiko Tinggi Pindah Kompetitor</div>
    </div>
</div>

<div class="card" style="margin-top: 1.5rem;">
    <div class="card-header">
        <span class="card-title">Database Toko Berdasarkan Segmen RFM</span>
        <span class="badge badge-blue">Total: {{ number_format($count) }} Outlet</span>
    </div>
    <div style="overflow-x:auto; max-height: 600px;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Nama Toko</th>
                    <th class="text-right" title="Kapan terakhir order?">Recency (Hari)</th>
                    <th class="text-right" title="Berapa banyak invoice?">Frequency (Trx)</th>
                    <th class="text-right" title="Total Rp belanja?">Monetary (Rp)</th>
                    <th class="text-center" title="Skor Keseluruhan">Skor RFM</th>
                    <th class="text-right">Segmen Evaluasi</th>
                </tr>
            </thead>
            <tbody>
                @foreach($outletStats as $index => $outlet)
                    @php 
                        $daysSince = \Carbon\Carbon::parse($outlet->last_order_date)->diffInDays(now());
                        
                        $segmentColors = [
                            'Champion' => 'badge-green',
                            'Loyal' => 'badge-blue',
                            'Need Attention' => 'badge-yellow',
                            'At Risk' => 'badge-red'
                        ];
                        $badgeTheme = $segmentColors[$outlet->segment] ?? 'badge-blue';
                    @endphp
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td class="font-bold">{{ $outlet->outlet_name }}</td>
                        
                        <td class="text-right font-mono">
                            {{ number_format($daysSince) }}
                            <span style="font-size:0.6rem; color:var(--text-muted);">{{ \Carbon\Carbon::parse($outlet->last_order_date)->format('d M y') }}</span>
                        </td>
                        
                        <td class="text-right font-mono">{{ number_format($outlet->frequency) }} x</td>
                        <td class="text-right font-mono text-green">Rp {{ number_format($outlet->monetary, 0, ',', '.') }}</td>
                        
                        <td class="text-center">
                            <span class="badge" style="background:#1e293b; color:#94a3b8; letter-spacing:1px; font-family:monospace;">
                                {{ $outlet->r_score }} {{ $outlet->f_score }} {{ $outlet->m_score }}
                            </span>
                        </td>
                        
                        <td class="text-right">
                            <span class="badge {{ $badgeTheme }}">{{ $outlet->segment }}</span>
                        </td>
                    </tr>
                @endforeach
                
                @if($count == 0)
                <tr>
                    <td colspan="7" class="text-center text-muted" style="padding: 2rem;">Data tidak ditemukan. Sesuaikan filter periode atau principal.</td>
                </tr>
                @endif
            </tbody>
        </table>
    </div>
</div>
@endsection
