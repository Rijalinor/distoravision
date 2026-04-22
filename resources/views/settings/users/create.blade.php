@extends('layouts.app')

@section('page-title', 'Tambah User Baru')

@section('content')
<div class="card" style="max-width: 600px; margin: 0 auto;">
    <form action="{{ route('users.store') }}" method="POST">
        @csrf

        <div class="form-group">
            <label class="form-label">Nama Lengkap</label>
            <input type="text" name="name" class="form-input" value="{{ old('name') }}" required>
            @error('name')<span class="text-red text-sm mt-1">{{ $message }}</span>@enderror
        </div>

        <div class="form-group">
            <label class="form-label">Email (Untuk Login)</label>
            <input type="email" name="email" class="form-input" value="{{ old('email') }}" required>
            @error('email')<span class="text-red text-sm mt-1">{{ $message }}</span>@enderror
        </div>

        <div class="form-group">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-input" required>
            @error('password')<span class="text-red text-sm mt-1">{{ $message }}</span>@enderror
        </div>

        <div class="form-group">
            <label class="form-label">Konfirmasi Password</label>
            <input type="password" name="password_confirmation" class="form-input" required>
        </div>

        <hr style="border-color: var(--border-color); margin: 1.5rem 0;">

        <div class="form-group">
            <label class="form-label">Tipe Role / Hak Akses</label>
            <select name="role" id="role-select" class="form-select" required>
                <option value="">-- Pilih Tipe --</option>
                <option value="admin" {{ old('role') == 'admin' ? 'selected' : '' }}>Admin (Global Akses)</option>
                <option value="supervisor" {{ old('role') == 'supervisor' ? 'selected' : '' }}>Supervisor (Multi-Principal)</option>
                <option value="salesman" {{ old('role') == 'salesman' ? 'selected' : '' }}>Salesman (Strict 1-entitas)</option>
            </select>
            @error('role')<span class="text-red text-sm mt-1">{{ $message }}</span>@enderror
        </div>

        <div class="form-group" id="salesman-wrapper" style="display: none;">
            <label class="form-label">Pilih Data Salesman (Identitas Dirinya)</label>
            <select name="salesman_id" class="form-select">
                <option value="">-- Hubungkan dengan Sales --</option>
                @foreach($salesmen as $s)
                    <option value="{{ $s->id }}" {{ old('salesman_id') == $s->id ? 'selected' : '' }}>{{ $s->name }}</option>
                @endforeach
            </select>
            <p class="text-muted text-sm mt-1">Saat login, user ini HANYA BISA melihat omset per tokonya sendiri (tidak bisa curang lihat yang lain).</p>
        </div>

        <div class="form-group" id="principal-wrapper" style="display: none;">
            <label class="form-label">Brand / Principal Yang Dipegang (Bisa Pilih Banyak)</label>
            <div style="background: var(--bg-dark); padding: 1rem; border-radius: 8px; max-height: 250px; overflow-y:auto; border: 1px solid var(--border-color);">
                @foreach($principals as $p)
                <div style="margin-bottom: 0.5rem; display: flex; align-items:center; gap:0.5rem;">
                    <input type="checkbox" name="principals[]" value="{{ $p->id }}" id="p_{{ $p->id }}" 
                        {{ in_array($p->id, old('principals', [])) ? 'checked' : '' }}
                        style="width:18px;height:18px;">
                    <label for="p_{{ $p->id }}" style="font-size:0.85rem;">{{ $p->name }}</label>
                </div>
                @endforeach
            </div>
            <p class="text-muted text-sm mt-1">Laporan Omset Supervisor akan otomatis menghitung <strong>HANYA</strong> untuk brand yang dicentang di sini.</p>
        </div>

        <div style="display:flex; justify-content: flex-end; gap: 1rem; margin-top: 2rem;">
            <a href="{{ route('users.index') }}" class="btn btn-secondary">Batal</a>
            <button type="submit" class="btn btn-primary">Simpan User Baru</button>
        </div>
    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const roleSelect = document.getElementById('role-select');
        const salesmanWrap = document.getElementById('salesman-wrapper');
        const principalWrap = document.getElementById('principal-wrapper');

        function toggleFields() {
            salesmanWrap.style.display = 'none';
            principalWrap.style.display = 'none';
            
            if (roleSelect.value === 'salesman') {
                salesmanWrap.style.display = 'block';
            } else if (roleSelect.value === 'supervisor') {
                principalWrap.style.display = 'block';
            }
        }

        roleSelect.addEventListener('change', toggleFields);
        toggleFields(); // Init on load
    });
</script>
@endsection
