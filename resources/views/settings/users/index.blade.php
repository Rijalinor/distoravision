@extends('layouts.app')

@section('page-title', 'User Management')

@section('top-bar-actions')
    <a href="{{ route('users.create') }}" class="btn btn-primary">
        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="18" height="18"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
        Tambah User
    </a>
@endsection

@section('content')
<div class="card">
    <table class="data-table">
        <thead>
            <tr>
                <th>Nama</th>
                <th>Email</th>
                <th>Role</th>
                <th>Mapping Data Area</th>
                <th>Aksi</th>
            </tr>
        </thead>
        <tbody>
            @foreach($users as $user)
            <tr>
                <td>
                    <div style="font-weight: 600; color: var(--text-primary);">{{ $user->name }}</div>
                </td>
                <td>{{ $user->email }}</td>
                <td>
                    @if($user->role === 'admin')
                        <span class="badge badge-green">Admin</span>
                    @elseif($user->role === 'supervisor')
                        <span class="badge badge-yellow">Supervisor</span>
                    @elseif($user->role === 'salesman')
                        <span class="badge badge-blue">Salesman</span>
                    @else
                        <span class="badge badge-secondary">{{ $user->role }}</span>
                    @endif
                </td>
                <td>
                    @if($user->role === 'admin')
                        <span class="text-muted text-sm">Akses 100% Global</span>
                    @elseif($user->role === 'supervisor')
                        @if($user->principals->count() > 0)
                            <div style="display:flex; flex-wrap:wrap; gap:0.25rem;">
                                @foreach($user->principals as $p)
                                    <span class="badge" style="background:#334155; color:#cbd5e1; font-size:0.65rem;">{{ $p->name }}</span>
                                @endforeach
                            </div>
                        @else
                            <span class="text-red text-sm">Belum ada Principal</span>
                        @endif
                    @elseif($user->role === 'salesman')
                        @if($user->salesman)
                            <span class="badge" style="background:#1e3a8a; color:#93c5fd; font-size:0.7rem;">{{ $user->salesman->name }}</span>
                        @else
                            <span class="text-red text-sm">Belum ada identitas Sales</span>
                        @endif
                    @endif
                </td>
                <td>
                    <div style="display:flex; gap:0.5rem;">
                        <a href="{{ route('users.edit', $user->id) }}" class="btn btn-secondary" style="padding: 0.35rem 0.75rem; font-size: 0.75rem;">Edit</a>
                        @if($user->id !== auth()->id())
                        <form action="{{ route('users.destroy', $user->id) }}" method="POST" onsubmit="return confirm('Yakin ingin menghapus user ini?');">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger" style="padding: 0.35rem 0.75rem; font-size: 0.75rem;">Hapus</button>
                        </form>
                        @endif
                    </div>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
@endsection
