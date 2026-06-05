@extends('layouts.app')
@section('page-title', 'Upload Sales Per')

@section('content')
<div class="card" style="max-width:600px;">
    <div class="card-header"><span class="card-title">Upload File Excel Sales Per</span></div>
    <form method="POST" action="{{ route('sales-per.imports.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="form-group">
            <label class="form-label">Periode</label>
            <input type="month" name="period" class="form-input" value="{{ date('Y-m') }}" required>
        </div>
        <div class="form-group">
            <label class="form-label">Mode Import</label>
            <select name="import_mode" class="form-select">
                <option value="ganti" selected>Ganti data periode ini</option>
                <option value="tambah">Tambah ke data yang ada</option>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">File Excel (.xlsx)</label>
            <div class="upload-area">
                <input type="file" name="file" accept=".xlsx,.xls" required>
                <div class="upload-icon">📊</div>
                <div style="font-weight:600;">Drag & drop atau klik untuk memilih file</div>
                <div style="font-size:0.8rem;color:var(--text-muted);margin-top:0.5rem;">Format: sales per [tanggal].xlsx · Max 50MB</div>
            </div>
        </div>
        <div style="display:flex;gap:1rem;margin-top:1.5rem;">
            <button type="submit" class="btn btn-primary">Upload & Proses</button>
            <a href="{{ route('sales-per.imports.index') }}" class="btn btn-secondary">Batal</a>
        </div>
    </form>
</div>
@endsection
