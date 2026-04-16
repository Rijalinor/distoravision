@extends('layouts.app')
@section('page-title', 'Import Data AR')

@section('content')
<div style="max-width:600px;">
    <div class="card">
        <div class="card-header"><span class="card-title">Upload File AR (Piutang)</span></div>

        <form method="POST" action="{{ route('ar.imports.store') }}" enctype="multipart/form-data" id="arImportForm">
            @csrf

            <div class="form-group">
                <label class="form-label">Tanggal Laporan AR</label>
                <input type="date" name="report_date" class="form-input" value="{{ old('report_date', date('Y-m-d')) }}" required>
                @error('report_date')
                    <p style="color:var(--accent-red);font-size:0.75rem;margin-top:0.25rem;">{{ $message }}</p>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label">Pilih Sheet (Cabang)</label>
                <input type="text" name="sheet_name" class="form-input" value="{{ old('sheet_name', 'BJM') }}" placeholder="BJM" required
                    style="text-transform:uppercase;">
                <p style="color:var(--text-muted);font-size:0.7rem;margin-top:0.25rem;">
                    Masukkan nama sheet/cabang, misal: <strong>BJM</strong>, <strong>BRB</strong>, <strong>BTL</strong>.
                    Sistem akan mencocokkan secara otomatis.
                </p>
                @error('sheet_name')
                    <p style="color:var(--accent-red);font-size:0.75rem;margin-top:0.25rem;">{{ $message }}</p>
                @enderror
            </div>

            <div class="form-group">
                <label class="form-label">File Excel AR</label>
                <div class="upload-area" id="uploadArea">
                    <input type="file" name="file" accept=".xlsx,.xls,.csv" required id="arFileInput">
                    <div class="upload-icon">💰</div>
                    <p style="color:var(--text-primary);font-weight:600;margin-bottom:0.25rem;" id="arFileName">Drag & drop atau klik untuk upload</p>
                    <p style="color:var(--text-muted);font-size:0.8rem;">XLSX, XLS (max 50MB)</p>
                </div>
                @error('file')
                    <p style="color:var(--accent-red);font-size:0.75rem;margin-top:0.25rem;">{{ $message }}</p>
                @enderror
            </div>

            <div style="display:flex;gap:1rem;align-items:center;">
                <button type="submit" class="btn btn-primary" id="arSubmitBtn">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                    Import AR
                </button>
                <a href="{{ route('ar.imports.index') }}" class="btn btn-secondary">Batal</a>
            </div>
        </form>
    </div>

    <div class="card" style="margin-top:1.5rem;">
        <div class="card-header"><span class="card-title">Panduan Import AR</span></div>
        <div style="font-size:0.8rem;color:var(--text-secondary);line-height:1.8;">
            <p>📌 File harus berformat <strong>Excel (.xlsx)</strong> dengan header pada baris pertama</p>
            <p>📌 Kolom utama: <code style="background:rgba(99,102,241,0.1);padding:0.1rem 0.3rem;border-radius:3px;color:var(--primary-light);">PFI/SN</code>, Outlet Id, Outlet Name, AR Balance, Over Due</p>
            <p>📌 Setiap import akan <strong>menambah batch baru</strong> — data import sebelumnya tetap tersimpan</p>
            <p>📌 Dashboard AR otomatis membaca dari import terbaru</p>
            <p>💡 File bisa memiliki beberapa sheet (per cabang). Pilih sheet yang ingin diimport.</p>
        </div>
    </div>
</div>

<script>
document.getElementById('arFileInput').addEventListener('change', function() {
    var name = this.files[0] ? this.files[0].name : 'Drag & drop atau klik untuk upload';
    document.getElementById('arFileName').textContent = name;
    if(this.files[0]) document.getElementById('uploadArea').style.borderColor = 'var(--primary)';
});
document.getElementById('arImportForm').addEventListener('submit', function() {
    var btn = document.getElementById('arSubmitBtn');
    btn.innerHTML = '<svg class="animate-spin" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg> Sedang import...';
    btn.disabled = true;
    btn.style.opacity = '0.7';
});
</script>
@endsection
