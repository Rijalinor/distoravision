@extends('layouts.app')
@section('page-title', 'Column Mapping')

@section('content')
<div style="max-width:900px;">

    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem;">
        <div>
            <h2 style="font-size:1.1rem;font-weight:700;color:var(--text-primary);margin-bottom:0.25rem;">⚙️ Pengaturan Mapping Kolom Excel</h2>
            <p style="font-size:0.8rem;color:var(--text-muted);">Sesuaikan nama kolom di bawah dengan header file Excel Anda. Key sistem (kiri) tetap, nama kolom Excel (kanan) bisa diubah.</p>
        </div>
        <a href="{{ route('imports.create') }}" class="btn btn-secondary" style="white-space:nowrap;">
            ← Kembali ke Import
        </a>
    </div>

    <form method="POST" action="{{ route('settings.column-mapping.update') }}" id="mappingForm">
        @csrf
        @method('PUT')

        @foreach($fieldGroups as $groupName => $fields)
        <div class="card" style="margin-bottom:1rem;">
            <div class="card-header">
                <span class="card-title">{{ $groupName }}</span>
                <span class="badge badge-blue" style="font-size:0.65rem;">{{ count($fields) }} kolom</span>
            </div>

            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:40%;">Field Sistem</th>
                        <th style="width:25%;">Key Internal</th>
                        <th style="width:35%;">Nama Kolom di Excel</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($fields as $key => $label)
                    <tr>
                        <td>
                            <span style="font-weight:600;color:var(--text-primary);">{{ $label }}</span>
                        </td>
                        <td>
                            <code style="font-size:0.75rem;padding:0.15rem 0.4rem;border-radius:4px;background:rgba(99,102,241,0.1);color:var(--primary-light);">{{ $key }}</code>
                        </td>
                        <td>
                            <input
                                type="text"
                                name="columns[{{ $key }}]"
                                value="{{ $currentMapping[$key] ?? $key }}"
                                class="form-input mapping-input"
                                style="font-family:'JetBrains Mono',monospace;font-size:0.8rem;padding:0.4rem 0.6rem;"
                                placeholder="{{ $key }}"
                                data-default="{{ $key }}"
                            >
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endforeach

        <div style="display:flex;gap:1rem;align-items:center;margin-top:1.5rem;padding-bottom:2rem;">
            <button type="submit" class="btn btn-primary">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                Simpan Mapping
            </button>
            <button type="button" class="btn btn-secondary" onclick="resetToDefaults()">
                <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                Reset ke Default
            </button>
        </div>
    </form>
</div>

<style>
    .mapping-input {
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    .mapping-input:not(:placeholder-shown) {
        border-color: var(--primary);
    }
    .mapping-input.changed {
        border-color: var(--accent-yellow) !important;
        box-shadow: 0 0 0 2px rgba(245,158,11,0.15) !important;
        background: rgba(245,158,11,0.05) !important;
    }
</style>

<script>
// Highlight changed inputs
document.querySelectorAll('.mapping-input').forEach(function(input) {
    input.addEventListener('input', function() {
        if (this.value !== this.dataset.default) {
            this.classList.add('changed');
        } else {
            this.classList.remove('changed');
        }
    });
    // Check on load
    if (input.value !== input.dataset.default) {
        input.classList.add('changed');
    }
});

function resetToDefaults() {
    if (!confirm('Reset semua mapping ke nilai default? Perubahan yang belum disimpan akan hilang.')) return;
    document.querySelectorAll('.mapping-input').forEach(function(input) {
        input.value = input.dataset.default;
        input.classList.remove('changed');
    });
}
</script>
@endsection
