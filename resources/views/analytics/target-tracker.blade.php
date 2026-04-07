@extends('layouts.app')
@section('page-title', 'Target Tracker & Run Rate')

@section('top-bar-actions')
<div style="display:flex; gap:0.75rem; align-items:center;">
    <form method="GET" onsubmit="return sanitizeBeforeSubmit(this)" style="display:flex; gap:0.75rem; align-items:center;">
        @include('components.filter')
        <div style="position: relative;">
            <span style="position:absolute; left:0.75rem; top:50%; transform:translateY(-50%); color:var(--text-muted); font-size:0.7rem;">Target Global Rp</span>
            <input type="text" 
                   name="base_target" 
                   value="{{ number_format(request('base_target', 10000000000), 0, ',', '.') }}" 
                   class="money-mask"
                   oninput="formatInput(this)"
                   onchange="this.form.submit()"
                   style="background:var(--card-bg); border:1px solid rgba(255,255,255,0.1); color:var(--text-color); border-radius:4px; padding:0.5rem 0.5rem 0.5rem 8rem; outline:none; font-family:monospace; width: 220px; font-size: 0.85rem;">
        </div>
    </form>
    <button type="button" class="btn btn-secondary" onclick="toggleCalculator()" style="padding:0.4rem 0.75rem; font-size:0.75rem; display:flex; align-items:center; gap:0.4rem;">
        <svg width="14" height="14" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
        Kalkulator Target
    </button>
</div>
@endsection

@section('content')
@include('components.ai-insight')

<!-- CALCULATOR SECTION (HIDDEN BY DEFAULT) -->
<div id="target-calculator" class="card" style="margin-bottom: 1.5rem; display: none; border: 1px solid var(--accent-blue);">
    <div class="card-header" style="background: rgba(59, 130, 246, 0.1);">
        <span class="card-title" style="color: var(--accent-blue);">🧮 Kalkulator Target Proporsional</span>
        <button type="button" class="btn-close" onclick="toggleCalculator()" style="background:none; border:none; color:var(--text-muted); cursor:pointer;">&times;</button>
    </div>
    <div class="card-body" style="padding: 1.5rem;">
        <div style="display: grid; grid-template-columns: 1fr 300px; gap: 2rem;">
            <!-- Left: Salesman Selection -->
            <div>
                <label style="display: block; margin-bottom: 1rem; font-weight: bold; font-size: 0.9rem;">1. Pilih Salesman yang akan di-targetkan:</label>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 0.75rem; max-height: 200px; overflow-y: auto; padding: 1rem; background: rgba(0,0,0,0.2); border-radius: 8px;">
                    @foreach($tracking as $s)
                    <label style="display: flex; align-items: center; gap: 0.75rem; cursor: pointer; font-size: 0.85rem;">
                        <input type="checkbox" class="calc-salesman-check" value="{{ $s->salesman_id }}" data-ratio="{{ $s->historical_ratio }}" checked style="width: 16px; height: 16px;">
                        {{ $s->salesman_name }}
                        <span class="text-muted" style="font-size: 0.7rem;">({{ number_format($s->historical_ratio, 1) }}%)</span>
                    </label>
                    @endforeach
                </div>
                <div style="margin-top: 0.5rem; font-size: 0.75rem;">
                    <a href="javascript:void(0)" onclick="selectAllCalc(true)" style="color: var(--accent-blue); text-decoration: none; margin-right: 1rem;">Pilih Semua</a>
                    <a href="javascript:void(0)" onclick="selectAllCalc(false)" style="color: var(--text-muted); text-decoration: none;">Hapus Semua</a>
                </div>
            </div>

            <!-- Right: Target Input -->
            <div style="border-left: 1px solid rgba(255,255,255,0.1); padding-left: 2rem;">
                <label style="display: block; margin-bottom: 0.5rem; font-weight: bold; font-size: 0.9rem;">2. Input Total Target Kelompok:</label>
                <div style="position: relative; margin-bottom: 1.5rem;">
                    <span style="position:absolute; left:0.75rem; top:50%; transform:translateY(-50%); color:var(--text-muted); font-size:0.9rem;">Rp</span>
                    <input type="text" id="calc-total-target" oninput="formatInput(this)" placeholder="Contoh: 1.000.000.000" style="width: 100%; background:var(--bg-color); border:1px solid rgba(255,255,255,0.2); color:white; border-radius:4px; padding:0.75rem 0.75rem 0.75rem 2.5rem; font-size: 1.1rem; font-family: monospace;">
                </div>
                
                <button type="button" onclick="runCalculation()" class="btn btn-primary" style="width: 100%; padding: 0.75rem; font-weight: bold;">
                    Hitung & Terapkan ke Tabel ↓
                </button>
                <p style="font-size: 0.75rem; color: var(--text-muted); margin-top: 1rem; line-height: 1.4;">
                    *Sistem akan membagi angka di atas ke sales yang dipilih secara proporsional berdasarkan kontribusi riwayat 3 bulan terakhir mereka.
                </p>
            </div>
        </div>
    </div>
</div>

@if(!$isCurrentMonth)
<div class="alert alert-yellow">
    <svg width="20" height="20" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
    <div>
        <strong>Peringatan Periode Lalu.</strong> Anda sedang melihat data periode bulan berlalu ({{ $period }}). Grafik KPI di bawah merefleksikan angka akhir pada penutupan bulan tersebut.
    </div>
</div>
@endif

<div class="kpi-grid" style="grid-template-columns: repeat(4, 1fr);">
    <div class="card kpi-card" style="border-top: 4px solid var(--accent-blue);">
        @if($isCurrentMonth)
            <div class="card-title" title="Hari yang sudah berlalu dibandingkan dengan total hari kerja (Senin-Sabtu) dalam sebulan.">Hari Berjalan</div>
            <div class="kpi-value text-blue">{{ $currentDay }} / {{ $workingDays }}</div>
            <div class="kpi-label">Sisa Hari Kerja: {{ $remainingDays }} Hari</div>
        @else
            <div class="card-title">Status Periode</div>
            <div class="kpi-value text-muted" style="font-size: 1.25rem;">TELAH SELESAI</div>
            <div class="kpi-label">Laporan Akhir Bulan {{ $period }}</div>
        @endif
    </div>
    
    <div class="card kpi-card" style="border-top: 4px solid var(--accent-yellow);">
        <div class="card-title" title="Target bulanan total untuk seluruh tim Salesman.">Total Target Tim</div>
        <div class="kpi-value text-yellow">Rp {{ number_format($tracking->sum('target'), 0, ',', '.') }}</div>
        <div class="kpi-label">Akumulasi Target Individu</div>
    </div>

    <div class="card kpi-card" style="border-top: 4px solid var(--accent-green);">
        <div class="card-title" title="Total akumulasi penjualan gabungan dari seluruh salesman per hari ini (Month-To-Date).">Total Sales Tim</div>
        <div class="kpi-value text-green">Rp {{ number_format($tracking->sum('total_revenue'), 0, ',', '.') }}</div>
        <div class="kpi-label">Akumulasi Seluruh Tim</div>
    </div>

    @php
        $totalSalesMTD = $tracking->sum('total_revenue');
        $totalTargetTim = $tracking->sum('target');
        $achievementPercent = $totalTargetTim > 0 ? ($totalSalesMTD / $totalTargetTim) * 100 : 0;
        $gapPercent = max(0, 100 - $achievementPercent);
    @endphp
    <div class="card kpi-card" style="border-top: 4px solid {{ $gapPercent > 0 ? 'var(--accent-red)' : 'var(--accent-green)' }};">
        <div class="card-title">Kekurangan Target</div>
        <div class="kpi-value {{ $gapPercent > 0 ? 'text-red' : 'text-green' }}">
            {{ $gapPercent > 0 ? number_format($gapPercent, 1) . '%' : 'TERCAPAI' }}
        </div>
        <div class="kpi-label">{{ $gapPercent > 0 ? 'Lagi Untuk Capai 100%' : 'Target Tim Sudah Aman' }}</div>
    </div>
</div>

<form id="save-targets-form" action="{{ route('analytics.save-targets') }}" method="POST" onsubmit="return sanitizeBeforeSubmit(this)">
    @csrf
    <input type="hidden" name="period" value="{{ $period }}">
    
    <div class="card" style="margin-top: 1.5rem;">
        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center;">
            <span class="card-title">Papan Skor Target Bulanan Salesman</span>
            <button type="submit" class="btn btn-primary" style="padding: 0.5rem 1rem; font-weight: bold; background: var(--accent-green); border-color: var(--accent-green);">
                💾 Simpan Semua ke Database
            </button>
        </div>
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Salesman</th>
                        <th class="text-right">Kontribusi<br><span style="font-size:0.7rem; font-weight:normal;" class="text-muted">(Avg 3 Bln)</span></th>
                        <th class="text-right" style="width: 200px;">Target Sales (Rp)</th>
                        <th class="text-right">Sales MTD (Rp)</th>
                        <th style="width: 15%;">Pencapaian (%)</th>
                        <th class="text-right">Kekurangan (Shortfall)</th>
                        <th class="text-right">Req. Run Rate / Hari</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($tracking as $tracker)
                    @php
                        $isAchieved = $tracker->progress >= 100;
                        $barColor = $isAchieved ? 'var(--accent-green)' : ($tracker->progress >= 75 ? 'var(--accent-blue)' : ($tracker->progress >= 40 ? 'var(--accent-yellow)' : 'var(--accent-red)'));
                    @endphp
                    <tr>
                        <td class="font-bold">
                            {{ $tracker->salesman_name }}
                            @if($tracker->is_custom)
                                <span title="Target ini sudah tersimpan di database" style="color:var(--accent-green); font-size:0.7rem; margin-left:4px;">●</span>
                            @endif
                        </td>
                        <td class="text-right font-mono text-muted">{{ number_format($tracker->historical_ratio, 2) }}%</td>
                        <td class="text-right">
                            <div style="position: relative;">
                                <span style="position:absolute; left:0.5rem; top:50%; transform:translateY(-50%); color:var(--text-muted); font-size:0.7rem;">Rp</span>
                                <input type="text" 
                                       name="targets[{{ $tracker->salesman_id }}]" 
                                       id="target-input-{{ $tracker->salesman_id }}"
                                       value="{{ number_format($tracker->target, 0, ',', '.') }}" 
                                       class="target-input money-mask"
                                       oninput="formatInput(this)"
                                       style="width: 100%; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.1); color: var(--accent-yellow); padding: 0.4rem 0.4rem 0.4rem 2rem; border-radius: 4px; font-family: monospace; text-align: right; outline: none;">
                            </div>
                        </td>
                        
                        <td class="text-right font-mono" style="{{ $isAchieved ? 'color: var(--accent-green);' : '' }}">
                            Rp {{ number_format($tracker->total_revenue, 0, ',', '.') }}
                        </td>
                        
                        <td>
                            <div style="display:flex; align-items:center; gap:0.5rem;">
                                <div style="flex:1; background:rgba(255,255,255,0.05); height:6px; border-radius:4px; overflow:hidden;">
                                    <div style="width: {{ $tracker->progress }}%; background:{{ $barColor }}; height:100%; border-radius:4px; transition: width 0.5s ease;"></div>
                                </div>
                                <div style="font-family:monospace; font-size:0.75rem; font-weight:bold; width: 40px; text-align:right;">
                                    {{ number_format($tracker->progress, 1) }}%
                                </div>
                            </div>
                        </td>

                        <td class="text-right font-mono text-muted">
                            @if($isAchieved)
                                <span class="badge badge-green" style="font-size: 0.6rem;">ACHIEVED 🏆</span>
                            @else
                                <span style="font-size: 0.85rem;">Rp {{ number_format($tracker->shortfall, 0, ',', '.') }}</span>
                            @endif
                        </td>

                        <td class="text-right font-mono font-bold" style="{{ !$isAchieved ? 'color: var(--accent-yellow);' : 'color: var(--accent-green);' }}">
                            @if($isAchieved)
                                <span style="font-size: 0.8rem;">✓ Aman</span>
                            @else
                                <span style="font-size: 0.85rem;">Rp {{ number_format($tracker->required_run_rate, 0, ',', '.') }}</span>
                            @endif
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="card-footer" style="padding: 1rem; text-align: center; background: rgba(0,0,0,0.1);">
            <p class="text-muted" style="font-size: 0.75rem; margin-bottom: 0.5rem;">
                💡 Anda bisa mengubah target secara manual langsung di dalam tabel. Jangan lupa klik tombol **Simpan** untuk mengunci angka tersebut ke database.
            </p>
        </div>
    </div>
</form>

<script>
    function toggleCalculator() {
        const calc = document.getElementById('target-calculator');
        calc.style.display = (calc.style.display === 'none') ? 'block' : 'none';
        if(calc.style.display === 'block') {
            calc.scrollIntoView({ behavior: 'smooth' });
        }
    }

    function selectAllCalc(val) {
        document.querySelectorAll('.calc-salesman-check').forEach(c => c.checked = val);
    }

    /* Formatting logic */
    function formatInput(el) {
        let val = el.value.replace(/\D/g, "");
        if (val === "") {
            el.value = "";
            return;
        }
        el.value = new Intl.NumberFormat('id-ID').format(val);
    }

    function sanitizeBeforeSubmit(form) {
        // Strip dots before submitting so PHP gets raw numbers
        form.querySelectorAll('.money-mask').forEach(input => {
            input.value = input.value.replace(/\./g, "");
        });
        return true;
    }

    function runCalculation() {
        const totalRaw = document.getElementById('calc-total-target').value.replace(/\./g, "");
        const totalInput = parseInt(totalRaw);

        if (!totalInput || totalInput <= 0) {
            alert('Silakan masukkan angka total target terlebih dahulu!');
            return;
        }

        const selectedChecks = Array.from(document.querySelectorAll('.calc-salesman-check:checked'));
        if (selectedChecks.length === 0) {
            alert('Silakan pilih setidaknya satu salesman!');
            return;
        }

        let totalSelectedRatio = 0;
        selectedChecks.forEach(c => {
            totalSelectedRatio += parseFloat(c.dataset.ratio);
        });

        if (totalSelectedRatio <= 0) totalSelectedRatio = selectedChecks.length;

        selectedChecks.forEach(c => {
            const salesmanId = c.value;
            const ratio = parseFloat(c.dataset.ratio);
            const normalizedRatio = ratio / totalSelectedRatio;
            const individualTarget = Math.round(normalizedRatio * totalInput);
            
            const inputField = document.getElementById('target-input-' + salesmanId);
            if (inputField) {
                inputField.value = new Intl.NumberFormat('id-ID').format(individualTarget);
                inputField.style.backgroundColor = 'rgba(59, 130, 246, 0.2)';
                inputField.style.borderColor = 'var(--accent-blue)';
            }
        });

        alert('Target berhasil dihitung dan diterapkan ke tabel sementara. Klik "Simpan Semua ke Database" untuk mengunci angka ini.');
    }
</script>

<style>
    .target-input {
        transition: all 0.3s;
    }
    .btn-close {
        font-size: 1.5rem;
        font-weight: bold;
        opacity: 0.5;
        transition: opacity 0.2s;
    }
    .btn-close:hover {
        opacity: 1;
    }
</style>
@endsection

