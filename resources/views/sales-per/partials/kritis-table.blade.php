<div class="card" style="margin-bottom:1.5rem;border-top:4px solid var(--accent-red);">
    <div class="card-header">
        <span class="card-title">🚨 Data Lengkap Stok Kritis — Segera Order (SWC ≤ 2 Minggu)</span>
        <span class="badge badge-red">{{ $items->total() }} produk</span>
    </div>
    <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Produk</th>
                    <th>Principal</th>
                    <th>Gudang</th>
                    <th class="text-right">On Hand</th>
                    <th class="text-right">WAS</th>
                    <th class="text-right">SWC</th>
                    <th class="text-right" style="color:var(--accent-red);">Saran Order (4W)</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $item)
                <tr>
                    <td>{{ Str::limit($item->item_name, 30) }}</td>
                    <td style="font-size:0.75rem;">{{ Str::limit(str_replace('PT. ', '', $item->principal_name), 18) }}</td>
                    <td><span class="badge badge-blue" style="font-size:0.65rem;">{{ Str::limit($item->warehouse_name, 15) }}</span></td>
                    <td class="text-right font-mono">{{ number_format($item->on_hand_base) }}</td>
                    <td class="text-right font-mono"><span style="color:var(--text-muted); font-size:0.8rem;">🔥</span> {{ number_format($item->was, 0) }} <span style="font-size:0.7rem; color:var(--text-muted);">/mgg</span></td>
                    <td class="text-right"><span class="badge badge-red">{{ $item->swc }}w</span></td>
                    <td class="text-right">
                        @php 
                            $targetStock = $item->was * 4;
                            $suggestedOrder = ceil($targetStock - $item->on_hand_base);
                        @endphp
                        @if($suggestedOrder > 0)
                            <span class="badge" style="background:var(--accent-red); color:white; font-weight:800; border-radius:4px; padding: 0.3rem 0.6rem; font-family:monospace; box-shadow: 0 2px 4px rgba(239, 68, 68, 0.4);">📦 +{{ number_format($suggestedOrder) }} unit</span>
                        @else
                            <span style="color:var(--text-muted); font-size:0.8rem;">Aman</span>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="7" class="text-center" style="padding:2rem;">Tidak ada produk kritis.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($items->hasPages())
    <div class="pagination-wrapper" style="padding:1rem; border-top:1px solid rgba(255,255,255,0.05);">
        {{ $items->appends(request()->query())->links() }}
    </div>
    @endif
</div>
