@extends('layouts.app')

@section('page-title', 'Audit Trail (Activity Log)')

@section('content')
<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Waktu</th>
                <th>Aktor</th>
                <th>Aksi / Deskripsi</th>
                <th>Target Data</th>
                <th>Perubahan Detail</th>
            </tr>
        </thead>
        <tbody>
            @forelse($activities as $log)
            <tr>
                <td>{{ $log->created_at->format('d M Y, H:i') }}</td>
                <td>
                    @if($log->causer)
                        <div style="font-weight: 600; color: var(--text-primary);">{{ $log->causer->name }}</div>
                        <div class="text-sm text-muted">{{ $log->causer->role }}</div>
                    @else
                        <span class="text-muted">Sistem (Auto)</span>
                    @endif
                </td>
                <td>
                    <span class="badge" style="background:#334155; color:#cbd5e1;">{{ $log->description }}</span>
                </td>
                <td>
                    @if($log->subject_type)
                        <span class="text-sm font-mono">{{ class_basename($log->subject_type) }} #{{ $log->subject_id }}</span>
                    @else
                        <span class="text-muted text-sm">-</span>
                    @endif
                </td>
                <td>
                    @if($log->properties && count($log->properties) > 0)
                        <button class="btn btn-secondary" style="padding: 0.25rem 0.5rem; font-size: 0.7rem;" onclick="document.getElementById('props-{{ $log->id }}').style.display = document.getElementById('props-{{ $log->id }}').style.display === 'none' ? 'block' : 'none'">Lihat Perubahan</button>
                        <div id="props-{{ $log->id }}" style="display:none; margin-top:0.5rem; background: var(--bg-darker); padding: 0.5rem; border-radius: 6px; font-size: 0.75rem; font-family: monospace; white-space: pre-wrap; overflow-x:auto;">{{ json_encode($log->properties, JSON_PRETTY_PRINT) }}</div>
                    @else
                        <span class="text-muted text-sm">-</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" style="text-align: center; padding: 2rem;" class="text-muted">Belum ada catatan aktivitas.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
    
    <div class="mt-1" style="margin-top: 1.5rem; display: flex; justify-content: center;">
        {{ $activities->links('pagination::bootstrap-4') }}
    </div>
</div>
@endsection
