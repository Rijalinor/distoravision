<div class="table-responsive">
    <table class="data-table" style="width:100%; border-collapse:collapse;">
        <thead>
            <tr>
                <th>Produk</th>
                <th>Principal</th>
                <th>Gudang</th>
                <th class="text-right">On Hand</th>
                @if(!auth()->user()->isSalesman())
                <th class="text-right">Nilai Stok</th>
                @endif
                <th class="text-right">WAS</th>
                <th class="text-right">SWC</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $item)
            <tr>
                <td>
                    <div style="font-weight:600;">{{ $item->item_name }}</div>
                    <div style="font-size:0.75rem;color:var(--text-muted);">{{ $item->item_no }}</div>
                </td>
                <td style="font-size:0.8rem;">{{ Str::limit(str_replace('PT. ', '', $item->principal_name), 20) }}</td>
                <td><span class="badge badge-blue" style="font-size:0.7rem;">{{ Str::limit($item->warehouse_name, 15) }}</span></td>
                <td class="text-right font-mono">{{ number_format($item->on_hand_base) }}</td>
                @if(!auth()->user()->isSalesman())
                <td class="text-right font-mono">Rp {{ number_format($item->stock_value_on_hand, 0, ',', '.') }}</td>
                @endif
                <td class="text-right font-mono">{{ number_format($item->was, 1) }}</td>
                <td class="text-right">
                    @if($item->swc <= 2 && $item->swc > 0)
                        <span class="badge badge-red">{{ number_format($item->swc, 1) }}w</span>
                    @elseif($item->swc > 8 || $item->swc == 0)
                        <span class="badge badge-yellow">{{ number_format($item->swc, 1) }}w</span>
                    @else
                        <span class="badge badge-green">{{ number_format($item->swc, 1) }}w</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="text-center" style="padding:2rem;">Tidak ada data ditemukan.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div style="margin-top:1rem;display:flex;justify-content:space-between;align-items:center;">
    <div style="font-size:0.8rem;color:var(--text-muted);">
        Menampilkan {{ $items->firstItem() ?? 0 }} s/d {{ $items->lastItem() ?? 0 }} dari {{ number_format($items->total()) }} data
    </div>
    <div class="pagination-wrapper" style="margin-top:0;">
        {{ $items->appends(request()->query())->links() }}
    </div>
</div>
