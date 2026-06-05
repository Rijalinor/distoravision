@extends('layouts.app')
@section('page-title', 'Restock Predictor')

@section('content')

@include('components.outlet-tabs')

{{-- FILTER --}}
<div class="card" style="margin-bottom:1.5rem;padding:0.75rem 1.25rem;">
    <form method="GET" action="{{ route('analytics.restock-predictor') }}" style="display:flex;gap:0.75rem;align-items:center;flex-wrap:wrap;">
        
        <input type="text" name="search" value="{{ $search }}" placeholder="Cari Toko atau Produk..." class="form-input" style="max-width:250px;">
        
        <select name="principal_id" class="period-select" style="max-width:220px;" onchange="this.form.submit()">
            <option value="all">Semua Principal</option>
            @foreach($principals as $id => $name)
                <option value="{{ $id }}" {{ $selectedPrincipal == $id ? 'selected' : '' }}>{{ Str::limit(str_replace('PT. ', '', $name), 25) }}</option>
            @endforeach
        </select>
        
        <button type="submit" class="btn btn-primary" style="padding:0.4rem 1rem;">Cari</button>
        @include('components.export-button')
    </form>
</div>

{{-- HEADER & AI INSIGHT --}}
<div class="card" style="margin-bottom:1.5rem;background:linear-gradient(135deg,rgba(59,130,246,0.1),rgba(16,185,129,0.1));border:1px solid rgba(59,130,246,0.3);">
    <div style="display:flex;align-items:flex-start;gap:1rem;">
        <div style="width:48px;height:48px;min-width:48px;border-radius:12px;background:linear-gradient(135deg,#3b82f6,#10b981);display:flex;align-items:center;justify-content:center;font-size:22px;">🤖</div>
        <div>
            <h2 style="font-size:1.15rem;font-weight:700;margin:0;">Distora AI: Repurchase Cycle Predictor</h2>
            <p style="color:var(--text-primary);font-size:0.85rem;margin:0.5rem 0 0 0; line-height:1.5; white-space:pre-wrap;">{{ $aiNarrative }}</p>
        </div>
    </div>
</div>

{{-- KPI --}}
<div class="kpi-grid" style="margin-bottom:1.5rem;">
    <div class="card kpi-card">
        <div class="card-header"><span class="card-title">Toko Dianalisis</span></div>
        <div class="kpi-value">{{ number_format($totalOutlets) }} <span style="font-size:1rem;color:var(--text-muted);">Toko</span></div>
    </div>
    <div class="card kpi-card" style="border-top:4px solid var(--accent-blue); background:rgba(59,130,246,0.05);">
        <div class="card-header"><span class="card-title">Pola Siklus Ditemukan</span></div>
        <div class="kpi-value" style="color:var(--accent-blue);">{{ number_format($totalAnalyzed) }} <span style="font-size:1rem;color:var(--text-muted);">Pola Produk</span></div>
    </div>
</div>

{{-- TABLE --}}
<div class="card">
    <div class="card-header">
        <span class="card-title">Pola Perilaku Belanja Toko (Siklus & Volume)</span>
    </div>
    
    <div style="overflow-x:auto;">
        <table class="data-table">
            <tbody>
                @forelse($paginatedPredictions as $g)
                
                {{-- MAIN ROW: Outlet Summary --}}
                <tr x-data="{ open: false }" style="cursor:pointer; background:rgba(255,255,255,0.02); transition:background 0.2s;" @click="open = !open" :class="{'bg-gray-800/50': open}">
                    <td colspan="6" style="padding:0;">
                        <div style="display:flex; justify-content:space-between; align-items:center; padding:1rem 1.25rem;">
                            <div style="display:flex; align-items:center; gap:1rem;">
                                <div style="width:32px; height:32px; border-radius:8px; background:rgba(99,102,241,0.1); display:flex; align-items:center; justify-content:center; color:var(--accent-blue);">
                                    <svg x-show="!open" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z"/></svg>
                                    <svg x-show="open" style="display:none;" xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" viewBox="0 0 16 16"><path d="M1.646 4.646a.5.5 0 0 1 .708 0L8 10.293l5.646-5.647a.5.5 0 0 1 .708.708l-6 6a.5.5 0 0 1-.708 0l-6-6a.5.5 0 0 1 0-.708z"/></svg>
                                </div>
                                <div>
                                    <div style="font-weight:700; color:var(--text-primary); font-size:1.05rem;">{{ Str::limit($g['outlet_name'], 40) }}</div>
                                    <div style="font-size:0.8rem; color:var(--text-muted); margin-top:2px;">
                                        Terdeteksi <strong style="color:var(--text-primary);">{{ count($g['items']) }}</strong> Pola Produk
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- CHILD ROW: Products Sub-table --}}
                        <div x-show="open" x-collapse style="display:none; border-top:1px dashed var(--border-color); background:rgba(0,0,0,0.15); padding:1rem 1.25rem;">
                            <table style="width:100%; border-collapse:collapse; font-size:0.85rem;">
                                <thead>
                                    <tr style="border-bottom:1px solid var(--border-color);">
                                        <th style="padding:0.5rem; text-align:left; color:var(--text-muted); font-weight:600;">Produk / Barang</th>
                                        <th style="padding:0.5rem; text-align:right; color:var(--text-muted); font-weight:600;">Rata-Rata Siklus</th>
                                        <th style="padding:0.5rem; text-align:right; color:var(--text-muted); font-weight:600;">Vol Beli Rata-Rata</th>
                                        <th style="padding:0.5rem; text-align:right; color:var(--text-muted); font-weight:600;">Terakhir Beli</th>
                                        <th style="padding:0.5rem; text-align:right; color:var(--text-muted); font-weight:600;">Estimasi Pesan Berikutnya</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($g['items'] as $p)
                                    <tr style="border-bottom:1px solid rgba(255,255,255,0.02);">
                                        <td style="padding:0.75rem 0.5rem;">
                                            <div style="font-weight:600; color:var(--text-primary);">{{ Str::limit($p->product_name, 40) }}</div>
                                            <div style="font-size:0.7rem; color:var(--text-muted);">{{ Str::limit(str_replace('PT. ', '', $p->principal_name), 30) }}</div>
                                        </td>
                                        <td style="padding:0.75rem 0.5rem; text-align:right; font-family:monospace; color:var(--accent-blue);">
                                            {{ round($p->avg_cycle_days) }} Hari
                                        </td>
                                        <td style="padding:0.75rem 0.5rem; text-align:right; font-family:monospace; color:var(--text-primary);">
                                            {{ number_format($p->avg_qty_per_order) }} Karton
                                        </td>
                                        <td style="padding:0.75rem 0.5rem; text-align:right; font-family:monospace; color:var(--text-muted);">
                                            {{ \Carbon\Carbon::parse($p->last_purchase_date)->translatedFormat('d M Y') }}
                                        </td>
                                        <td style="padding:0.75rem 0.5rem; text-align:right; font-family:monospace; color:var(--text-primary);">
                                            {{ \Carbon\Carbon::parse($p->next_purchase_date)->translatedFormat('d M Y') }}
                                            <div style="font-size:0.65rem; color:var(--text-muted); margin-top:2px;">
                                                @if($p->diff_days < 0)
                                                    (Terlewat {{ abs($p->diff_days) }} hr)
                                                @elseif($p->diff_days == 0)
                                                    (Hari ini)
                                                @else
                                                    ({{ $p->diff_days }} hr lagi)
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="6" class="text-center" style="padding:2rem;color:var(--text-muted);">
                        Belum ada pola siklus yang ditemukan.
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($paginatedPredictions->hasPages())
    <div class="pagination-wrapper" style="padding: 1rem 1.25rem;">
        {{ $paginatedPredictions->appends(request()->query())->links() }}
    </div>
    <div style="font-size:0.75rem; text-align:center; padding-bottom:1rem; color:var(--text-muted);">
        Menampilkan {{ $paginatedPredictions->firstItem() ?? 0 }} s/d {{ $paginatedPredictions->lastItem() ?? 0 }} data dari total {{ number_format($paginatedPredictions->total()) }} data.
    </div>
    @endif
</div>

@endsection
