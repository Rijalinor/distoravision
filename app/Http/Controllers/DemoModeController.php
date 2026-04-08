<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DemoModeController extends Controller
{
    public function toggle(Request $request): RedirectResponse
    {
        $active = (bool) $request->session()->get('demo_mode_active', false);
        $request->session()->put('demo_mode_active', !$active);

        return back()->with(
            'success',
            !$active
                ? 'Demo mode aktif. Semua analytics sekarang menggunakan data fake.'
                : 'Demo mode dimatikan. Sistem kembali menggunakan data asli.'
        );
    }
}
