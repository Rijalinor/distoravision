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
                --sidebar-w-collapsed: 70px;
                --primary: #8991c2;       /* Lavender Blue */
                --primary-light: #a3aace; /* Lighter Lavender */
                --primary-dark: #6b73a6;  /* Hover Lavender */
                --bg-dark: #111633;       /* Midnight Navy (Stripe 2) */
                --bg-darker: #070B19;     /* Darkest Navy (Stripe 1) */
                --bg-card: #182046;       /* Deep Navy Card */
                --bg-card-hover: #3b476e; /* Slate Blue Card Hover (Stripe 3) */
                --text-primary: #f8fafc;
                --text-secondary: #cbd5e1;
                --text-muted: #94a3b8;
                --accent-green: #10b981;
                --accent-red: #ef4444;
                --accent-yellow: #f59e0b;
                --accent-blue: #8991c2;   /* Matching primary lavender */
                --border-color: #243156;  /* Midnight Navy border */
            }
            body { background: var(--bg-darker); color: var(--text-primary); }

            .sidebar {
                position: fixed; top: 0; left: 0; bottom: 0;
                width: var(--sidebar-w); background: var(--bg-dark);
                border-right: 1px solid var(--border-color);
                display: flex; flex-direction: column;
                z-index: 50; transition: transform 0.3s; /* Removed width transition for performance */
                overflow: hidden;
            }
            .sidebar-logo {
                padding: 1.5rem; border-bottom: 1px solid var(--border-color);
                display: flex; align-items: center; gap: 0.75rem;
            }
            .sidebar-logo .logo-icon {
                width: 36px; height: 36px; border-radius: 8px;
                background: var(--primary);
                display: flex; align-items: center; justify-content: center;
                font-size: 18px; font-weight: 800; color: white;
            }
            .sidebar-logo h1 { font-size: 1.15rem; font-weight: 700; color: var(--text-primary); transition: opacity 0.2s, width 0.2s; white-space: nowrap; }
            .sidebar-logo span { font-size: 0.7rem; color: var(--text-muted); transition: opacity 0.2s; white-space: nowrap; }

            .sidebar-nav { flex: 1; overflow-y: auto; padding: 1rem 0.75rem; }
            .nav-section { margin-bottom: 1.5rem; }
            .nav-section-title {
                padding: 0 0.75rem; margin-bottom: 0.5rem;
                font-size: 0.65rem; font-weight: 600; text-transform: uppercase;
                letter-spacing: 0.1em; color: var(--text-muted);
                white-space: nowrap; transition: opacity 0.2s;
                cursor: pointer; user-select: none;
                display: flex; align-items: center; justify-content: space-between;
            }
            .nav-section-title:hover { color: var(--text-secondary); }
            .nav-section-title .section-chevron {
                transition: transform 0.2s; font-size: 0.55rem;
            }
            .nav-section.collapsed .nav-section-title .section-chevron { transform: rotate(-90deg); }
            .nav-section.collapsed .nav-link { display: none; }
            .nav-link {
                display: flex; align-items: center; gap: 0.75rem;
                padding: 0.625rem 0.75rem; border-radius: 8px;
                color: var(--text-secondary); text-decoration: none;
                font-size: 0.875rem; font-weight: 500;
                transition: all 0.15s; white-space: nowrap; overflow: hidden;
            }
            .nav-link:hover { background: var(--bg-card); color: var(--text-primary); }
            .nav-link.active {
                background: rgba(137, 145, 194, 0.15); /* Soft transparent primary */
                color: var(--primary-light);
                border: 1px solid rgba(137, 145, 194, 0.3);
            }
            .nav-link svg { width: 20px; height: 20px; flex-shrink: 0; min-width: 20px; }

            /* Sidebar Collapse Toggle */
            .sidebar-collapse-btn {
                position: absolute; top: 1.35rem; right: -12px;
                width: 24px; height: 24px; border-radius: 50%;
                background: var(--bg-card); border: 1px solid var(--border-color);
                color: var(--text-muted); cursor: pointer;
                display: flex; align-items: center; justify-content: center;
                z-index: 60; transition: all 0.2s;
                box-shadow: 0 2px 8px rgba(0,0,0,0.3);
            }
            .sidebar-collapse-btn:hover { background: var(--primary); color: white; border-color: var(--primary); }
            .sidebar-collapse-btn svg { width: 14px; height: 14px; transition: transform 0.3s; }

            /* Sidebar Collapsed State */
            .sidebar.collapsed { width: var(--sidebar-w-collapsed); }
            .sidebar.collapsed .sidebar-logo h1,
            .sidebar.collapsed .sidebar-logo span { display: none; }
            .sidebar.collapsed .sidebar-logo > div:last-child { display: none; }
            .sidebar.collapsed .sidebar-logo { justify-content: center; padding: 1.25rem 0; }
            .sidebar.collapsed .nav-section-title { display: none; }
            .sidebar.collapsed .nav-link {
                justify-content: center; padding: 0.65rem 0;
                font-size: 0; gap: 0; /* hides raw text nodes */
                position: relative;
            }
            .sidebar.collapsed .nav-link svg { width: 22px; height: 22px; }
            .sidebar.collapsed .nav-link:hover::after {
                content: attr(title); position: absolute; left: calc(100% + 8px);
                top: 50%; transform: translateY(-50%);
                background: var(--bg-card); color: var(--text-primary);
                font-size: 0.75rem; padding: 0.35rem 0.65rem; border-radius: 6px;
                border: 1px solid var(--border-color); white-space: nowrap;
                box-shadow: 0 4px 12px rgba(0,0,0,0.4); z-index: 100;
                pointer-events: none;
            }
            .sidebar.collapsed .nav-link.active { border: none; padding: 0.65rem 0; }
            .sidebar.collapsed .sidebar-user .user-info { display: none; }
            .sidebar.collapsed .sidebar-user form { display: none; }
            .sidebar.collapsed .sidebar-user { justify-content: center; padding: 0.75rem 0; }
            .sidebar.collapsed .sidebar-collapse-btn svg { transform: rotate(180deg); }
            .sidebar.collapsed + .main-content { margin-left: var(--sidebar-w-collapsed); }
            .sidebar.collapsed .sidebar-nav { padding: 0.75rem 0.35rem; }
            .sidebar.collapsed .nav-section { margin-bottom: 0.5rem; }

            .sidebar-user {
                padding: 1rem; border-top: 1px solid var(--border-color);
                display: flex; align-items: center; gap: 0.75rem;
            }
            .sidebar-user .avatar {
                width: 36px; height: 36px; border-radius: 8px;
                background: var(--primary);
                display: flex; align-items: center; justify-content: center;
                font-weight: 700; font-size: 14px; color: white;
            }
            .sidebar-user .user-info { flex: 1; }
            .sidebar-user .user-name { font-size: 0.8rem; font-weight: 600; }
            .sidebar-user .user-email { font-size: 0.7rem; color: var(--text-muted); }

            /* Main Content */
            .main-content { margin-left: var(--sidebar-w); min-height: 100vh; /* Removed margin-left transition for performance */ }
            .top-bar {
                padding: 0.75rem 2rem; display: flex; justify-content: space-between;
                align-items: center; border-bottom: 1px solid var(--border-color);
                background: var(--bg-dark); /* Removed blur for performance */
                position: sticky; top: 0; z-index: 40;
            }
            .page-title { font-size: 1.25rem; font-weight: 700; }
            .content-area { padding: 1.5rem 2rem; }

            /* Breadcrumb */
            .breadcrumb {
                display: flex; align-items: center; gap: 0.4rem;
                font-size: 0.72rem; color: var(--text-muted); margin-top: 0.15rem;
            }
            .breadcrumb a { color: var(--text-muted); text-decoration: none; transition: color 0.15s; }
            .breadcrumb a:hover { color: var(--primary-light); }
            .breadcrumb .bc-sep { opacity: 0.4; font-size: 0.6rem; }
            .breadcrumb .bc-current { color: var(--text-secondary); font-weight: 600; }

            /* Top Bar Right Items */
            .topbar-right { display: flex; align-items: center; gap: 0.5rem; flex-wrap: wrap; justify-content: flex-end; }
            .topbar-clock {
                font-family: 'JetBrains Mono', monospace; font-size: 0.75rem;
                color: var(--text-muted); padding: 0.3rem 0.6rem;
                background: rgba(255,255,255,0.03); border: 1px solid var(--border-color);
                border-radius: 6px; white-space: nowrap;
            }
            .topbar-search-trigger {
                display: flex; align-items: center; gap: 0.5rem;
                padding: 0.35rem 0.75rem; border-radius: 8px;
                background: rgba(255,255,255,0.03); border: 1px solid var(--border-color);
                color: var(--text-muted); font-size: 0.8rem; cursor: pointer;
                transition: all 0.2s; white-space: nowrap;
            }
            .topbar-search-trigger:hover { border-color: var(--primary); color: var(--text-secondary); background: rgba(137,145,194,0.05); }
            .topbar-search-trigger kbd {
                font-size: 0.6rem; padding: 0.1rem 0.35rem; border-radius: 4px;
                background: var(--bg-card); border: 1px solid var(--border-color);
                font-family: inherit; color: var(--text-muted);
            }

            /* Notification Bell */
            .topbar-bell {
                position: relative; background: none; border: 1px solid var(--border-color);
                border-radius: 8px; padding: 0.4rem; cursor: pointer;
                color: var(--text-muted); transition: all 0.2s;
                display: flex; align-items: center; justify-content: center;
            }
            .topbar-bell:hover { border-color: var(--primary); color: var(--text-secondary); background: rgba(137,145,194,0.05); }
            .topbar-bell svg { width: 18px; height: 18px; }
            .topbar-bell .bell-badge {
                position: absolute; top: -4px; right: -4px;
                min-width: 16px; height: 16px; border-radius: 50%;
                background: var(--accent-red); color: white;
                font-size: 0.55rem; font-weight: 800;
                display: flex; align-items: center; justify-content: center;
                border: 2px solid var(--bg-dark);
            }
            .bell-dropdown {
                position: absolute; top: calc(100% + 8px); right: 0;
                width: 320px; background: var(--bg-card); border: 1px solid var(--border-color);
                border-radius: 12px; box-shadow: 0 12px 40px rgba(0,0,0,0.5);
                display: none; z-index: 100; overflow: hidden;
            }
            .bell-dropdown.show { display: block; animation: dropIn 0.2s ease; }
            .bell-dropdown-header {
                padding: 0.75rem 1rem; border-bottom: 1px solid var(--border-color);
                font-size: 0.8rem; font-weight: 700; color: var(--text-primary);
            }
            .bell-dropdown-item {
                padding: 0.75rem 1rem; display: flex; gap: 0.75rem; align-items: flex-start;
                border-bottom: 1px solid rgba(51,65,85,0.3); transition: background 0.15s;
                text-decoration: none; color: inherit;
            }
            .bell-dropdown-item:hover { background: rgba(137,145,194,0.05); }
            .bell-dropdown-item .bell-icon {
                width: 32px; height: 32px; border-radius: 8px;
                display: flex; align-items: center; justify-content: center;
                flex-shrink: 0; font-size: 0.9rem;
            }
            .bell-dropdown-empty {
                padding: 2rem 1rem; text-align: center;
                color: var(--text-muted); font-size: 0.8rem;
            }

            /* Command Palette */
            .cmd-overlay {
                position: fixed; inset: 0; background: rgba(0,0,0,0.6);
                backdrop-filter: blur(4px); z-index: 200;
                display: none; align-items: flex-start; justify-content: center;
                padding-top: 15vh;
            }
            .cmd-overlay.show { display: flex; animation: fadeIn 0.15s ease; }
            .cmd-dialog {
                width: 560px; max-width: 90vw; background: var(--bg-card);
                border: 1px solid var(--border-color); border-radius: 16px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.5);
                overflow: hidden; animation: slideUp 0.2s ease;
            }
            .cmd-input-wrap {
                display: flex; align-items: center; gap: 0.75rem;
                padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-color);
            }
            .cmd-input-wrap svg { width: 20px; height: 20px; color: var(--text-muted); flex-shrink: 0; }
            .cmd-input {
                flex: 1; background: none; border: none; outline: none;
                color: var(--text-primary); font-size: 1rem; font-family: inherit;
            }
            .cmd-input::placeholder { color: var(--text-muted); }
            .cmd-results { max-height: 350px; overflow-y: auto; padding: 0.5rem; }
            .cmd-item {
                display: flex; align-items: center; gap: 0.75rem;
                padding: 0.6rem 0.75rem; border-radius: 8px;
                color: var(--text-secondary); text-decoration: none;
                font-size: 0.85rem; transition: all 0.1s; cursor: pointer;
            }
            .cmd-item:hover, .cmd-item.active { background: rgba(137,145,194,0.15); color: var(--text-primary); }
            .cmd-item svg { width: 18px; height: 18px; color: var(--text-muted); flex-shrink: 0; }
            .cmd-item .cmd-item-shortcut {
                margin-left: auto; font-size: 0.65rem; color: var(--text-muted);
                background: var(--bg-darker); padding: 0.15rem 0.4rem; border-radius: 4px;
            }
            .cmd-footer {
                padding: 0.5rem 1rem; border-top: 1px solid var(--border-color);
                font-size: 0.65rem; color: var(--text-muted);
                display: flex; gap: 1rem;
            }
            .cmd-footer kbd {
                font-size: 0.6rem; padding: 0.1rem 0.3rem; border-radius: 3px;
                background: var(--bg-darker); border: 1px solid var(--border-color);
            }

            /* --- MICRO-ANIMATIONS & TRANSITIONS --- */
            html { scroll-behavior: smooth; }
            button, .btn, .nav-link, a { transition: background-color 0.2s, color 0.2s, border-color 0.2s, transform 0.2s cubic-bezier(0.4,0,0.2,1); }
            button:active:not(:disabled), .btn:active:not(:disabled) { transform: scale(0.98); }

            .content-area { animation: contentFadeIn 0.5s cubic-bezier(0.4,0,0.2,1) forwards; }
            @keyframes contentFadeIn {
                from { opacity: 0; transform: translateY(15px); }
                to { opacity: 1; transform: translateY(0); }
            }

            .alert {
                animation: alertDrop 0.4s cubic-bezier(0.4,0,0.2,1) forwards;
                transition: opacity 0.5s ease, transform 0.5s ease;
            }
            .alert.fade-out { opacity: 0; transform: translateY(-10px); }
            @keyframes alertDrop {
                from { opacity: 0; transform: translateY(-10px); }
                to { opacity: 1; transform: translateY(0); }
            }

            @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
            @keyframes slideUp { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
            @keyframes dropIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }

            /* Cards */
            .card {
                background: var(--bg-card); border: 1px solid var(--border-color);
                border-radius: 12px; padding: 1.25rem; transition: all 0.2s;
                overflow-x: auto; /* Prevent table stretch on mobile */
            }
            .card:hover { border-color: var(--primary); box-shadow: 0 0 20px rgba(137,145,194,0.1); }
            .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; }
            .card-title { font-size: 0.8rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }

            /* KPI Cards */
            .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }
            .kpi-card { padding: 1.25rem; position: relative; overflow: hidden; }
            
            .kpi-value { font-size: 1.75rem; font-weight: 800; margin: 0.5rem 0; color: var(--text-primary); }
            .kpi-label { font-size: 0.75rem; color: var(--text-muted); }
            .kpi-icon { width: 40px; height: 40px; border-radius: 8px; display: flex; align-items: center; justify-content: center; position: relative; overflow: hidden; }
            .kpi-icon::before { content: ''; position: absolute; inset: 0; opacity: 0.15; background: currentColor; }
            .kpi-icon.green { color: var(--accent-green); }
            .kpi-icon.red { color: var(--accent-red); }
            .kpi-icon.blue { color: var(--accent-blue); }
            .kpi-icon.yellow { color: var(--accent-yellow); }

            /* Trend Badge Animation */
            .badge { display: inline-flex; align-items: center; justify-content: center; padding: 0.2rem 0.5rem; border-radius: 4px; font-weight: 600; }
            .badge-green { background: rgba(16,185,129,0.1); color: var(--accent-green); border: 1px solid rgba(16,185,129,0.2); }
            .badge-red { background: rgba(239,68,68,0.1); color: var(--accent-red); border: 1px solid rgba(239,68,68,0.2); }
            .kpi-card:hover .badge-green { animation: badgeBounce 0.4s ease; }
            .kpi-card:hover .badge-red { animation: badgeBounce 0.4s ease; }
            @keyframes badgeBounce { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-3px); } }

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
            .data-table tr:hover td { background: rgba(137, 145, 194, 0.05); color: var(--text-primary); }

            /* Badges */
            .badge {
                display: inline-flex; align-items: center; padding: 0.25rem 0.625rem;
                border-radius: 100px; font-size: 0.7rem; font-weight: 600;
            }
            .badge-green { background: rgba(16,185,129,0.15); color: var(--accent-green); }
            .badge-red { background: rgba(239,68,68,0.15); color: var(--accent-red); }
            .badge-blue { background: rgba(59,130,246,0.15); color: var(--accent-blue); }
            .badge-yellow { background: rgba(245,158,11,0.15); color: var(--accent-yellow); }

            /* Tab Menu */
            .tab-menu-container {
                display: flex; gap: 1rem; border-bottom: 1px solid var(--border-color); margin-bottom: 1.5rem; flex-wrap: wrap;
            }
            .tab-link {
                padding: 0.5rem 1rem; color: var(--text-muted); text-decoration: none; border-bottom: 2px solid transparent; font-weight: 500; font-size: 0.85rem;
                transition: all 0.2s;
            }
            .tab-link:hover { color: var(--text-primary); }
            .tab-link.active-tab {
                color: var(--accent-blue); border-bottom-color: var(--accent-blue);
            }

            /* Buttons */
            .btn {
                display: inline-flex; align-items: center; gap: 0.5rem;
                padding: 0.5rem 1.25rem; border-radius: 8px; font-size: 0.85rem;
                font-weight: 600; border: none; cursor: pointer; transition: all 0.2s;
                text-decoration: none;
            }
            .btn-primary {
                background: var(--primary);
                color: white; border: 1px solid var(--primary-dark);
            }
            .btn-primary:hover { background: var(--primary-dark); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(137,145,194,0.3); }
            .btn-danger { background: rgba(239,68,68,0.15); color: var(--accent-red); border: 1px solid rgba(239,68,68,0.3); }
            .btn-danger:hover { background: rgba(239,68,68,0.25); }
            .btn-secondary { background: var(--bg-card); color: var(--text-secondary); border: 1px solid var(--border-color); }
            .btn-secondary:hover { background: var(--bg-card-hover); color: var(--text-primary); }
            .top-actions { display:flex; align-items:center; gap:0.75rem; }

            /* Form inputs */
            .form-group { margin-bottom: 1.25rem; }
            .form-label { display: block; font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; }
            .form-input, .form-select {
                width: 100%; padding: 0.625rem 0.875rem; border-radius: 8px;
                background: var(--bg-darker); border: 1px solid var(--border-color);
                color: var(--text-primary); font-size: 0.875rem;
                transition: border-color 0.15s;
            }
            .form-input:focus, .form-select:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(137,145,194,0.1); }
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
            .upload-area:hover { border-color: var(--primary); background: rgba(137,145,194,0.05); }
            .upload-area input[type="file"] {
                position: absolute; inset: 0; opacity: 0; cursor: pointer;
            }
            .upload-icon { font-size: 3rem; margin-bottom: 1rem; }

            /* Responsive mobile */
            .mobile-toggle { display: none; }
            @media (max-width: 768px) {
                body, html { overflow-x: hidden; }
                .sidebar { transform: translateX(-100%); width: var(--sidebar-w) !important; }
                .sidebar.open { transform: translateX(0); }
                .sidebar.collapsed { transform: translateX(-100%); }
                .sidebar.collapsed.open { transform: translateX(0); width: var(--sidebar-w) !important; }
                .main-content { margin-left: 0 !important; max-width: 100vw; }
                .mobile-toggle { display: block; }
                .sidebar-collapse-btn { display: none; }
                .grid-2, .grid-3, .chart-grid { grid-template-columns: 1fr; }
                .kpi-grid { grid-template-columns: repeat(2, 1fr); }
                .content-area { padding: 1rem; width: 100%; box-sizing: border-box; }
                .top-bar { flex-wrap: wrap; padding: 0.75rem 1rem; gap: 0.5rem; justify-content: flex-start; }
                .topbar-clock { display: none; }
                .topbar-search-trigger span { display: none; }
                .topbar-search-trigger kbd { display: none; }
                .breadcrumb { display: none; }
            }
            @media (max-width: 480px) {
                .kpi-grid { grid-template-columns: 1fr; }
                .kpi-value { font-size: 1.5rem; word-break: break-word; }
                .top-actions { width: 100%; flex-wrap: wrap; }
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
            <button class="sidebar-collapse-btn" id="sidebarCollapseBtn" onclick="toggleSidebarCollapse()" title="Collapse sidebar">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
            </button>
            <div class="sidebar-logo">
                <div class="logo-icon">DV</div>
                <div>
                    <h1>DistoraVision</h1>
                    <span>Analytics Dashboard</span>
                </div>
            </div>

            <nav class="sidebar-nav">
                <!-- 1. AR PIUTANG -->
                <div class="nav-section">
                    <div class="nav-section-title" onclick="toggleSection(this)">AR Piutang <span class="section-chevron">▼</span></div>
                    @if(Auth::user()->isAdmin())
                    <a href="{{ route('ar.imports.index') }}" class="nav-link {{ request()->routeIs('ar.imports.*') ? 'active' : '' }}" title="Import AR: upload data piutang outlet dari file Excel. Data berisi daftar tagihan yang belum dibayar oleh outlet, termasuk aging dan status pembayaran.">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Import AR
                    </a>
                    @endif
                    <a href="{{ route('ar.dashboard') }}" class="nav-link {{ request()->routeIs('ar.dashboard') ? 'active' : '' }}" title="Dashboard AR (Piutang): analisa piutang outlet berdasarkan data import terbaru. Menampilkan aging analysis, top outlet bermasalah, AR per salesman, dan detail outstanding.">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21l-7-5-7 5V5a2 2 0 012-2h10a2 2 0 012 2v16z"></path></svg>
                        Dashboard AR
                    </a>
                </div>

                <!-- 2. SALES PER -->
                <div class="nav-section">
                    <div class="nav-section-title" onclick="toggleSection(this)">Sales Per <span class="section-chevron">▼</span></div>
                    @if(Auth::user()->isAdmin())
                    <a href="{{ route('sales-per.imports.index') }}" class="nav-link {{ request()->routeIs('sales-per.imports.*') ? 'active' : '' }}" title="Import Sales Per: upload file Excel data penjualan harian yang belum di-approve untuk monitoring omset salesman.">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        Import Sales Per
                    </a>
                    @endif
                    <a href="{{ route('sales-per.dashboard') }}" class="nav-link {{ request()->routeIs('sales-per.dashboard') ? 'active' : '' }}" title="Sales Per Dashboard: monitoring omset salesman dari data penjualan harian yang belum di-approve. Menampilkan ranking salesman, tren harian, dan detail pencapaian.">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                        Sales Per Dashboard
                    </a>
                    <a href="{{ route('sales-per.stock') }}" class="nav-link {{ request()->routeIs('sales-per.stock') ? 'active' : '' }}" title="Analisis Stok Gudang: monitoring stok semua gudang (BJM, BRB, BTL), coverage (SWC), aging, dan deteksi produk kritis/overstock.">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                        Stok Gudang
                    </a>
                </div>

                <!-- 3. UMUM (DATA CLOSING) -->
                <div class="nav-section">
                    <div class="nav-section-title" onclick="toggleSection(this)">Umum (Data Closing) <span class="section-chevron">▼</span></div>
                    <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'active' : '' }}" title="Dashboard Eksekutif: ringkasan performa bisnis dalam satu layar. Menampilkan KPI utama seperti total sales, return rate, margin, tren mingguan, kontribusi principal, serta narasi insight otomatis untuk membantu Anda membaca kondisi bisnis dengan cepat sebelum masuk ke analisa yang lebih detail.">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z"></path></svg>
                        Dashboard Eksekutif
                    </a>
                    @if(Auth::user()->isAdmin())
                    <a href="{{ route('imports.index') }}" class="nav-link {{ request()->routeIs('imports.*') ? 'active' : '' }}" title="Import Data Utama: pusat unggah data transaksi closing dari file CSV/Excel. Fitur ini dipakai untuk memasukkan data periodik, memantau status proses import (pending, processing, selesai, gagal), melihat jumlah baris sukses/gagal, serta melakukan pengelolaan log import agar histori data tetap terkontrol.">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
                        Import Data Utama
                    </a>
                    @endif

                    <div class="nav-section-title" style="margin-top: 1.5rem; font-size: 0.7rem; color: var(--text-muted); padding-left: 0.5rem; margin-bottom: 0.5rem;">— ANALYTICS & INTELLIGENCE</div>
                    
                    <a href="{{ route('ai-chat.index') }}" class="nav-link {{ request()->routeIs('ai-chat.*') ? 'active' : '' }}" title="Distora AI Assistant: Asisten AI pintar untuk tanya jawab data dan performa.">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                        <span style="font-weight: 600; color: var(--text-primary);">Distora AI Assistant</span>
                    </a>
                    @if(!Auth::user()->isSalesman())
                    <a href="{{ route('salesmen.index') }}" class="nav-link {{ request()->routeIs('salesmen.*') || request()->routeIs('analytics.target-tracker') ? 'active' : '' }}" title="Salesman Intelligence: Performa salesman harian dan Target Tracker.">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        Salesman Intelligence
                    </a>
                    @endif
                    
                    <a href="{{ route('outlets.index') }}" class="nav-link {{ request()->routeIs('outlets.*') || request()->routeIs('analytics.rfm') || request()->routeIs('analytics.cohort') || request()->routeIs('analytics.sleeping-outlets') ? 'active' : '' }}" title="Outlet Intelligence: Performa toko, Segmentasi RFM, Analisa Churn (Sleep), dan Retensi Cohort.">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                        Outlet Intelligence
                    </a>
                    
                    <a href="{{ route('products.index') }}" class="nav-link {{ request()->routeIs('products.*') || request()->routeIs('analytics.pareto') || request()->routeIs('analytics.cross-selling') || request()->routeIs('analytics.product-trajectory') ? 'active' : '' }}" title="Produk Intelligence: Performa SKU, Analisa Pareto 80/20, Product Trajectory, dan Peluang Keranjang.">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                        Produk Intelligence
                    </a>

                    <a href="{{ route('inventory.forecast.multi-period') }}" class="nav-link {{ request()->routeIs('inventory.forecast.*') || request()->routeIs('sales-per.stock') ? 'active' : '' }}" title="Inventory & Demand: Prediksi penjualan multi-bulan, deteksi risiko expired, dan rekomendasi restock.">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                        Inventory & Demand
                    </a>

                    @if(Auth::user()->isAdmin())
                    <a href="{{ route('analytics.margin') }}" class="nav-link {{ request()->routeIs('analytics.margin') || request()->routeIs('analytics.discount') || request()->routeIs('analytics.promo-uplift') ? 'active' : '' }}" title="Promo & Keuangan: Profitabilitas (Margin), Efektivitas Diskon, dan Evaluasi Efek Promo (ROI).">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        Promo & Keuangan
                    </a>
                    @endif

                    @if(!Auth::user()->isSalesman())
                    <a href="{{ route('principals.index') }}" class="nav-link {{ request()->routeIs('principals.*') ? 'active' : '' }}" title="Principal Intelligence: Kontribusi supplier dan brand.">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21l-7-5-7 5V5a2 2 0 012-2h10a2 2 0 012 2z"></path></svg>
                        Principal
                    </a>
                    @endif

                    <a href="{{ route('regional.index') }}" class="nav-link {{ request()->routeIs('regional.*') ? 'active' : '' }}" title="Regional Analytics: Visualisasi performa per wilayah/kota.">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        Regional
                    </a>
                    
                    @if(Auth::user()->isAdmin())
                    <a href="{{ route('analytics.report') }}" class="nav-link {{ request()->routeIs('analytics.report') ? 'active' : '' }}" title="Buku Rapor (Cetak): Laporan siap cetak/ekspor untuk rapat evaluasi." style="margin-top: 0.5rem; background: rgba(59, 130, 246, 0.1); border-left: 3px solid var(--accent-blue);">
                        <svg fill="none" stroke="var(--accent-blue)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                        <span style="color:var(--accent-blue); font-weight:bold;">Buku Rapor (Cetak)</span>
                    </a>
                    @endif

                    @if(Auth::user()->isAdmin())
                    <div class="nav-section-title" style="margin-top: 1.5rem; font-size: 0.7rem; color: var(--text-muted); padding-left: 0.5rem; margin-bottom: 0.5rem;">— SETTINGS</div>
                    <a href="{{ route('users.index') }}" class="nav-link {{ request()->routeIs('users.*') ? 'active' : '' }}" title="Kelola Pengguna: tambah, ubah role (Admin/Supervisor/Salesman).">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                        User Management
                    </a>
                    <a href="{{ route('settings.column-mapping') }}" class="nav-link {{ request()->routeIs('settings.column-mapping') ? 'active' : '' }}" title="Column Mapping: atur nama kolom Excel agar sesuai format file import Anda. Berguna jika header kolom Excel dari vendor berubah.">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.066 2.573c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.573 1.066c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.066-2.573c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                        Column Mapping
                    </a>
                    <a href="{{ route('settings.activity-logs') }}" class="nav-link {{ request()->routeIs('settings.activity-logs') ? 'active' : '' }}" title="Activity Logs: Jejak audit sistem untuk melihat riwayat aksi user.">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                        Activity Logs
                    </a>
                    <a href="{{ route('periods.index') }}" class="nav-link {{ request()->routeIs('periods.*') ? 'active' : '' }}" title="Tutup Buku: kelola periode akuntansi bulanan. Tutup buku untuk membekukan data dan mencegah perubahan retroaktif.">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                        Tutup Buku
                    </a>
                    @endif
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
                    <div>
                        <h1 class="page-title">@yield('page-title', 'Dashboard')</h1>
                        <div class="breadcrumb">
                            <a href="{{ route('dashboard') }}">🏠 Home</a>
                            @hasSection('breadcrumb-parent')
                                <span class="bc-sep">›</span>
                                @yield('breadcrumb-parent')
                            @endif
                            <span class="bc-sep">›</span>
                            <span class="bc-current">@yield('page-title', 'Dashboard')</span>
                        </div>
                    </div>
                </div>
                <div class="topbar-right">
                    {{-- Live Clock --}}
                    <div class="topbar-clock" id="topbarClock">--:--:--</div>

                    {{-- Quick Search Trigger --}}
                    <button class="topbar-search-trigger" onclick="openCommandPalette()">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                        <span>Cari halaman...</span>
                        <kbd>Ctrl+K</kbd>
                    </button>

                    {{-- Notification Bell --}}
                    @php
                        $bellAlerts = [];
                        $criticalStock = \App\Models\SalesPerStock::where('period', \App\Models\Transaction::max('period') ?? date('Y-m'))->where('swc', '<=', 2)->where('swc', '>', 0)->count();
                        $overstock = \App\Models\SalesPerStock::where('period', \App\Models\Transaction::max('period') ?? date('Y-m'))->where('swc', '>=', 12)->count();
                        $latestAr = \App\Models\ArImportLog::where('status', 'completed')->orderByDesc('report_date')->first();
                        $overdueAr = 0;
                        if ($latestAr) {
                            $overdueAr = \App\Models\ArReceivable::where('ar_import_log_id', $latestAr->id)->where('overdue_days', '>', 60)->where('ar_balance', '>', 0)->count();
                        }
                        if ($criticalStock > 0) $bellAlerts[] = ['icon' => '🔴', 'bg' => 'rgba(239,68,68,0.15)', 'title' => $criticalStock . ' SKU Stok Kritis', 'desc' => 'Produk hampir habis (SWC ≤ 2)', 'url' => route('sales-per.stock')];
                        if ($overstock > 0) $bellAlerts[] = ['icon' => '🟡', 'bg' => 'rgba(245,158,11,0.15)', 'title' => $overstock . ' SKU Overstock', 'desc' => 'Produk macet di gudang (SWC ≥ 12)', 'url' => route('sales-per.stock')];
                        if ($overdueAr > 0) $bellAlerts[] = ['icon' => '💰', 'bg' => 'rgba(239,68,68,0.15)', 'title' => $overdueAr . ' Invoice Kritis', 'desc' => 'Piutang overdue > 60 hari', 'url' => route('ar.dashboard')];
                    @endphp
                    <div class="topbar-bell" onclick="this.querySelector('.bell-dropdown').classList.toggle('show')">
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                        @if(count($bellAlerts) > 0)
                            <span class="bell-badge">{{ count($bellAlerts) }}</span>
                        @endif
                        <div class="bell-dropdown" onclick="event.stopPropagation()">
                            <div class="bell-dropdown-header">🔔 Notifikasi Sistem</div>
                            @forelse($bellAlerts as $alert)
                                <a href="{{ $alert['url'] }}" class="bell-dropdown-item">
                                    <div class="bell-icon" style="background:{{ $alert['bg'] }}">{{ $alert['icon'] }}</div>
                                    <div>
                                        <div style="font-size:0.8rem; font-weight:600; color:var(--text-primary);">{{ $alert['title'] }}</div>
                                        <div style="font-size:0.7rem; color:var(--text-muted);">{{ $alert['desc'] }}</div>
                                    </div>
                                </a>
                            @empty
                                <div class="bell-dropdown-empty">✅ Tidak ada notifikasi — semua aman!</div>
                            @endforelse
                        </div>
                    </div>

                    {{-- Divider before page actions --}}
                    @hasSection('top-bar-actions')
                    <div style="width:1px;height:24px;background:var(--border-color);"></div>
                    @endif

                    <div class="top-actions">
                        @yield('top-bar-actions')
                    </div>
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

        {{-- Command Palette Modal --}}
        <div class="cmd-overlay" id="cmdOverlay" onclick="closeCommandPalette()">
            <div class="cmd-dialog" onclick="event.stopPropagation()">
                <div class="cmd-input-wrap">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    <input type="text" class="cmd-input" id="cmdInput" placeholder="Ketik nama halaman... (contoh: pareto, stok, AR)" oninput="filterCmdResults(this.value)">
                </div>
                <div class="cmd-results" id="cmdResults"></div>
                <div class="cmd-footer">
                    <span><kbd>↑↓</kbd> Navigasi</span>
                    <span><kbd>Enter</kbd> Buka</span>
                    <span><kbd>Esc</kbd> Tutup</span>
                </div>
            </div>
        </div>

        <script>
        // === SIDEBAR COLLAPSE ===
        function toggleSidebarCollapse() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('collapsed');
            localStorage.setItem('dv_sidebar_collapsed', sidebar.classList.contains('collapsed') ? '1' : '0');
        }
        // Restore sidebar state from localStorage
        (function() {
            if (localStorage.getItem('dv_sidebar_collapsed') === '1') {
                document.getElementById('sidebar').classList.add('collapsed');
            }
        })();

        // === SECTION COLLAPSE ===
        function toggleSection(titleEl) {
            const section = titleEl.closest('.nav-section');
            section.classList.toggle('collapsed');
            // Save state
            const key = 'dv_section_' + titleEl.textContent.trim().replace(/[^a-zA-Z]/g, '');
            localStorage.setItem(key, section.classList.contains('collapsed') ? '1' : '0');
        }
        // Restore section collapse states
        document.querySelectorAll('.nav-section-title').forEach(function(el) {
            const key = 'dv_section_' + el.textContent.trim().replace(/[^a-zA-Z]/g, '');
            if (localStorage.getItem(key) === '1') {
                el.closest('.nav-section')?.classList.add('collapsed');
            }
        });

        // === LIVE CLOCK ===
        function updateClock() {
            const now = new Date();
            const days = ['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
            const day = days[now.getDay()];
            const h = String(now.getHours()).padStart(2,'0');
            const m = String(now.getMinutes()).padStart(2,'0');
            const s = String(now.getSeconds()).padStart(2,'0');
            document.getElementById('topbarClock').textContent = day + ' ' + h + ':' + m + ':' + s;
        }
        updateClock();
        setInterval(updateClock, 1000);

        // === COMMAND PALETTE ===
        const cmdPages = [
            @if(Auth::user()->isAdmin())
            { name: 'Import AR', url: '{{ route('ar.imports.index') }}', icon: '💰', section: 'AR Piutang' },
            @endif
            { name: 'Dashboard AR', url: '{{ route('ar.dashboard') }}', icon: '📊', section: 'AR Piutang' },
            @if(Auth::user()->isAdmin())
            { name: 'Import Sales Per', url: '{{ route('sales-per.imports.index') }}', icon: '📥', section: 'Sales Per' },
            @endif
            { name: 'Sales Per Dashboard', url: '{{ route('sales-per.dashboard') }}', icon: '📈', section: 'Sales Per' },
            { name: 'Stok Gudang', url: '{{ route('sales-per.stock') }}', icon: '📦', section: 'Sales Per' },
            { name: 'Dashboard Eksekutif', url: '{{ route('dashboard') }}', icon: '🏠', section: 'Umum' },
            @if(Auth::user()->isAdmin())
            { name: 'Import Data Utama', url: '{{ route('imports.index') }}', icon: '☁️', section: 'Umum' },
            @endif
            @if(!Auth::user()->isSalesman())
            { name: 'Salesman Intelligence', url: '{{ route('salesmen.index') }}', icon: '👥', section: 'Analytics' },
            @endif
            { name: 'Outlet Intelligence', url: '{{ route('outlets.index') }}', icon: '🏪', section: 'Analytics' },
            { name: 'Produk Intelligence', url: '{{ route('products.index') }}', icon: '📦', section: 'Analytics' },
            @if(Auth::user()->isAdmin())
            { name: 'Promo & Keuangan', url: '{{ route('analytics.margin') }}', icon: '💵', section: 'Analytics' },
            @endif
            @if(!Auth::user()->isSalesman())
            { name: 'Principal', url: '{{ route('principals.index') }}', icon: '🏷️', section: 'Analytics' },
            @endif
            { name: 'Regional', url: '{{ route('regional.index') }}', icon: '📍', section: 'Analytics' },
            { name: 'Pareto Analysis', url: '{{ route('analytics.pareto') }}', icon: '📊', section: 'Analytics' },
            { name: 'Cross Selling', url: '{{ route('analytics.cross-selling') }}', icon: '🛒', section: 'Analytics' },
            { name: 'Target Tracker', url: '{{ route('analytics.target-tracker') }}', icon: '🎯', section: 'Analytics' },
            { name: 'Cohort Analysis', url: '{{ route('analytics.cohort') }}', icon: '📉', section: 'Analytics' },
            { name: 'Promo Uplift', url: '{{ route('analytics.promo-uplift') }}', icon: '🚀', section: 'Analytics' },
            { name: 'Forecasting', url: '{{ route('inventory.forecast') }}', icon: '🔮', section: 'Analytics' },
            { name: 'Salesman Profitability', url: '{{ route('analytics.salesman-profitability') }}', icon: '💼', section: 'Analytics' },
            { name: 'Outlet Trajectory', url: '{{ route('analytics.outlet-trajectory') }}', icon: '📈', section: 'Analytics' },
            @if(Auth::user()->isAdmin())
            { name: 'Buku Rapor', url: '{{ route('analytics.report') }}', icon: '📄', section: 'Analytics' },
            { name: 'User Management', url: '{{ route('users.index') }}', icon: '👤', section: 'Settings' },
            { name: 'Column Mapping', url: '{{ route('settings.column-mapping') }}', icon: '⚙️', section: 'Settings' },
            { name: 'Activity Logs', url: '{{ route('settings.activity-logs') }}', icon: '📋', section: 'Settings' },
            { name: 'Tutup Buku', url: '{{ route('periods.index') }}', icon: '🔒', section: 'Settings' },
            @endif
            { name: 'TV Dashboard', url: '{{ route('tv.dashboard') }}', icon: '📺', section: 'Tools' },
        ];

        let cmdActiveIdx = 0;
        let cmdFiltered = [];

        function openCommandPalette() {
            document.getElementById('cmdOverlay').classList.add('show');
            const input = document.getElementById('cmdInput');
            input.value = '';
            input.focus();
            filterCmdResults('');
        }

        function closeCommandPalette() {
            document.getElementById('cmdOverlay').classList.remove('show');
        }

        function filterCmdResults(query) {
            const q = query.toLowerCase().trim();
            cmdFiltered = q === '' ? cmdPages : cmdPages.filter(p =>
                p.name.toLowerCase().includes(q) || p.section.toLowerCase().includes(q)
            );
            cmdActiveIdx = 0;
            renderCmdResults();
        }

        function renderCmdResults() {
            const container = document.getElementById('cmdResults');
            if (cmdFiltered.length === 0) {
                container.innerHTML = '<div style="padding:2rem;text-align:center;color:var(--text-muted);font-size:0.85rem;">Tidak ditemukan halaman yang cocok</div>';
                return;
            }
            container.innerHTML = cmdFiltered.map(function(p, i) {
                return '<a href="' + p.url + '" class="cmd-item' + (i === cmdActiveIdx ? ' active' : '') + '">' +
                    '<span style="font-size:1.1rem;">' + p.icon + '</span>' +
                    '<span>' + p.name + '</span>' +
                    '<span class="cmd-item-shortcut">' + p.section + '</span>' +
                '</a>';
            }).join('');
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl+K to open
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                openCommandPalette();
                return;
            }
            // Only handle if palette is open
            if (!document.getElementById('cmdOverlay').classList.contains('show')) return;

            if (e.key === 'Escape') {
                closeCommandPalette();
            } else if (e.key === 'ArrowDown') {
                e.preventDefault();
                cmdActiveIdx = Math.min(cmdActiveIdx + 1, cmdFiltered.length - 1);
                renderCmdResults();
                // Scroll active into view
                var active = document.querySelector('.cmd-item.active');
                if (active) active.scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                cmdActiveIdx = Math.max(cmdActiveIdx - 1, 0);
                renderCmdResults();
                var active = document.querySelector('.cmd-item.active');
                if (active) active.scrollIntoView({ block: 'nearest' });
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (cmdFiltered[cmdActiveIdx]) {
                    window.location.href = cmdFiltered[cmdActiveIdx].url;
                }
            }
        });

        // Close bell dropdown when clicking outside
        document.addEventListener('click', function(e) {
            document.querySelectorAll('.bell-dropdown.show').forEach(function(dd) {
                if (!dd.closest('.topbar-bell').contains(e.target)) {
                    dd.classList.remove('show');
                }
            });
        });

        // === AUTO-DISMISS ALERTS ===
        document.addEventListener("DOMContentLoaded", function() {
            // Auto dismiss alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.classList.add('fade-out');
                    setTimeout(() => alert.remove(), 500); // Wait for transition
                }, 5000);
            });
        });
        </script>
    </body>
</html>
