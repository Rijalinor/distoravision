<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Salesman;
use App\Models\Principal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with('salesman', 'principals')->get();
        return view('settings.users.index', compact('users'));
    }

    public function create()
    {
        $salesmen = Salesman::orderBy('name')->get();
        $principals = Principal::orderBy('name')->get();
        return view('settings.users.create', compact('salesmen', 'principals'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => 'required|in:admin,supervisor,salesman',
            'salesman_id' => 'nullable|required_if:role,salesman|exists:salesmen,id',
            'principals' => 'nullable|array',
            'principals.*' => 'exists:principals,id',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'salesman_id' => $request->role === 'salesman' ? $request->salesman_id : null,
        ]);

        if ($request->role === 'supervisor' && $request->has('principals')) {
            $user->principals()->sync($request->principals);
        }

        return redirect()->route('users.index')->with('success', 'User created successfully.');
    }

    public function edit(User $user)
    {
        if ($user->id === auth()->id()) {
            return redirect()->route('users.index')->with('error', 'Silakan edit profil sendiri melalui menu Profile.');
        }

        $salesmen = Salesman::orderBy('name')->get();
        $principals = Principal::orderBy('name')->get();
        return view('settings.users.edit', compact('user', 'salesmen', 'principals'));
    }

    public function update(Request $request, User $user)
    {
        if ($user->id === auth()->id()) {
            return redirect()->route('users.index')->with('error', 'Tidak bisa mengubah role diri sendiri di sini.');
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$user->id,
            'password' => ['nullable', 'confirmed', Rules\Password::defaults()],
            'role' => 'required|in:admin,supervisor,salesman',
            'salesman_id' => 'nullable|required_if:role,salesman|exists:salesmen,id',
            'principals' => 'nullable|array',
            'principals.*' => 'exists:principals,id',
        ]);

        $user->name = $request->name;
        $user->email = $request->email;
        $user->role = $request->role;
        $user->salesman_id = $request->role === 'salesman' ? $request->salesman_id : null;
        
        if ($request->filled('password')) {
            $user->password = Hash::make($request->password);
        }
        
        $user->save();

        if ($request->role === 'supervisor' && $request->has('principals')) {
            $user->principals()->sync($request->principals);
        } else {
            $user->principals()->detach();
        }

        return redirect()->route('users.index')->with('success', 'User updated successfully.');
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return redirect()->route('users.index')->with('error', 'Anda tidak bisa menghapus diri sendiri.');
        }

        $user->delete();
        return redirect()->route('users.index')->with('success', 'User deleted successfully.');
    }
}
