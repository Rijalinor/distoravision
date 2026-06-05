<div class="card" style="margin-bottom:1.5rem;border-top:4px solid var(--accent-red);">
    <div class="card-header">
        <span class="card-title">🛑 Data Lengkap Modal Tertahan (Slow-Moving & Dead Stock)</span>
        <span class="badge badge-red">{{ $items->total() }} produk</span>
    </div>
    <p style="padding:0.75rem 1rem 0;font-size:0.75rem;color:var(--text-muted);">Daftar barang *Slow-Moving* (SWC > 8 minggu) atau mati total (SWC 0) diurutkan berdasarkan nilai modal tertanam terbesar.</p>
    <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Produk</th>
                    <th>Principal</th>
                    <th>Gudang</th>
                    <th class="text-right">On Hand</th>
                    <th class="text-right">SWC</th>
                    <th class="text-right" style="color:var(--accent-red);">Nilai Tertanam</th>
                </tr>
            </thead>
            <tbody>
                @forelse($items as $item)
                <tr style="background: rgba(239, 68, 68, 0.02);">
                    <td>{{ Str::limit($item->item_name, 30) }}</td>
                    <td style="font-size:0.75rem;">{{ Str::limit(str_replace('PT. ', '', $item->principal_name), 18) }}</td>
                    <td><span class="badge badge-blue" style="font-size:0.65rem;">{{ Str::limit($item->warehouse_name, 15) }}</span></td>
                    <td class="text-right font-mono">{{ number_format($item->on_hand_base) }}</td>
                    <td class="text-right"><span class="badge badge-red">{{ $item->swc }}w</span></td>
                    <td class="text-right font-mono font-bold" style="color:var(--accent-red); font-size:1.1rem;">Rp {{ number_format($item->stock_value_on_hand / 1000000, 1, ',', '.') }}Jt</td>
                </tr>
                @empty
                <tr><td colspan="6" class="text-center" style="padding:2rem;">Tidak ada barang tertahan.</td></tr>
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
