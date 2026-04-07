<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ config('app.name', 'DistoraVision') }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:300,400,500,600,700,800&display=swap" rel="stylesheet" />
        <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        <style>
            * { font-family: 'Inter', sans-serif; }
            :root {
                --sidebar-w: 260px;
                --primary: #6366f1;
                --primary-light: #818cf8;
                --bg-dark: #0f172a;
                --bg-darker: #020617;
                --bg-card: #1e293b;
                --bg-card-hover: #334155;
                --text-primary: #f1f5f9;
                --text-secondary: #94a3b8;
                --text-muted: #64748b;
                --accent-green: #10b981;
                --accent-red: #ef4444;
                --accent-yellow: #f59e0b;
                --accent-blue: #3b82f6;
                --border-color: #334155;
            }
            body { background: var(--bg-darker); color: var(--text-primary); }

            /* Sidebar */
            .sidebar {
                position: fixed; top: 0; left: 0; bottom: 0;
                width: var(--sidebar-w); background: var(--bg-dark);
                border-right: 1px solid var(--border-color);
                display: flex; flex-direction: column;
                z-index: 50; transition: transform 0.3s;
            }
            .sidebar-logo {
                padding: 1.5rem; border-bottom: 1px solid var(--border-color);
                display: flex; align-items: center; gap: 0.75rem;
            }
            .sidebar-logo .logo-icon {
                width: 36px; height: 36px; border-radius: 10px;
                background: linear-gradient(135deg, var(--primary), #a855f7);
                display: flex; align-items: center; justify-content: center;
                font-size: 18px; font-weight: 800; color: white;
            }
            .sidebar-logo h1 { font-size: 1.15rem; font-weight: 700; color: var(--text-primary); }
            .sidebar-logo span { font-size: 0.7rem; color: var(--text-muted); }

            .sidebar-nav { flex: 1; overflow-y: auto; padding: 1rem 0.75rem; }
            .nav-section { margin-bottom: 1.5rem; }
            .nav-section-title {
                padding: 0 0.75rem; margin-bottom: 0.5rem;
                font-size: 0.65rem; font-weight: 600; text-transform: uppercase;
                letter-spacing: 0.1em; color: var(--text-muted);
            }
            .nav-link {
                display: flex; align-items: center; gap: 0.75rem;
                padding: 0.625rem 0.75rem; border-radius: 8px;
                color: var(--text-secondary); text-decoration: none;
                font-size: 0.875rem; font-weight: 500;
                transition: all 0.15s;
            }
            .nav-link:hover { background: var(--bg-card); color: var(--text-primary); }
            .nav-link.active {
                background: linear-gradient(135deg, rgba(99,102,241,0.2), rgba(168,85,247,0.1));
                color: var(--primary-light);
                border: 1px solid rgba(99,102,241,0.3);
            }
            .nav-link svg { width: 20px; height: 20px; flex-shrink: 0; }

            .sidebar-user {
                padding: 1rem; border-top: 1px solid var(--border-color);
                display: flex; align-items: center; gap: 0.75rem;
            }
            .sidebar-user .avatar {
                width: 36px; height: 36px; border-radius: 50%;
                background: linear-gradient(135deg, var(--primary), #a855f7);
                display: flex; align-items: center; justify-content: center;
                font-weight: 700; font-size: 14px; color: white;
            }
            .sidebar-user .user-info { flex: 1; }
            .sidebar-user .user-name { font-size: 0.8rem; font-weight: 600; }
            .sidebar-user .user-email { font-size: 0.7rem; color: var(--text-muted); }

            /* Main Content */
            .main-content { margin-left: var(--sidebar-w); min-height: 100vh; }
            .top-bar {
                padding: 1rem 2rem; display: flex; justify-content: space-between;
                align-items: center; border-bottom: 1px solid var(--border-color);
                background: rgba(15,23,42,0.8); backdrop-filter: blur(12px);
                position: sticky; top: 0; z-index: 40;
            }
            .page-title { font-size: 1.25rem; font-weight: 700; }
            .content-area { padding: 1.5rem 2rem; }

            /* Cards */
            .card {
                background: var(--bg-card); border: 1px solid var(--border-color);
                border-radius: 12px; padding: 1.25rem; transition: all 0.2s;
            }
            .card:hover { border-color: var(--primary); box-shadow: 0 0 20px rgba(99,102,241,0.1); }
            .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
            .card-title { font-size: 0.8rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }

            /* KPI Cards */
            .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
            .kpi-card { padding: 1.25rem; }
            .kpi-value { font-size: 1.75rem; font-weight: 800; margin: 0.5rem 0; background: linear-gradient(135deg, var(--text-primary), var(--primary-light)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
            .kpi-label { font-size: 0.75rem; color: var(--text-muted); }
            .kpi-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; }
            .kpi-icon.green { background: rgba(16,185,129,0.15); color: var(--accent-green); }
            .kpi-icon.red { background: rgba(239,68,68,0.15); color: var(--accent-red); }
            .kpi-icon.blue { background: rgba(59,130,246,0.15); color: var(--accent-blue); }
            .kpi-icon.yellow { background: rgba(245,158,11,0.15); color: var(--accent-yellow); }

            /* Tables */
            .data-table { width: 100%; border-collapse: collapse; }
            .data-table th {
                padding: 0.75rem 1rem; text-align: left;
                font-size: 0.7rem; font-weight: 600; text-transform: uppercase;
                letter-spacing: 0.05em; color: var(--text-muted);
                border-bottom: 1px solid var(--border-color);
            }
            .data-table td {
                padding: 0.75rem 1rem; font-size: 0.85rem;
                border-bottom: 1px solid rgba(51,65,85,0.5);
                color: var(--text-secondary);
            }
            .data-table tr:hover td { background: rgba(99,102,241,0.05); color: var(--text-primary); }

            /* Badges */
            .badge {
                display: inline-flex; align-items: center; padding: 0.25rem 0.625rem;
                border-radius: 100px; font-size: 0.7rem; font-weight: 600;
            }
            .badge-green { background: rgba(16,185,129,0.15); color: var(--accent-green); }
            .badge-red { background: rgba(239,68,68,0.15); color: var(--accent-red); }
            .badge-blue { background: rgba(59,130,246,0.15); color: var(--accent-blue); }
            .badge-yellow { background: rgba(245,158,11,0.15); color: var(--accent-yellow); }

            /* Buttons */
            .btn {
                display: inline-flex; align-items: center; gap: 0.5rem;
                padding: 0.5rem 1.25rem; border-radius: 8px; font-size: 0.85rem;
                font-weight: 600; border: none; cursor: pointer; transition: all 0.2s;
                text-decoration: none;
            }
            .btn-primary {
                background: linear-gradient(135deg, var(--primary), #a855f7);
                color: white;
            }
            .btn-primary:hover { opacity: 0.9; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(99,102,241,0.4); }
            .btn-danger { background: rgba(239,68,68,0.15); color: var(--accent-red); border: 1px solid rgba(239,68,68,0.3); }
            .btn-danger:hover { background: rgba(239,68,68,0.25); }
            .btn-secondary { background: var(--bg-card); color: var(--text-secondary); border: 1px solid var(--border-color); }
            .btn-secondary:hover { background: var(--bg-card-hover); color: var(--text-primary); }

            /* Form inputs */
            .form-group { margin-bottom: 1.25rem; }
            .form-label { display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; }
            .form-input, .form-select {
                width: 100%; padding: 0.625rem 0.875rem; border-radius: 8px;
                background: var(--bg-darker); border: 1px solid var(--border-color);
                color: var(--text-primary); font-size: 0.875rem;
                transition: border-color 0.15s;
            }
            .form-input:focus, .form-select:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(99,102,241,0.1); }
            .form-select option { background: var(--bg-dark); }

            /* Charts area */
            .chart-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 1.5rem; margin-bottom: 1.5rem; }

            /* Period selector */
            .period-select {
                padding: 0.375rem 0.75rem; border-radius: 6px;
                background: var(--bg-card); border: 1px solid var(--border-color);
                color: var(--text-primary); font-size: 0.8rem; font-weight: 500;
            }

            /* Alerts */
            .alert {
                padding: 0.875rem 1.25rem; border-radius: 10px; margin-bottom: 1rem;
                font-size: 0.85rem; display: flex; align-items: center; gap: 0.5rem;
            }
            .alert-success { background: rgba(16,185,129,0.1); border: 1px solid rgba(16,185,129,0.3); color: var(--accent-green); }
            .alert-error { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: var(--accent-red); }

            /* Pagination */
            .pagination-wrapper { display: flex; justify-content: center; margin-top: 1.5rem; }
            .pagination-wrapper nav { display: flex; gap: 0.25rem; }
            .pagination-wrapper a, .pagination-wrapper span {
                padding: 0.375rem 0.75rem; border-radius: 6px; font-size: 0.8rem;
                color: var(--text-secondary); background: var(--bg-card); border: 1px solid var(--border-color);
                text-decoration: none;
            }
            .pagination-wrapper span[aria-current] { background: var(--primary); color: white; border-color: var(--primary); }

            /* Grid layouts */
            .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; }
            .grid-3 { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; }

            /* Upload area */
            .upload-area {
                border: 2px dashed var(--border-color); border-radius: 12px;
                padding: 3rem; text-align: center; cursor: pointer;
                transition: all 0.2s; position: relative;
            }
            .upload-area:hover { border-color: var(--primary); background: rgba(99,102,241,0.05); }
            .upload-area input[type="file"] {
                position: absolute; inset: 0; opacity: 0; cursor: pointer;
            }
            .upload-icon { font-size: 3rem; margin-bottom: 1rem; }

            /* Responsive mobile */
            .mobile-toggle { display: none; }
            @media (max-width: 768px) {
                .sidebar { transform: translateX(-100%); }
                .sidebar.open { transform: translateX(0); }
                .main-content { margin-left: 0; }
                .mobile-toggle { display: block; }
                .grid-2, .grid-3, .chart-grid { grid-template-columns: 1fr; }
                .kpi-grid { grid-template-columns: repeat(2, 1fr); }
                .content-area { padding: 1rem; }
            }

            /* Scrollbar */
            ::-webkit-scrollbar { width: 6px; }
            ::-webkit-scrollbar-track { background: var(--bg-darker); }
            ::-webkit-scrollbar-thumb { background: var(--border-color); border-radius: 3px; }
            ::-webkit-scrollbar-thumb:hover { background: var(--text-muted); }

            /* Stat number formatting */
            .text-green { color: var(--accent-green) !important; }
            .text-red { color: var(--accent-red) !important; }
            .text-blue { color: var(--accent-blue) !important; }
            .text-yellow { color: var(--accent-yellow) !important; }
            .text-muted { color: var(--text-muted) !important; }
            .text-right { text-align: right !important; }
            .font-mono { font-family: 'JetBrains Mono', monospace; }
            .font-bold { font-weight: 700 !important; }
            .text-sm { font-size: 0.8rem; }
            .mt-1 { margin-top: 0.5rem; }
        </style>
    </head>
    <body>
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-logo">
                <div class="logo-icon">DV</div>
                <div>
                    <h1>DistoraVision</h1>
                    <span>Analytics Dashboard</span>
                </div>
            </div>

            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">Main</div>
                    <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                        Dashboard
                    </a>
                    <a href="{{ route('imports.index') }}" class="nav-link {{ request()->routeIs('imports.*') ? 'active' : '' }}">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                        Import Data
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Analytics</div>
                    <a href="{{ route('salesmen.index') }}" class="nav-link {{ request()->routeIs('salesmen.*') ? 'active' : '' }}">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        Salesman
                    </a>
                    <a href="{{ route('outlets.index') }}" class="nav-link {{ request()->routeIs('outlets.*') ? 'active' : '' }}">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                        Outlet
                    </a>
                    <a href="{{ route('principals.index') }}" class="nav-link {{ request()->routeIs('principals.*') ? 'active' : '' }}">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21l-7-5-7 5V5a2 2 0 012-2h10a2 2 0 012 2z"></path></svg>
                        Principal
                    </a>
                    <a href="{{ route('products.index') }}" class="nav-link {{ request()->routeIs('products.*') ? 'active' : '' }}">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                        Produk
                    </a>
                    <a href="{{ route('regional.index') }}" class="nav-link {{ request()->routeIs('regional.*') ? 'active' : '' }}">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        Regional
                    </a>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Intelligence</div>
                    <a href="{{ route('analytics.rfm') }}" class="nav-link {{ request()->routeIs('analytics.rfm') ? 'active' : '' }}">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"></path></svg>
                        Analisa RFM
                    </a>
                    <a href="{{ route('analytics.cross-selling') }}" class="nav-link {{ request()->routeIs('analytics.cross-selling') ? 'active' : '' }}">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        Peluang Keranjang
                    </a>
                    
                    <div class="nav-section-title" style="margin-top: 1rem;">C-Suite Analytics</div>
                    
                    <a href="{{ route('analytics.margin') }}" class="nav-link {{ request()->routeIs('analytics.margin') ? 'active' : '' }}">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Profitabilitas (Margin)
                    </a>
                    <a href="{{ route('analytics.target-tracker') }}" class="nav-link {{ request()->routeIs('analytics.target-tracker') ? 'active' : '' }}">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        Target Tracker & Run Rate
                    </a>
                    <a href="{{ route('analytics.cohort') }}" class="nav-link {{ request()->routeIs('analytics.cohort') ? 'active' : '' }}">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                        Cohort Analysis (Matriks)
                    </a>
                    
                    <!-- EXPORT / REPORT -->
                    <a href="{{ route('analytics.report') }}" class="nav-link {{ request()->routeIs('analytics.report') ? 'active' : '' }}" style="margin-top: 0.5rem; background: rgba(59, 130, 246, 0.1); border-left: 3px solid var(--accent-blue);">
                        <svg fill="none" stroke="var(--accent-blue)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        <span style="color:var(--accent-blue); font-weight:bold;">Buku Rapor (Cetak)</span>
                    </a>

                    <div class="nav-section-title" style="margin-top: 1rem;">Lainnya</div>
                    <a href="{{ route('analytics.pareto') }}" class="nav-link {{ request()->routeIs('analytics.pareto') ? 'active' : '' }}">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                        Analisa Pareto
                    </a>
                    <a href="{{ route('analytics.discount') }}" class="nav-link {{ request()->routeIs('analytics.discount') ? 'active' : '' }}">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path></svg>
                        Efektifitas Diskon
                    </a>
                    <a href="{{ route('analytics.sleeping-outlets') }}" class="nav-link {{ request()->routeIs('analytics.sleeping-outlets') ? 'active' : '' }}">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path></svg>
                        Toko Berhenti (Sleep)
                    </a>
                </div>
            </nav>

            <div class="sidebar-user">
                <div class="avatar">{{ strtoupper(substr(Auth::user()->name ?? 'U', 0, 1)) }}</div>
                <div class="user-info">
                    <div class="user-name">{{ Auth::user()->name ?? 'User' }}</div>
                    <div class="user-email">{{ Auth::user()->email ?? '' }}</div>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" style="background:none;border:none;color:var(--text-muted);cursor:pointer;" title="Logout">
                        <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                    </button>
                </form>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="main-content">
            <div class="top-bar">
                <div style="display:flex;align-items:center;gap:1rem;">
                    <button class="mobile-toggle" onclick="document.getElementById('sidebar').classList.toggle('open')" style="background:none;border:none;color:var(--text-primary);cursor:pointer;">
                        <svg width="24" height="24" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    </button>
                    <h1 class="page-title">@yield('page-title', 'Dashboard')</h1>
                </div>
                <div>
                    @yield('top-bar-actions')
                </div>
            </div>

            <div class="content-area">
                @if(session('success'))
                    <div class="alert alert-success">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        {{ session('success') }}
                    </div>
                @endif
                @if(session('error'))
                    <div class="alert alert-error">
                        <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        {{ session('error') }}
                    </div>
                @endif
                @yield('content')
            </div>
        </div>
    </body>
</html>
