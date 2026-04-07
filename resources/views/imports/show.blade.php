@extends('layouts.app')
@section('page-title', 'Detail Import')

@section('content')
<div style="max-width:700px;">
    <div class="card">
        <div class="card-header"><span class="card-title">Detail Import #{{ $import->id }}</span></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.5rem;">
            <div>
                <div class="text-sm text-muted">File</div>
                <div>{{ $import->filename }}</div>
            </div>
            <div>
                <div class="text-sm text-muted">Periode</div>
                <div><span class="badge badge-blue">{{ $import->period }}</span></div>
            </div>
            <div>
                <div class="text-sm text-muted">Status</div>
                <div>
                    @if($import->status === 'completed')
                        <span class="badge badge-green">Selesai</span>
                    @elseif($import->status === 'failed')
                        <span class="badge badge-red">Gagal</span>
                    @else
                        <span class="badge badge-yellow">{{ $import->status }}</span>
                    @endif
                </div>
            </div>
            <div>
                <div class="text-sm text-muted">Baris Sukses / Gagal</div>
                <div><span class="text-green">{{ number_format($import->imported_rows) }}</span> / <span class="text-red">{{ number_format($import->failed_rows) }}</span></div>
            </div>
        </div>

        @if($import->errors)
            <div style="margin-top:1rem;">
                <div class="card-title" style="margin-bottom:0.5rem;">Error Log</div>
                <div style="background:var(--bg-darker);border-radius:8px;padding:1rem;font-size:0.75rem;font-family:monospace;max-height:300px;overflow-y:auto;color:var(--accent-red);white-space:pre-wrap;">{{ $import->errors }}</div>
            </div>
        @endif

        <div style="margin-top:1.5rem;">
            <a href="{{ route('imports.index') }}" class="btn btn-secondary">← Kembali</a>
        </div>
    </div>
</div>
@endsection
