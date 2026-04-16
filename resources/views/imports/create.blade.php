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
            <p>⚠️ Import data dengan periode yang sama akan menambah data baru (tidak overwrite)</p>
            <p style="margin-top:0.5rem;color:var(--text-muted);">💡 Jika nama kolom Excel berbeda, ubah mapping di <a href="{{ route('settings.column-mapping') }}" style="color:var(--primary-light);">Settings > Column Mapping</a></p>
        </div>
    </div>
</div>

<script>
document.getElementById('fileInput').addEventListener('change', function() {
    var name = this.files[0] ? this.files[0].name : 'Drag & drop atau klik untuk upload';
    document.getElementById('fileName').textContent = name;
    if(this.files[0]) document.getElementById('uploadArea').style.borderColor = 'var(--primary)';
});
document.getElementById('importForm').addEventListener('submit', function() {
    var btn = document.getElementById('submitBtn');
    btn.innerHTML = '<svg class="animate-spin" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg> Sedang import...';
    btn.disabled = true;
    btn.style.opacity = '0.7';
});
</script>
@endsection
