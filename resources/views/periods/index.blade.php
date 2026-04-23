@extends('layouts.app')
@section('page-title', 'Tutup Buku — Manajemen Periode')

@section('content')
<div style="margin-bottom: 1.5rem;">
    <p style="color: var(--text-muted); font-size: 0.85rem; max-width: 700px;">
        Kelola periode akuntansi bulanan. Tutup buku di akhir bulan untuk membekukan data (snapshot) dan mencegah perubahan data di periode yang sudah ditutup.
    </p>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">Daftar Periode</span>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th>Periode</th>
                <th>Status</th>
                <th>Ditutup Oleh</th>
                <th>Tanggal Tutup</th>
                <th>Snapshot</th>
                <th class="text-right">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse($periods as $period)
            <tr>
                <td style="font-weight: 600;">{{ $period->label }}</td>
                <td>
                    @if($period->isClosed())
                        <span class="badge badge-red">🔒 Closed</span>
                    @else
                        <span class="badge badge-green">🟢 Open</span>
                    @endif
                </td>
                <td>{{ $period->closedByUser?->name ?? '-' }}</td>
                <td>{{ $period->closed_at?->format('d M Y, H:i') ?? '-' }}</td>
                <td>
                    @if($period->snapshot)
                        <span class="badge badge-blue">📊 Ada</span>
                    @else
                        <span style="color: var(--text-muted); font-size: 0.8rem;">—</span>
                    @endif
                </td>
                <td class="text-right">
                    <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                        @if($period->isOpen())
                            {{-- Tutup Buku Button --}}
                            <button class="btn btn-primary" style="font-size: 0.75rem; padding: 0.375rem 0.875rem;"
                                onclick="document.getElementById('closeModal{{ $period->id }}').style.display='flex'">
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                Tutup Buku
                            </button>
                        @else
                            {{-- View Snapshot --}}
                            @if($period->snapshot)
                            <a href="{{ route('periods.show', $period) }}" class="btn btn-secondary" style="font-size: 0.75rem; padding: 0.375rem 0.875rem;">
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                Lihat Snapshot
                            </a>
                            @endif

                            {{-- Reopen Button --}}
                            <button class="btn btn-danger" style="font-size: 0.75rem; padding: 0.375rem 0.875rem;"
                                onclick="document.getElementById('reopenModal{{ $period->id }}').style.display='flex'">
                                <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"></path></svg>
                                Buka Kembali
                            </button>
                        @endif
                    </div>
                </td>
            </tr>

            {{-- Close Modal --}}
            @if($period->isOpen())
            <tr><td colspan="6" style="padding:0;border:none;">
                <div id="closeModal{{ $period->id }}" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:100; align-items:center; justify-content:center; backdrop-filter:blur(4px);">
                    <div class="card" style="max-width:500px; width:90%; position:relative;">
                        <button onclick="this.closest('[id^=closeModal]').style.display='none'" style="position:absolute;top:1rem;right:1rem;background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:1.2rem;">✕</button>
                        <h3 style="margin-bottom:0.5rem; font-size:1.1rem;">🔒 Konfirmasi Tutup Buku</h3>
                        <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:1.25rem;">
                            Anda akan menutup buku untuk periode <strong style="color:var(--text-primary);">{{ $period->label }}</strong>. Proses ini akan:
                        </p>
                        <ul style="color:var(--text-secondary); font-size:0.8rem; margin-bottom:1.25rem; padding-left:1.25rem;">
                            <li>Membekukan seluruh data Sales & AR ke dalam snapshot</li>
                            <li>Mengunci import data ke periode ini</li>
                            <li>Membuka periode bulan selanjutnya secara otomatis</li>
                        </ul>
                        <form method="POST" action="{{ route('periods.close', $period) }}">
                            @csrf
                            <div class="form-group">
                                <label class="form-label">Catatan (opsional)</label>
                                <input type="text" name="notes" class="form-input" placeholder="Mis: Sudah diverifikasi oleh SVP...">
                            </div>
                            <div style="display:flex; gap:0.75rem; justify-content:flex-end;">
                                <button type="button" class="btn btn-secondary" onclick="this.closest('[id^=closeModal]').style.display='none'">Batal</button>
                                <button type="submit" class="btn btn-primary">
                                    <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                                    Ya, Tutup Buku Sekarang
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </td></tr>
            @endif

            {{-- Reopen Modal --}}
            @if($period->isClosed())
            <tr><td colspan="6" style="padding:0;border:none;">
                <div id="reopenModal{{ $period->id }}" style="display:none; position:fixed; inset:0; background:rgba(0,0,0,0.7); z-index:100; align-items:center; justify-content:center; backdrop-filter:blur(4px);">
                    <div class="card" style="max-width:450px; width:90%; position:relative;">
                        <button onclick="this.closest('[id^=reopenModal]').style.display='none'" style="position:absolute;top:1rem;right:1rem;background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:1.2rem;">✕</button>
                        <h3 style="margin-bottom:0.5rem; font-size:1.1rem;">⚠️ Buka Kembali Periode</h3>
                        <p style="color:var(--text-muted); font-size:0.85rem; margin-bottom:1.25rem;">
                            Anda yakin ingin membuka kembali periode <strong style="color:var(--text-primary);">{{ $period->label }}</strong>? Tindakan ini akan memungkinkan import data kembali ke periode ini.
                        </p>
                        <div style="display:flex; gap:0.75rem; justify-content:flex-end;">
                            <button type="button" class="btn btn-secondary" onclick="this.closest('[id^=reopenModal]').style.display='none'">Batal</button>
                            <form method="POST" action="{{ route('periods.reopen', $period) }}">
                                @csrf
                                <button type="submit" class="btn btn-danger">Ya, Buka Kembali</button>
                            </form>
                        </div>
                    </div>
                </div>
            </td></tr>
            @endif
            @empty
            <tr>
                <td colspan="6" style="text-align:center; padding:3rem; color:var(--text-muted);">
                    <div style="font-size:2.5rem; margin-bottom:0.75rem;">📅</div>
                    <p>Belum ada periode. Import data terlebih dahulu untuk membuat periode otomatis.</p>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="pagination-wrapper">
        {{ $periods->links() }}
    </div>
</div>
@endsection
