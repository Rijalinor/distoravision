@extends('layouts.app')
@section('page-title', 'Import Data Baru')

@section('content')
<div style="max-width:600px;">
    <div class="card">
        <div class="card-header"><span class="card-title">Upload File Secondary Sales</span></div>

        <form method="POST" action="{{ route('imports.store') }}" enctype="multipart/form-data" id="importForm">
            @csrf

            <div class="form-group">
                <label class="form-label">Periode Data</label>
                <input type="month" name="period" class="form-input" value="{{ old('period', date('Y-m')) }}" required>
                @error('period')
                    <p style="color:var(--accent-red);font-size:0.75rem;margin-top:0.25rem;">{{ $message }}</p>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label">Mode Import</label>
                <div style="display:flex; gap:0.75rem;" id="modeSelector">
                    <label style="flex:1; cursor:pointer;">
                        <input type="radio" name="import_mode" value="ganti" checked style="display:none;" onchange="updateModeUI()">
                        <div class="card mode-card" data-mode="ganti" style="padding:0.875rem; border:2px solid var(--primary); background:rgba(99,102,241,0.15); transition:all 0.2s; position:relative;">
                            <div style="position:absolute;top:0.5rem;right:0.5rem;" class="mode-check">
                                <svg width="20" height="20" fill="var(--primary-light)" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            </div>
                            <div style="font-weight:700; font-size:0.85rem; margin-bottom:0.25rem; color:var(--primary-light);">🔄 Mode Ganti</div>
                            <div style="font-size:0.72rem; color:var(--text-secondary);">Data lama di periode ini dihapus, diganti total dengan file baru. Cocok untuk update harian.</div>
                        </div>
                    </label>
                    <label style="flex:1; cursor:pointer;">
                        <input type="radio" name="import_mode" value="tambah" style="display:none;" onchange="updateModeUI()">
                        <div class="card mode-card" data-mode="tambah" style="padding:0.875rem; border:2px solid var(--border-color); transition:all 0.2s; position:relative;">
                            <div style="position:absolute;top:0.5rem;right:0.5rem;display:none;" class="mode-check">
                                <svg width="20" height="20" fill="var(--primary-light)" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/></svg>
                            </div>
                            <div style="font-weight:700; font-size:0.85rem; margin-bottom:0.25rem;">➕ Mode Tambah</div>
                            <div style="font-size:0.72rem; color:var(--text-muted);">Data baru ditambahkan tanpa menghapus data lama. Cocok jika file hanya berisi data baru.</div>
                        </div>
                    </label>
                </div>
                @error('import_mode')
                    <p style="color:var(--accent-red);font-size:0.75rem;margin-top:0.25rem;">{{ $message }}</p>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label">File CSV/Excel</label>
                <div class="upload-area" id="uploadArea">
                    <input type="file" name="file" accept=".csv,.xlsx,.xls,.txt" required id="fileInput">
                    <div class="upload-icon">📄</div>
                    <p style="color:var(--text-primary);font-weight:600;margin-bottom:0.25rem;" id="fileName">Drag & drop atau klik untuk upload</p>
                    <p style="color:var(--text-muted);font-size:0.8rem;">CSV, XLSX, XLS (max 50MB)</p>
                </div>
                @error('file')
                    <p style="color:var(--accent-red);font-size:0.75rem;margin-top:0.25rem;">{{ $message }}</p>
                @enderror
            </div>

            <div style="display:flex;gap:1rem;align-items:center;">
                <button type="submit" class="btn btn-primary" id="submitBtn">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                    Import Data
                </button>
                <a href="{{ route('imports.index') }}" class="btn btn-secondary">Batal</a>
            </div>
        </form>
    </div>

    <div class="card" style="margin-top:1.5rem;">
        <div class="card-header">
            <span class="card-title">Panduan Import</span>
            <a href="{{ route('settings.column-mapping') }}" class="btn btn-secondary" style="font-size:0.75rem;padding:0.3rem 0.75rem;">
                ⚙️ Ubah Mapping Kolom
            </a>
        </div>
        <div style="font-size:0.8rem;color:var(--text-secondary);line-height:1.8;">
            <p>📌 File harus memiliki header kolom pada baris pertama</p>
            <p>📌 Kolom wajib:
                <code style="background:rgba(99,102,241,0.1);padding:0.1rem 0.3rem;border-radius:3px;color:var(--primary-light);">{{ config('import_columns.branch', 'branch') }}</code>,
                <code style="background:rgba(99,102,241,0.1);padding:0.1rem 0.3rem;border-radius:3px;color:var(--primary-light);">{{ config('import_columns.sales_id', 'sales_id') }}</code>,
                <code style="background:rgba(99,102,241,0.1);padding:0.1rem 0.3rem;border-radius:3px;color:var(--primary-light);">{{ config('import_columns.sales_name', 'sales_name') }}</code>,
                <code style="background:rgba(99,102,241,0.1);padding:0.1rem 0.3rem;border-radius:3px;color:var(--primary-light);">{{ config('import_columns.type', 'type') }}</code> (I/R),
                <code style="background:rgba(99,102,241,0.1);padding:0.1rem 0.3rem;border-radius:3px;color:var(--primary-light);">{{ config('import_columns.outlet_id', 'outlet_id') }}</code>,
                <code style="background:rgba(99,102,241,0.1);padding:0.1rem 0.3rem;border-radius:3px;color:var(--primary-light);">{{ config('import_columns.outlet_name', 'outlet_name') }}</code>,
                <code style="background:rgba(99,102,241,0.1);padding:0.1rem 0.3rem;border-radius:3px;color:var(--primary-light);">{{ config('import_columns.item_no', 'item_no') }}</code>,
                <code style="background:rgba(99,102,241,0.1);padding:0.1rem 0.3rem;border-radius:3px;color:var(--primary-light);">{{ config('import_columns.item_name', 'item_name') }}</code>,
                <code style="background:rgba(99,102,241,0.1);padding:0.1rem 0.3rem;border-radius:3px;color:var(--primary-light);">{{ config('import_columns.principle_name', 'principle_name') }}</code>
            </p>
            <p>📌 Type <span class="badge badge-green">I</span> = Invoice, <span class="badge badge-red">R</span> = Return</p>
            <p>📌 File besar (>10.000 baris) akan diproses secara chunk untuk performa optimal</p>
            <p>⚠️ Mode Ganti akan menghapus data lama di periode tersebut sebelum mengimport data baru</p>
            <p style="margin-top:0.5rem;color:var(--text-muted);">💡 Jika nama kolom Excel berbeda, ubah mapping di <a href="{{ route('settings.column-mapping') }}" style="color:var(--primary-light);">Settings > Column Mapping</a></p>
        </div>
    </div>
</div>

<script>
function updateModeUI() {
    document.querySelectorAll('.mode-card').forEach(function(card) {
        var mode = card.getAttribute('data-mode');
        var radio = document.querySelector('input[name="import_mode"][value="' + mode + '"]');
        var check = card.querySelector('.mode-check');
        var title = card.querySelector('div[style*="font-weight"]');
        if (radio.checked) {
            card.style.borderColor = 'var(--primary)';
            card.style.background = 'rgba(99,102,241,0.15)';
            card.style.boxShadow = '0 0 15px rgba(99,102,241,0.2)';
            check.style.display = 'block';
            title.style.color = 'var(--primary-light)';
        } else {
            card.style.borderColor = 'var(--border-color)';
            card.style.background = '';
            card.style.boxShadow = 'none';
            check.style.display = 'none';
            title.style.color = '';
        }
    });
}
document.getElementById('fileInput').addEventListener('change', function() {
    var name = this.files[0] ? this.files[0].name : 'Drag & drop atau klik untuk upload';
    document.getElementById('fileName').textContent = name;
    if(this.files[0]) document.getElementById('uploadArea').style.borderColor = 'var(--primary)';
});
document.getElementById('importForm').addEventListener('submit', function(e) {
    var mode = document.querySelector('input[name="import_mode"]:checked');
    if (mode && mode.value === 'ganti') {
        if (!confirm('⚠️ MODE GANTI AKTIF!\n\nSemua data transaksi di periode ini akan DIHAPUS dan diganti dengan data dari file baru.\n\nApakah Anda yakin ingin melanjutkan?')) {
            e.preventDefault();
            return false;
        }
    }
    var btn = document.getElementById('submitBtn');
    btn.innerHTML = '<svg class="animate-spin" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg> Sedang import...';
    btn.disabled = true;
    btn.style.opacity = '0.7';
});
</script>
@endsection
