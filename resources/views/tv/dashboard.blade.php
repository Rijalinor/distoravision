<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TV Wallboard - DistoraVision</title>
    <link href="https://fonts.bunny.net/css?family=inter:400,600,800,900&display=swap" rel="stylesheet" />
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
        body { 
            background: #070B19; /* Darkest Navy (Stripe 1) */
            color: #f8fafc; 
            overflow: hidden; /* Hide scrollbars for TV */
            height: 100vh;
            width: 100vw;
            display: flex;
            flex-direction: column;
        }

        /* Top Bar / Header */
        .header {
            padding: 1.5rem 3rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: rgba(17, 22, 51, 0.9); /* Midnight Navy (Stripe 2) */
            border-bottom: 2px solid #243156; /* Navy border */
            z-index: 10;
        }
        .header h1 {
            font-size: 2rem;
            font-weight: 900;
            background: linear-gradient(135deg, #8991c2, #a3aace); /* Lavender Blue (Stripe 4) */
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-transform: uppercase;
            letter-spacing: 2px;
        }
        .header .live-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(16, 185, 129, 0.2);
            border: 1px solid #10b981;
            padding: 0.5rem 1rem;
            border-radius: 99px;
            color: #34d399;
            font-weight: 800;
            font-size: 1.2rem;
            letter-spacing: 1px;
            animation: pulse-glow 2s infinite;
        }
        .header .live-badge .dot {
            width: 12px; height: 12px;
            background: #10b981;
            border-radius: 50%;
        }

        @keyframes pulse-glow {
            0% { box-shadow: 0 0 5px rgba(16, 185, 129, 0.4); }
            50% { box-shadow: 0 0 20px rgba(16, 185, 129, 0.8); }
            100% { box-shadow: 0 0 5px rgba(16, 185, 129, 0.4); }
        }

        /* Carousel Container */
        .carousel {
            flex: 1;
            position: relative;
            overflow: hidden;
            padding: 2rem 3rem;
        }

        .slide {
            position: absolute;
            inset: 2rem 3rem;
            opacity: 0;
            transform: scale(0.95);
            transition: all 1s cubic-bezier(0.4, 0, 0.2, 1);
            pointer-events: none;
            display: flex;
            flex-direction: column;
            justify-content: center;
            visibility: hidden;
        }

        .slide.active {
            opacity: 1;
            transform: scale(1);
            pointer-events: auto;
            visibility: visible;
        }

        /* Typography & Layouts */
        .title { font-size: 3rem; font-weight: 800; margin-bottom: 2rem; color: #94a3b8; text-transform: uppercase; text-align: center; }
        
        .kpi-container {
            display: grid;
            grid-template-columns: 1fr;
            gap: 2rem;
            text-align: center;
        }
        .mega-kpi {
            background: linear-gradient(145deg, #182046, #111633); /* Deep Navy blends */
            border: 2px solid #243156;
            border-radius: 24px;
            padding: 4rem;
            box-shadow: 0 20px 40px rgba(0,0,0,0.5);
        }
        .mega-kpi.highlight {
            border-color: #8991c2;
            box-shadow: 0 20px 50px rgba(137, 145, 194, 0.3);
            position: relative;
            overflow: hidden;
        }
        .mega-kpi.highlight::before {
            content: '';
            position: absolute;
            top: 0; left: 0; width: 100%; height: 5px;
            background: linear-gradient(90deg, #8991c2, #5a6890, #9396c6); /* Custom theme colors gradient */
        }
        .kpi-label { font-size: 2rem; font-weight: 600; color: #94a3b8; margin-bottom: 1rem; text-transform: uppercase; letter-spacing: 2px; }
        .kpi-val { font-size: 8rem; font-weight: 900; background: linear-gradient(to right, #ffffff, #cbd5e1); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .kpi-val.green { background: linear-gradient(to right, #34d399, #10b981); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .kpi-val.red { background: linear-gradient(to right, #f87171, #ef4444); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .kpi-sub { font-size: 1.5rem; color: #64748b; margin-top: 1rem; }

        .progress-bar-wrap {
            margin-top: 3rem;
            height: 3rem;
            background: #111633;
            border-radius: 99px;
            overflow: hidden;
            position: relative;
        }
        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, #8991c2, #a3aace);
            border-radius: 99px;
            width: 0%;
            transition: width 2s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }
        .progress-bar::after {
            content: '';
            position: absolute;
            top: 0; right: 0; bottom: 0; width: 50px;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4));
            animation: shine 2s infinite;
        }
        @keyframes shine { 0% { transform: translateX(-100%); } 100% { transform: translateX(100px); } }

        /* Leaderboard */
        .leaderboard {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }
        .lb-card {
            display: flex;
            align-items: center;
            background: #182046;
            padding: 1rem 1.5rem;
            border-radius: 16px;
            border-left: 6px solid #243156;
            transition: transform 0.3s;
        }
        .lb-card.rank-1 { border-left-color: #fbbf24; background: linear-gradient(to right, rgba(251,191,36,0.1), #182046); transform: scale(1.02); }
        .lb-card.rank-2 { border-left-color: #94a3b8; }
        .lb-card.rank-3 { border-left-color: #b45309; }
        .lb-rank { font-size: 2.5rem; font-weight: 900; color: #64748b; width: 65px; flex-shrink: 0; }
        .rank-1 .lb-rank { color: #fbbf24; }
        .rank-2 .lb-rank { color: #94a3b8; }
        .rank-3 .lb-rank { color: #b45309; }
        
        .lb-details { flex: 1; min-width: 0; }
        .lb-name { font-size: 1.6rem; font-weight: 800; color: white; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        
        .lb-stats {
            display: flex; gap: 1rem; margin-top: 0.25rem; font-size: 1rem; font-weight: 700; color: #94a3b8;
        }
        .stat.omset { color: #34d399; }
        .stat.target { color: #cbd5e1; }
        .stat.ar { color: #f87171; }

        .lb-progress-bar-wrap { 
            height: 10px; background: #111633; border-radius: 5px; overflow:visible; margin-top: 0.5rem; position: relative;
        }
        .lb-progress-fill { height: 100%; border-radius: 5px; background: #8991c2; transition: width 1s; }
        .rank-1 .lb-progress-fill { background: #fbbf24; }
        
        .lb-pct {
            position: absolute; right: 0; top: -1.4rem; font-size: 0.9rem; font-weight: bold; color: #cbd5e1;
        }

        /* Generic Table / Lists for Slide 3 */
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 4rem; }
        .list-box { background: #182046; border-radius: 24px; padding: 3rem; }
        .list-title { font-size: 2rem; font-weight: 800; margin-bottom: 2rem; color: #8991c2; border-bottom: 2px solid #243156; padding-bottom: 1rem; }
        .list-item { display: flex; justify-content: space-between; font-size: 1.8rem; margin-bottom: 1.5rem; border-bottom: 1px dashed #243156; padding-bottom: 1rem; }
        .list-item:last-child { border: none; margin-bottom: 0; padding-bottom: 0; }
        .list-item-name { font-weight: 600; color: #f1f5f9; }
        .list-item-val { font-weight: 800; font-family: monospace; color: #10b981; }

        /* Footer Progress */
        .footer {
            height: 6px;
            background: #111633;
            width: 100%;
        }
        .footer-progress {
            height: 100%;
            background: #8991c2;
            width: 0%;
            transition: width 1s linear;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1>DV | COMMAND CENTER ({{ $monthName }})</h1>
        <div class="live-badge">
            <div class="dot"></div> LIVE
        </div>
    </div>

    <div class="carousel">

        <!-- SLIDE 1: GLOBAL KPI -->
        <div class="slide active" id="slide-1">
            <div class="title">Global Performance</div>
            <div class="kpi-container">
                <div class="mega-kpi highlight">
                    <div class="kpi-label">Target Pencapaian MTD</div>
                    <div class="kpi-val {{ $achievementPct >= 100 ? 'green' : '' }}">
                        {{ number_format($achievementPct, 1) }}%
                    </div>
                    
                    <div class="progress-bar-wrap">
                        <div class="progress-bar" style="width: {{ min($achievementPct, 100) }}%;"></div>
                    </div>

                    <div style="display:flex; justify-content: space-between; margin-top: 1.5rem; font-size: 1.5rem; font-weight:bold;">
                        <span style="color:#64748b;">Achieved: <span style="color:white;">Rp {{ number_format($netSales / 1000000, 0, ',', '.') }} Jt</span></span>
                        <span style="color:#64748b;">Target: <span style="color:white;">Rp {{ number_format($teamTarget / 1000000, 0, ',', '.') }} Jt</span></span>
                    </div>
                </div>
            </div>
            @if($gap > 0)
                <div style="text-align:center; font-size:2rem; font-weight:800; color:#f87171; margin-top: 3rem;">
                    BENTAR LAGI! Kurang Rp {{ number_format($gap / 1000000, 0, ',', '.') }} Juta untuk Capai Target 🚀
                </div>
            @else
                <div style="text-align:center; font-size:3rem; font-weight:900; color:#34d399; margin-top: 3rem; text-transform:uppercase; animation: pulse-glow 2s infinite;">
                    🎉 TARGET TERCAPAI! LUAR BIASA TIM! 🎉
                </div>
            @endif
        </div>

        <!-- LAST SLIDE: TOP ENTITIES -->
        <div class="slide" id="slide-2">
            <div class="grid-2">
                <div class="list-box">
                    <div class="list-title">🏷️ Top 5 Principal</div>
                    @foreach($topProducts as $i => $p)
                        <div class="list-item">
                            <div class="list-item-name">{{ $i + 1 }}. {{ Str::limit($p->name, 25) }}</div>
                            <div class="list-item-val">Rp {{ number_format($p->total_sales / 1000000, 0, ',', '.') }}jt</div>
                        </div>
                    @endforeach
                </div>
                <div class="list-box">
                    <div class="list-title">🏪 Top 5 Outlet Terlaris</div>
                    @foreach($topOutlets as $i => $o)
                        <div class="list-item">
                            <div class="list-item-name">{{ $i + 1 }}. {{ Str::limit($o->name, 20) }} <span style="font-size:1rem;color:#64748b;display:block;">{{ $o->city }}</span></div>
                            <div class="list-item-val">Rp {{ number_format($o->total_sales / 1000000, 0, ',', '.') }}jt</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- SLIDE 3: ACCOUNTS RECEIVABLE (AR) SUMMARY -->
        <div class="slide" id="slide-3">
            <div class="title">📊 Accounts Receivable (Aging Piutang)</div>
            @if($arSummary)
                <div class="grid-2">
                    <div class="list-box" style="display:flex; flex-direction:column; justify-content:space-between;">
                        <div class="list-title" style="color:#f87171;">⚠️ Outstanding AR Overview</div>
                        <div class="list-item" style="font-size:2rem; margin-bottom:2rem;">
                            <span class="list-item-name">Total Piutang:</span>
                            <span class="list-item-val" style="color:#cbd5e1; font-weight:900;">Rp {{ number_format($arSummary->total_balance / 1000000, 1, ',', '.') }} Jt</span>
                        </div>
                        <div class="list-item">
                            <span class="list-item-name">Overdue (Jatuh Tempo):</span>
                            <span class="list-item-val" style="color:#ef4444;">Rp {{ number_format($arSummary->total_overdue / 1000000, 1, ',', '.') }} Jt</span>
                        </div>
                        <div class="list-item">
                            <span class="list-item-name">Jatuh Tempo > 30 Hari:</span>
                            <span class="list-item-val" style="color:#f59e0b;">Rp {{ number_format($arSummary->overdue_30 / 1000000, 1, ',', '.') }} Jt</span>
                        </div>
                        <div class="list-item">
                            <span class="list-item-name">Jatuh Tempo > 90 Hari:</span>
                            <span class="list-item-val" style="color:#ef4444; text-shadow:0 0 10px rgba(239,68,68,0.5);">Rp {{ number_format($arSummary->overdue_90 / 1000000, 1, ',', '.') }} Jt</span>
                        </div>
                    </div>
                    <div class="list-box">
                        <div class="list-title">🏪 Top 5 Outlet Outstanding AR</div>
                        @foreach($topArOutlets as $i => $outlet)
                            <div class="list-item">
                                <div class="list-item-name">
                                    {{ $i + 1 }}. {{ Str::limit($outlet->outlet_name, 22) }}
                                    <span style="font-size:1rem; color:#64748b; display:block;">Overdue: {{ $outlet->overdue_days }} Hari</span>
                                </div>
                                <div class="list-item-val" style="color:#fbbf24;">Rp {{ number_format($outlet->ar_balance / 1000000, 1, ',', '.') }} Jt</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="list-box" style="text-align:center; padding:5rem;">
                    <div style="font-size:2rem; color:var(--text-muted);">Tidak ada data Piutang Outstanding saat ini.</div>
                </div>
            @endif
        </div>

    </div>

    <!-- Slide Timeline Indicator -->
    <div class="footer">
        <div class="footer-progress" id="slideTimer"></div>
    </div>

    <script>
        const SLIDE_DURATION = 15000; // 15 seconds per slide
        const REFRESH_INTERVAL = 5 * 60 * 1000; // 5 minutes whole page reload to get new DB data
        
        let currentSlide = 1;
        const slides = document.querySelectorAll('.slide');
        const totalSlides = slides.length;
        
        function showSlide(index) {
            const slideEl = document.getElementById('slide-' + index);
            if (!slideEl) return;

            document.querySelectorAll('.slide').forEach(s => s.classList.remove('active'));
            slideEl.classList.add('active');
            
            // Reset timer bar
            const timer = document.getElementById('slideTimer');
            if (timer) {
                timer.style.transition = 'none';
                timer.style.width = '0%';
                
                // Force reflow
                void timer.offsetWidth;
                
                // Start timer bar animation
                timer.style.transition = `width ${SLIDE_DURATION}ms linear`;
                timer.style.width = '100%';
            }
        }
        
        function nextSlide() {
            currentSlide++;
            if(currentSlide > totalSlides) currentSlide = 1;
            showSlide(currentSlide);
        }

        // Initialize First Slide
        showSlide(1);

        setInterval(nextSlide, SLIDE_DURATION);
        
        // Full Page Reload Fetching New Data
        setTimeout(() => {
            window.location.reload();
        }, REFRESH_INTERVAL);

        // Optional: Confetti Effect if Target Reached (Slide 1)
        @if($achievementPct >= 100)
            // Can embed lightweight confetti script here if needed.
        @endif
    </script>
</body>
</html>
