@extends('layouts.app')
@section('page-title', 'Import Sales Per')

@section('content')
<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
    <div>
        <p style="color:var(--text-muted);font-size:0.85rem;">Upload file Excel "Sales Per" harian untuk monitoring omset salesman yang sedang berjalan.</p>
    </div>
    <a href="{{ route('sales-per.imports.create') }}" class="btn btn-primary">
        <svg width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
        Upload File
    </a>
</div>

    @if(session('success'))
        <div class="alert alert-success" style="margin-bottom:1.5rem;">
            <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
            {{ session('success') }}
        </div>
    @endif

    <div class="card">
    <div class="card-header">
        <span class="card-title">Riwayat Import Sales Per</span>
        <a href="" class="btn btn-secondary" style="padding:0.3rem 0.6rem;font-size:0.75rem;">🔄 Refresh</a>
    </div>
    <table class="data-table">
        <thead><tr>
            <th>File</th><th>Periode</th><th>Status</th><th>Rows</th><th>Tanggal</th><th>User</th><th class="text-right">Aksi</th>
        </tr></thead>
        <tbody>
        @forelse($imports as $imp)
        <tr>
            <td>{{ Str::limit($imp->filename, 35) }}</td>
            <td><span class="badge badge-blue">{{ $imp->period }}</span></td>
            <td>
                @if($imp->status === 'completed')<span class="badge badge-green">✓ Selesai</span>
                @elseif($imp->status === 'processing')<span class="badge badge-yellow">⏳ Proses</span>
                @elseif($imp->status === 'failed')<span class="badge badge-red">✗ Gagal</span>
                @else<span class="badge">Pending</span>@endif
            </td>
            <td class="font-mono">{{ number_format($imp->imported_rows) }} / {{ number_format($imp->total_rows) }}</td>
            <td style="font-size:0.8rem;color:var(--text-muted);">{{ $imp->created_at->format('d/m/Y H:i') }}</td>
            <td style="font-size:0.8rem;">{{ $imp->user->name ?? '-' }}</td>
            <td class="text-right">
                <a href="javascript:void(0)" 
                   onclick="if(confirm('Hapus import ini dan semua data terkait?')) document.getElementById('delete-form-{{ $imp->id }}').submit();" 
                   class="btn btn-danger" 
                   style="padding:0.4rem 0.8rem;font-size:0.75rem;">
                    Hapus
                </a>
                <form id="delete-form-{{ $imp->id }}" action="{{ route('sales-per.imports.destroy', $imp) }}" method="POST" style="display: none;">
                    @csrf @method('DELETE')
                </form>
            </td>
        </tr>
        @if($imp->errors)
        <tr><td colspan="7" style="padding:0.5rem 1rem;font-size:0.75rem;color:var(--accent-red);background:rgba(239,68,68,0.05);">{{ Str::limit($imp->errors, 200) }}</td></tr>
        @endif
        @empty
        <tr><td colspan="7" style="text-align:center;color:var(--text-muted);padding:3rem;">Belum ada import. Klik "Upload File" untuk mulai.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>

<div class="pagination-wrapper">{{ $imports->links() }}</div>
@endsection
