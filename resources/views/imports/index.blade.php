@extends('layouts.app')
@section('page-title', 'Import Data')
@section('top-bar-actions')
<a href="{{ route('imports.create') }}" class="btn btn-primary">
    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
    Import Baru
</a>
@endsection

@section('content')
<div class="card">
    <div class="card-header"><span class="card-title">Riwayat Import</span></div>
    @if($imports->isEmpty())
        <div style="text-align:center;padding:3rem;color:var(--text-muted);">
            <p>Belum ada data import. Klik tombol "Import Baru" untuk memulai.</p>
        </div>
    @else
        <table class="data-table">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>File</th>
                    <th>Periode</th>
                    <th>Total</th>
                    <th>Sukses</th>
                    <th>Skip</th>
                    <th>Gagal</th>
                    <th>Status</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
            @foreach($imports as $import)
                <tr>
                    <td>{{ $import->created_at->format('d M Y H:i') }}</td>
                    <td>{{ Str::limit($import->filename, 30) }}</td>
                    <td><span class="badge badge-blue">{{ $import->period }}</span></td>
                    <td class="font-mono">{{ number_format($import->total_rows) }}</td>
                    <td class="font-mono text-green">{{ number_format($import->imported_rows) }}</td>
                    <td class="font-mono text-yellow">{{ number_format($import->skipped_rows ?? 0) }}</td>
                    <td class="font-mono text-red">{{ number_format($import->failed_rows) }}</td>
                    <td>
                        @if($import->status === 'completed')
                            <span class="badge badge-green">✓ Selesai</span>
                        @elseif($import->status === 'processing')
                            <span class="badge badge-yellow">⟳ Proses</span>
                        @elseif($import->status === 'failed')
                            <span class="badge badge-red">✗ Gagal</span>
                        @else
                            <span class="badge">Pending</span>
                        @endif
                    </td>
                    <td>
                        <div style="display:flex;gap:0.5rem;">
                            @if($import->errors)
                                <a href="{{ route('imports.show', $import) }}" class="btn btn-secondary" style="padding:0.25rem 0.5rem;font-size:0.75rem;">Detail</a>
                            @endif
                            <form method="POST" action="{{ route('imports.destroy', $import) }}" onsubmit="return confirm('Hapus import ini dan semua data transaksi periode {{ $import->period }}?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn btn-danger" style="padding:0.25rem 0.5rem;font-size:0.75rem;">Hapus</button>
                            </form>
                        </div>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <div class="pagination-wrapper">{{ $imports->links() }}</div>
    @endif
</div>
@endsection
