@extends('layouts.app')
@section('page-title', 'Dashboard AR (Piutang)')

@section('top-bar-actions')
<a href="{{ route('ar.imports.index') }}" class="btn btn-secondary">
    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path></svg>
    Import AR
</a>
@endsection

@section('content')
@if(!$hasData)
    <div class="card" style="text-align:center;padding:4rem;">
        <div style="font-size:3rem;margin-bottom:1rem;">💰</div>
        <h2 style="font-size:1.1rem;font-weight:700;margin-bottom:0.5rem;">Belum Ada Data AR</h2>
        <p style="color:var(--text-muted);margin-bottom:1.5rem;">Import file AR terlebih dahulu.</p>
        <a href="{{ route('ar.imports.create') }}" class="btn btn-primary">Import AR Sekarang</a>
    </div>
@else
    {{-- Source info --}}
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem;flex-wrap:wrap;gap:0.5rem;">
        <p style="font-size:0.75rem;color:var(--text-muted);">
            Data: <strong style="color:var(--text-primary);">{{ $latestImport->filename }}</strong>
            · <span class="badge badge-blue">{{ $latestImport->report_date->format('d M Y') }}</span>
            · <span class="badge badge-yellow">{{ $latestImport->sheet_name }}</span>
            @if($dateRange->min_date)
                · <span style="font-size:0.7rem;">Invoice: {{ \Carbon\Carbon::parse($dateRange->min_date)->format('d/m/Y') }} s/d {{ \Carbon\Carbon::parse($dateRange->max_date)->format('d/m/Y') }}</span>
            @endif
        </p>
        <button type="button" onclick="document.getElementById('globalFilterPanel').classList.toggle('filter-hidden')" 
            class="btn btn-secondary" style="padding:0.3rem 0.7rem;font-size:0.75rem;display:flex;align-items:center;gap:0.35rem;">
            🔍 Filter
            @if($activeFilterCount > 0)
                <span style="background:var(--accent-red);color:white;border-radius:50%;width:18px;height:18px;display:inline-flex;align-items:center;justify-content:center;font-size:0.65rem;font-weight:700;">{{ $activeFilterCount }}</span>
            @endif
        </button>
    </div>

    {{-- GLOBAL FILTER PANEL --}}
    <div id="globalFilterPanel" class="card {{ $activeFilterCount > 0 ? '' : 'filter-hidden' }}" style="margin-bottom:1rem;overflow:hidden;transition:all 0.3s ease;">
        <form method="GET" id="globalFilterForm">
            <input type="hidden" name="tab" value="{{ $tab }}">
            <div style="padding:0.75rem 1rem 0.5rem;display:flex;align-items:center;justify-content:space-between;">
                <span style="font-size:0.8rem;font-weight:600;color:var(--text-primary);">🔍 Filter Global</span>
                @if($activeFilterCount > 0)
                    <a href="{{ route('ar.dashboard', ['tab' => $tab]) }}" class="btn btn-secondary" style="padding:0.2rem 0.6rem;font-size:0.7rem;">✕ Reset Filter</a>
                @endif
            </div>
            <div style="padding:0.5rem 1rem 0.75rem;display:flex;gap:0.75rem;flex-wrap:wrap;align-items:flex-end;">
                
                {{-- Date Presets --}}
                <div style="flex:1;min-width:200px;">
                    <label style="font-size:0.7rem;color:var(--text-muted);display:block;margin-bottom:0.25rem;">📅 Tanggal Invoice</label>
                    <div style="display:flex;gap:0.25rem;flex-wrap:wrap;margin-bottom:0.35rem;">
                        @foreach([
                            'today' => 'Hari Ini',
                            'this_month' => 'Bulan Ini',
                            'last_month' => 'Bln Lalu',
                            '3_months' => '3 Bulan',
                            'this_year' => 'Tahun Ini',
                        ] as $preset => $label)
                        <button type="button" onclick="applyDatePreset('{{ $preset }}')" 
                            class="btn btn-secondary" style="padding:0.15rem 0.45rem;font-size:0.65rem;border-radius:4px;">{{ $label }}</button>
                        @endforeach
                    </div>
                    <div style="display:flex;align-items:center;gap:0.25rem;">
                        <input type="date" name="start_date" id="filterStartDate" value="{{ $filters['start_date'] ?? '' }}" class="form-input" style="padding:0.25rem 0.4rem;font-size:0.75rem;flex:1;">
                        <span style="font-size:0.7rem;color:var(--text-muted);">s/d</span>
                        <input type="date" name="end_date" id="filterEndDate" value="{{ $filters['end_date'] ?? '' }}" class="form-input" style="padding:0.25rem 0.4rem;font-size:0.75rem;flex:1;">
                    </div>
                </div>

                {{-- Branch --}}
                @if($branches->count() > 1)
                <div style="min-width:120px;">
                    <label style="font-size:0.7rem;color:var(--text-muted);display:block;margin-bottom:0.25rem;">🏢 Cabang</label>
                    <select name="branch" class="form-input" style="padding:0.25rem 0.4rem;font-size:0.75rem;width:100%;">
                        <option value="">Semua</option>
                        @foreach($branches as $b)
                            <option value="{{ $b }}" {{ $currentBranch === $b ? 'selected' : '' }}>{{ $b }}</option>
                        @endforeach
                    </select>
                </div>
                @endif

                {{-- Salesman --}}
                <div style="min-width:140px;">
                    <label style="font-size:0.7rem;color:var(--text-muted);display:block;margin-bottom:0.25rem;">👤 Salesman</label>
                    <select name="salesman" class="form-input" style="padding:0.25rem 0.4rem;font-size:0.75rem;width:100%;">
                        <option value="">Semua ({{ $salesmanList->count() }})</option>
                        @foreach($salesmanList as $s)
                            <option value="{{ $s }}" {{ ($filters['salesman'] ?? '') === $s ? 'selected' : '' }}>{{ Str::limit($s, 20) }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Principal --}}
                <div style="min-width:140px;">
                    <label style="font-size:0.7rem;color:var(--text-muted);display:block;margin-bottom:0.25rem;">🏷️ Principal</label>
                    <select name="principal" class="form-input" style="padding:0.25rem 0.4rem;font-size:0.75rem;width:100%;">
                        <option value="">Semua ({{ $principalList->count() }})</option>
                        @foreach($principalList as $p)
                            <option value="{{ $p }}" {{ ($filters['principal'] ?? '') === $p ? 'selected' : '' }}>{{ Str::limit($p, 20) }}</option>
                        @endforeach
                    </select>
                </div>

                {{-- Apply button --}}
                <div>
                    <button type="submit" class="btn btn-primary" style="padding:0.35rem 1rem;font-size:0.75rem;">Terapkan</button>
                </div>
            </div>
        </form>
    </div>

    {{-- TAB NAVIGATION --}}
    @php
    $tabs = [
        'overview' => ['icon' => '📊', 'label' => 'Ringkasan'],
        'aging' => ['icon' => '⏰', 'label' => 'Aging'],
        'credit-risk' => ['icon' => '⚠️', 'label' => 'Credit Risk'],
        'top-outlets' => ['icon' => '🏪', 'label' => 'Top Outlet'],
        'payment' => ['icon' => '💳', 'label' => 'Payment'],
        'salesman' => ['icon' => '👤', 'label' => 'Salesman'],
        'giro' => ['icon' => '🏦', 'label' => 'Giro'],
        'detail' => ['icon' => '📋', 'label' => 'Detail'],
    ];
    @endphp
    <div style="display:flex;gap:0.25rem;margin-bottom:1.5rem;flex-wrap:wrap;background:var(--bg-darker);padding:0.35rem;border-radius:10px;">
        @foreach($tabs as $key => $t)
        <a href="{{ route('ar.dashboard', array_merge(request()->only('branch', 'start_date', 'end_date', 'salesman', 'principal'), ['tab' => $key])) }}"
           style="padding:0.4rem 0.75rem;border-radius:7px;font-size:0.75rem;font-weight:500;text-decoration:none;transition:all 0.2s;
           {{ $tab === $key ? 'background:var(--primary);color:white;' : 'color:var(--text-muted);' }}"
           onmouseover="this.style.opacity='0.8'" onmouseout="this.style.opacity='1'">
            {{ $t['icon'] }} {{ $t['label'] }}
        </a>
        @endforeach
    </div>

    {{-- KPI CARDS (always visible) --}}
    <div class="kpi-grid" style="grid-template-columns:repeat(auto-fit,minmax(155px,1fr));margin-bottom:1.5rem;">
        <div class="card kpi-card" title="Rincian KPI untuk konteks data yang dipilih">
            <div class="kpi-label" title="Total nilai tagihan/piutang yang belum dibayar lunas oleh outlet.">Total Outstanding</div>
            <div class="kpi-value" style="font-size:1.15rem;">Rp {{ number_format($kpi->total_outstanding ?? 0, 0, ',', '.') }}</div>
            <div style="font-size:0.65rem;color:var(--text-muted);">{{ number_format($kpi->invoice_count ?? 0) }} invoice</div>
        </div>
        <div class="card kpi-card" title="Rincian KPI untuk konteks data yang dipilih">
            <div class="kpi-label" title="Total nilai tagihan yang pembayarannya sudah melewati batas waktu jatuh tempo.">Total Overdue</div>
            <div class="kpi-value" style="font-size:1.15rem;color:var(--accent-red);">Rp {{ number_format($kpi->total_overdue ?? 0, 0, ',', '.') }}</div>
        </div>
        <div class="card kpi-card" title="Rincian KPI untuk konteks data yang dipilih">
            <div class="kpi-label" title="Jumlah toko/outlet unik yang saat ini masih memiliki hutang ke distributor.">Outlet Berpiutang</div>
            <div class="kpi-value" style="font-size:1.15rem;">{{ number_format($kpi->outlet_count ?? 0) }}</div>
            <div style="font-size:0.65rem;color:var(--accent-yellow);">{{ $kpi->over_limit_count ?? 0 }} over limit</div>
        </div>
        <div class="card kpi-card" title="Rincian KPI untuk konteks data yang dipilih">
            <div class="kpi-label" title="Rata-rata lamanya waktu (dalam hari) keterlambatan pembayaran dari outlet yang menunggak.">Rata-rata Overdue</div>
            <div class="kpi-value" style="font-size:1.15rem;">{{ round($kpi->avg_overdue ?? 0) }} hari</div>
        </div>
        <div class="card kpi-card" title="Rincian KPI untuk konteks data yang dipilih">
            <div class="kpi-label" title="Persentase jumlah uang yang sudah berhasil ditagih dibandingkan total tagihan.">Collection Rate</div>
            @php $cr = ($kpi->total_ar_amount > 0) ? ($kpi->total_ar_paid / $kpi->total_ar_amount * 100) : 0; @endphp
            <div class="kpi-value" style="font-size:1.15rem;color:var(--accent-green);">{{ number_format($cr, 1) }}%</div>
        </div>
        <div class="card kpi-card" title="Rincian KPI untuk konteks data yang dipilih">
            <div class="kpi-label" title="Jumlah outlet yang sudah ditagih 3 kali atau lebih (CM ≥ 3) tapi belum melunasi tagihannya.">Outlet Bandel</div>
            <div class="kpi-value" style="font-size:1.15rem;color:var(--accent-red);">{{ number_format($kpi->stubborn_count ?? 0) }}</div>
            <div style="font-size:0.65rem;color:var(--text-muted);">CM ≥ 3x ditagih</div>
        </div>
    </div>

    {{-- ═══════════════ TAB CONTENT ═══════════════ --}}

    @if($tab === 'overview')
        <div class="card">
            <div class="card-header"><span class="card-title">📊 Ringkasan AR</span></div>
            <div style="padding:1.5rem;color:var(--text-secondary);line-height:2;font-size:0.85rem;">
                <p>Selamat datang di Dashboard AR. Gunakan tab di atas untuk navigasi:</p>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(250px,1fr));gap:1rem;margin-top:1rem;">
                    @foreach($tabs as $key => $t)
                        @if($key !== 'overview')
                        <a href="{{ route('ar.dashboard', ['tab' => $key]) }}" style="display:block;padding:0.75rem 1rem;border-radius:8px;border:1px solid var(--border-color);text-decoration:none;color:var(--text-primary);transition:all 0.2s;" onmouseover="this.style.borderColor='var(--primary)'" onmouseout="this.style.borderColor='var(--border-color)'">
                            <span style="font-size:1.2rem;">{{ $t['icon'] }}</span> <strong>{{ $t['label'] }}</strong>
                            <div style="font-size:0.7rem;color:var(--text-muted);margin-top:0.25rem;">
                                @switch($key)
                                    @case('aging') Lihat distribusi umur piutang @break
                                    @case('credit-risk') Outlet yang melebihi limit kredit @break
                                    @case('top-outlets') 20 outlet piutang terbesar @break
                                    @case('payment') Perilaku pembayaran & outlet bandel @break
                                    @case('salesman') Piutang per salesman @break
                                    @case('giro') Monitoring giro & bank @break
                                    @case('detail') Cari data piutang spesifik @break
                                @endswitch
                            </div>
                        </a>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>

    @elseif($tab === 'aging')
        <div class="card">
            <div class="card-header"><span class="card-title">⏰ Aging Analysis</span></div>
            <p style="padding:0.75rem 1rem 0;font-size:0.75rem;color:var(--text-muted);">Klik bucket untuk lihat detail outlet pada kategori tersebut.</p>
            <div id="agingChart" style="min-height:320px;"></div>
            <div style="display:flex;gap:0.5rem;flex-wrap:wrap;padding:0 1rem 1rem;">
                @foreach($agingBuckets as $b => $data)
                <a href="{{ route('ar.dashboard', array_merge(request()->only('branch', 'start_date', 'end_date', 'salesman', 'principal'), ['tab' => 'aging', 'bucket' => $b])) }}"
                   style="flex:1;min-width:100px;padding:0.75rem;border-radius:8px;text-decoration:none;text-align:center;border:2px solid {{ ($currentBucket ?? '') === $b ? 'var(--primary)' : 'var(--border-color)' }};background:{{ ($currentBucket ?? '') === $b ? 'rgba(99,102,241,0.1)' : 'var(--bg-darker)' }};">
                    <div style="font-size:0.7rem;color:var(--text-muted);">{{ $b === 'Current' ? 'Current' : $b.' hari' }}</div>
                    <div class="font-mono" style="font-size:1rem;font-weight:700;color:var(--text-primary);">{{ number_format($data['count']) }}</div>
                    <div class="font-mono" style="font-size:0.7rem;color:var(--accent-red);">Rp {{ number_format($data['total'], 0, ',', '.') }}</div>
                </a>
                @endforeach
            </div>
        </div>
        @if(isset($agingDetail) && $agingDetail)
        <div class="card" style="margin-top:1rem;">
            <div class="card-header"><span class="card-title">Detail: {{ $currentBucket === 'Current' ? 'Current' : $currentBucket.' hari' }}</span></div>
            <table class="data-table"><thead><tr><th title="Nama dan kode outlet tujuan">Outlet</th><th title="Salesman yang bertanggung jawab atas outlet ini">Salesman</th><th title="Nomor faktur / invoice">PFI/SN</th><th class="text-right" title="Sisa nilai piutang yang belum dibayar lunas oleh outlet">AR Balance</th><th class="text-right" title="Jumlah hari keterlambatan pembayaran dihitung dari tanggal jatuh tempo">Overdue</th><th class="text-right" title="Collection Mention: Berapa kali surat tagihan sudah dicetak/keluar dari sistem">CM</th></tr></thead>
            <tbody>
            @foreach($agingDetail as $d)
                <tr>
                    <td><div style="font-weight:600;">{{ Str::limit($d->outlet_name, 22) }}</div><div style="font-size:0.7rem;color:var(--text-muted);">{{ $d->outlet_code }}</div></td>
                    <td style="font-size:0.8rem;">{{ Str::limit($d->salesman_name, 18) }}</td>
                    <td><code style="font-size:0.7rem;padding:0.1rem 0.3rem;border-radius:3px;background:rgba(99,102,241,0.1);color:var(--primary-light);">{{ $d->pfi_sn }}</code></td>
                    <td class="text-right font-mono" style="font-weight:700;color:var(--accent-red);">{{ number_format($d->ar_balance, 0, ',', '.') }}</td>
                    <td class="text-right">@if($d->overdue_days > 90)<span class="badge badge-red">{{ $d->overdue_days }} hr</span>@elseif($d->overdue_days > 30)<span class="badge badge-yellow">{{ $d->overdue_days }} hr</span>@elseif($d->overdue_days > 0)<span class="badge badge-blue">{{ $d->overdue_days }} hr</span>@else<span class="badge badge-green">Current</span>@endif</td>
                    <td class="text-right"><span class="badge {{ $d->cm >= 3 ? 'badge-red' : 'badge-blue' }}">{{ $d->cm }}x</span></td>
                </tr>
            @endforeach
            </tbody></table>
            <div class="pagination-wrapper">{{ $agingDetail->links() }}</div>
        </div>
        @endif

    @elseif($tab === 'credit-risk')
        <div class="card">
            <div class="card-header"><span class="card-title">⚠️ Credit Risk Analysis</span></div>
            <p style="padding:0.75rem 1rem 0;font-size:0.75rem;color:var(--text-muted);">Outlet dengan utilisasi kredit tinggi. Utilisasi = AR Balance ÷ Credit Limit × 100%.</p>
            <div style="display:flex;gap:0.75rem;flex-wrap:wrap;padding:1rem;">
                @foreach(['Low' => ['🟢','var(--accent-green)'], 'Medium' => ['🔵','var(--primary-light)'], 'High' => ['🟡','var(--accent-yellow)'], 'Over Limit' => ['🔴','var(--accent-red)']] as $level => $cfg)
                <div style="flex:1;min-width:100px;padding:0.75rem;border-radius:8px;background:var(--bg-darker);text-align:center;">
                    <div style="font-size:1.2rem;">{{ $cfg[0] }}</div>
                    <div class="font-mono" style="font-size:1.3rem;font-weight:700;color:{{ $cfg[1] }};">{{ $riskLevels[$level] ?? 0 }}</div>
                    <div style="font-size:0.7rem;color:var(--text-muted);">{{ $level }}</div>
                </div>
                @endforeach
            </div>
            <table class="data-table"><thead><tr><th title="Nama dan kode outlet tujuan">Outlet</th><th title="Salesman yang bertanggung jawab atas outlet ini">Salesman</th><th class="text-right" title="Sisa nilai piutang yang belum dibayar lunas oleh outlet">AR Balance</th><th class="text-right" title="Batas maksimal piutang yang diperbolehkan untuk outlet ini">Limit</th><th class="text-right" title="Persentase pemakaian limit kredit (AR Balance ÷ Limit). Lebih dari 100% berarti Over Limit">Utilisasi</th><th class="text-right" title="Collection Mention: Berapa kali surat tagihan sudah dicetak/keluar dari sistem">CM</th></tr></thead>
            <tbody>
            @forelse($creditRisk as $cr)
                <tr>
                    <td><div style="font-weight:600;">{{ Str::limit($cr->outlet_name, 22) }}</div><div style="font-size:0.7rem;color:var(--text-muted);">{{ $cr->outlet_code }}</div></td>
                    <td style="font-size:0.8rem;">{{ Str::limit($cr->salesman_name, 15) }}</td>
                    <td class="text-right font-mono" style="color:var(--accent-red);font-weight:600;">{{ number_format($cr->total_balance, 0, ',', '.') }}</td>
                    <td class="text-right font-mono">
                        @if($cr->credit_limit > 1)
                            {{ number_format($cr->credit_limit, 0, ',', '.') }}
                        @else
                            <span style="color:var(--text-muted);">-</span>
                        @endif
                    </td>
                    <td class="text-right">
                        @if($cr->credit_limit > 1)
                            <span class="badge {{ $cr->utilization_pct > 100 ? 'badge-red' : 'badge-yellow' }}">{{ $cr->utilization_pct }}%</span>
                        @else
                            <span class="badge badge-red">Over Limit</span>
                        @endif
                    </td>
                    <td class="text-right"><span class="badge {{ $cr->max_cm >= 3 ? 'badge-red' : 'badge-blue' }}">{{ $cr->max_cm }}x</span></td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--text-muted);">Semua outlet dalam batas limit 👍</td></tr>
            @endforelse
            </tbody></table>
            <div class="pagination-wrapper">{{ $creditRisk->links() }}</div>
        </div>

    @elseif($tab === 'top-outlets')
        <div class="card">
            <div class="card-header"><span class="card-title">🏪 Outlet Piutang Terbesar</span></div>
            <p style="padding:0.75rem 1rem 0;font-size:0.75rem;color:var(--text-muted);">Ranking outlet berdasarkan total AR Balance. Ini daftar prioritas penagihan.</p>
            <table class="data-table"><thead><tr><th>#</th><th title="Nama dan kode outlet tujuan">Outlet</th><th title="Salesman yang bertanggung jawab atas outlet ini">Salesman</th><th class="text-right" title="Sisa nilai piutang yang belum dibayar lunas oleh outlet">AR Balance</th><th class="text-right">Invoices</th><th class="text-right" title="Keterlambatan terlama (Max Overdue) dari semua invoice milik outlet ini">Max OD</th><th class="text-right" title="Collection Mention: Berapa kali surat tagihan sudah dicetak/keluar dari sistem">CM</th></tr></thead>
            <tbody>
            @foreach($topOutlets as $i => $o)
                <tr>
                    <td style="font-weight:700;color:var(--text-muted);">{{ $topOutlets->firstItem() + $i }}</td>
                    <td><div style="font-weight:600;">{{ Str::limit($o->outlet_name, 22) }}</div><div style="font-size:0.7rem;color:var(--text-muted);">{{ $o->outlet_code }}</div></td>
                    <td style="font-size:0.8rem;">{{ Str::limit($o->salesman_name, 18) }}</td>
                    <td class="text-right font-mono" style="color:var(--accent-red);font-weight:700;">{{ number_format($o->total_balance, 0, ',', '.') }}</td>
                    <td class="text-right font-mono">{{ $o->invoice_count }}</td>
                    <td class="text-right">@if($o->max_overdue > 90)<span class="badge badge-red">{{ $o->max_overdue }} hr</span>@elseif($o->max_overdue > 30)<span class="badge badge-yellow">{{ $o->max_overdue }} hr</span>@else<span class="badge badge-green">{{ $o->max_overdue }} hr</span>@endif</td>
                    <td class="text-right"><span class="badge {{ $o->max_cm >= 3 ? 'badge-red' : 'badge-blue' }}">{{ $o->max_cm }}x</span></td>
                </tr>
            @endforeach
            </tbody></table>
            <div class="pagination-wrapper">{{ $topOutlets->links() }}</div>
        </div>

    @elseif($tab === 'payment')
        <div class="card" style="margin-bottom:1rem;">
            <div class="card-header"><span class="card-title">💳 Payment Behavior</span></div>
            <p style="padding:0.75rem 1rem 0;font-size:0.75rem;color:var(--text-muted);">Seberapa baik outlet membayar tagihan mereka.</p>
            <div style="display:flex;gap:1rem;justify-content:center;padding:1.5rem;">
                <div style="text-align:center;padding:1rem;background:var(--bg-darker);border-radius:10px;flex:1;max-width:160px;">
                    <div style="font-size:2rem;">🔴</div>
                    <div class="font-mono" style="font-size:1.5rem;font-weight:700;color:var(--accent-red);">{{ number_format($paymentSummary->zero_pay_count ?? 0) }}</div>
                    <div style="font-size:0.75rem;color:var(--text-muted);">Belum Bayar<br>Sama Sekali</div>
                </div>
                <div style="text-align:center;padding:1rem;background:var(--bg-darker);border-radius:10px;flex:1;max-width:160px;">
                    <div style="font-size:2rem;">🟡</div>
                    <div class="font-mono" style="font-size:1.5rem;font-weight:700;color:var(--accent-yellow);">{{ number_format($paymentSummary->partial_pay_count ?? 0) }}</div>
                    <div style="font-size:0.75rem;color:var(--text-muted);">Bayar<br>Sebagian</div>
                </div>
                <div style="text-align:center;padding:1rem;background:var(--bg-darker);border-radius:10px;flex:1;max-width:160px;">
                    <div style="font-size:2rem;">🟢</div>
                    <div class="font-mono" style="font-size:1.5rem;font-weight:700;color:var(--accent-green);">{{ number_format($paymentSummary->full_pay_count ?? 0) }}</div>
                    <div style="font-size:0.75rem;color:var(--text-muted);">Lunas</div>
                </div>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><span class="card-title">🚩 Worst Payers (Outlet Paling Susah Bayar)</span></div>
            <table class="data-table"><thead><tr><th title="Nama dan kode outlet tujuan">Outlet</th><th title="Salesman yang bertanggung jawab atas outlet ini">Salesman</th><th class="text-right">Tagihan</th><th class="text-right" title="Total nilai yang sudah dibayar oleh outlet">Dibayar</th><th class="text-right" title="Total sisa tagihan (sama dengan AR Balance)">Sisa</th><th class="text-right" title="Persentase pembayaran (Total Dibayar ÷ Total Tagihan). Makin kecil, makin susah ditagih">% Bayar</th><th class="text-right" title="Collection Mention: Berapa kali surat tagihan sudah dicetak/keluar dari sistem">CM</th></tr></thead>
            <tbody>
            @forelse($worstPayers as $wp)
                <tr>
                    <td><div style="font-weight:600;">{{ Str::limit($wp->outlet_name, 22) }}</div><div style="font-size:0.7rem;color:var(--text-muted);">{{ $wp->outlet_code }}</div></td>
                    <td style="font-size:0.8rem;">{{ Str::limit($wp->salesman_name, 15) }}</td>
                    <td class="text-right font-mono">{{ number_format($wp->total_invoiced, 0, ',', '.') }}</td>
                    <td class="text-right font-mono text-green">{{ number_format($wp->total_paid, 0, ',', '.') }}</td>
                    <td class="text-right font-mono" style="color:var(--accent-red);font-weight:600;">{{ number_format($wp->total_balance, 0, ',', '.') }}</td>
                    <td class="text-right">@if($wp->payment_pct == 0)<span class="badge badge-red">0%</span>@elseif($wp->payment_pct < 50)<span class="badge badge-yellow">{{ $wp->payment_pct }}%</span>@else<span class="badge badge-green">{{ $wp->payment_pct }}%</span>@endif</td>
                    <td class="text-right"><span class="badge {{ $wp->max_cm >= 3 ? 'badge-red' : 'badge-blue' }}">{{ $wp->max_cm }}x</span></td>
                </tr>
            @empty
                <tr><td colspan="7" style="text-align:center;padding:2rem;color:var(--text-muted);">Tidak ada data</td></tr>
            @endforelse
            </tbody></table>
            <div class="pagination-wrapper">{{ $worstPayers->links() }}</div>
        </div>

    @elseif($tab === 'salesman')
        <div class="card">
            <div class="card-header"><span class="card-title">👤 AR per Salesman</span></div>
            <p style="padding:0.75rem 1rem 0;font-size:0.75rem;color:var(--text-muted);">Ranking salesman berdasarkan total piutang outlet mereka. "Bandel" = invoice dengan CM ≥ 3.</p>
            <table class="data-table"><thead><tr><th title="Salesman yang bertanggung jawab atas outlet ini">Salesman</th><th class="text-right" title="Sisa nilai piutang yang belum dibayar lunas oleh outlet">AR Balance</th><th class="text-right">Outlets</th><th class="text-right">Invoices</th><th class="text-right">Bandel</th><th class="text-right" title="Rata-rata hari keterlambatan pembayaran">Avg OD</th></tr></thead>
            <tbody>
            @forelse($salesmanAr as $s)
                <tr>
                    <td><div style="font-weight:600;">{{ $s->salesman_name ?: '-' }}</div><div style="font-size:0.7rem;color:var(--text-muted);">{{ $s->salesman_code }}</div></td>
                    <td class="text-right font-mono" style="color:var(--accent-red);font-weight:600;">{{ number_format($s->total_balance, 0, ',', '.') }}</td>
                    <td class="text-right font-mono">{{ $s->outlet_count }}</td>
                    <td class="text-right font-mono">{{ $s->invoice_count }}</td>
                    <td class="text-right">@if($s->stubborn_invoices > 0)<span class="badge badge-red">{{ $s->stubborn_invoices }}</span>@else<span class="badge badge-green">0</span>@endif</td>
                    <td class="text-right font-mono {{ $s->avg_overdue > 30 ? 'text-red' : '' }}">{{ round($s->avg_overdue) }} hr</td>
                </tr>
            @empty
                <tr><td colspan="6" style="text-align:center;padding:2rem;color:var(--text-muted);">Tidak ada data</td></tr>
            @endforelse
            </tbody></table>
        </div>

    @elseif($tab === 'giro')
        <div class="card" style="margin-bottom:1rem;">
            <div class="card-header"><span class="card-title">🏦 Giro per Bank</span></div>
            <div style="display:flex;gap:0.75rem;flex-wrap:wrap;padding:1rem;">
                @forelse($giroPerBank as $gb)
                <div style="flex:1;min-width:140px;background:var(--bg-darker);padding:0.75rem;border-radius:8px;border:1px solid var(--border-color);">
                    <div style="font-size:0.7rem;color:var(--text-muted);">{{ $gb->bank_name ?: 'Unknown' }}</div>
                    <div class="font-mono" style="font-size:1rem;font-weight:700;color:var(--text-primary);">Rp {{ number_format($gb->total_amount, 0, ',', '.') }}</div>
                    <div style="font-size:0.65rem;color:var(--text-muted);">{{ $gb->giro_count }} giro</div>
                </div>
                @empty
                <p style="color:var(--text-muted);">Tidak ada data giro</p>
                @endforelse
            </div>
        </div>
        <div class="card">
            <div class="card-header"><span class="card-title">📄 Daftar Giro</span></div>
            <table class="data-table"><thead><tr><th>Giro No</th><th title="Nama dan kode outlet tujuan">Outlet</th><th>Bank</th><th class="text-right">Amount</th><th title="Tanggal jatuh tempo pembayaran invoice">Due Date</th></tr></thead>
            <tbody>
            @forelse($giroList as $g)
                <tr>
                    <td><code style="font-size:0.75rem;padding:0.1rem 0.3rem;border-radius:3px;background:rgba(99,102,241,0.1);color:var(--primary-light);">{{ $g->giro_no }}</code></td>
                    <td><div style="font-size:0.8rem;">{{ Str::limit($g->outlet_name, 20) }}</div><div style="font-size:0.7rem;color:var(--text-muted);">{{ $g->outlet_code }}</div></td>
                    <td style="font-size:0.8rem;">{{ $g->bank_name ?: '-' }}</td>
                    <td class="text-right font-mono" style="font-weight:600;">{{ number_format($g->giro_amount, 0, ',', '.') }}</td>
                    <td style="font-size:0.8rem;">{{ $g->giro_due_date ? \Carbon\Carbon::parse($g->giro_due_date)->format('d/m/Y') : '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="5" style="text-align:center;padding:2rem;color:var(--text-muted);">Tidak ada data giro</td></tr>
            @endforelse
            </tbody></table>
            <div class="pagination-wrapper">{{ $giroList->links() }}</div>
        </div>

    @elseif($tab === 'detail')
        <div class="card">
            <div class="card-header">
                <span class="card-title">📋 Detail Piutang Outstanding</span>
                <form method="GET" style="display:flex;gap:0.5rem;">
                    <input type="hidden" name="tab" value="detail">
                    @if($currentBranch)<input type="hidden" name="branch" value="{{ $currentBranch }}">@endif
                    @foreach($filters as $fk => $fv)@if($fv)<input type="hidden" name="{{ $fk }}" value="{{ $fv }}">@endif @endforeach
                    <input type="text" name="search" class="form-input" placeholder="Cari outlet, salesman, PFI/SN..."
                        value="{{ $search }}" style="width:260px;padding:0.35rem 0.75rem;font-size:0.8rem;">
                    <button type="submit" class="btn btn-secondary" style="padding:0.35rem 0.75rem;font-size:0.8rem;">🔍</button>
                </form>
            </div>
            <div style="overflow-x:auto;">
            <table class="data-table"><thead><tr><th title="Nama dan kode outlet tujuan">Outlet</th><th title="Salesman yang bertanggung jawab atas outlet ini">Salesman</th><th title="Nomor faktur / invoice">PFI/SN</th><th title="Tanggal terbit invoice">Tgl Invoice</th><th title="Brand atau principal dari barang yang dibeli">Principal</th><th class="text-right" title="Sisa nilai piutang yang belum dibayar lunas oleh outlet">AR Balance</th><th class="text-right" title="Jumlah hari keterlambatan pembayaran dihitung dari tanggal jatuh tempo">Overdue</th><th class="text-right" title="Collection Mention: Berapa kali surat tagihan sudah dicetak/keluar dari sistem">CM</th><th title="Tanggal jatuh tempo pembayaran invoice">Due Date</th></tr></thead>
            <tbody>
            @forelse($details as $d)
                <tr>
                    <td><div style="font-weight:600;">{{ Str::limit($d->outlet_name, 22) }}</div><div style="font-size:0.7rem;color:var(--text-muted);">{{ $d->outlet_code }}</div></td>
                    <td style="font-size:0.8rem;">{{ Str::limit($d->salesman_name, 18) }}</td>
                    <td><code style="font-size:0.7rem;padding:0.1rem 0.3rem;border-radius:3px;background:rgba(99,102,241,0.1);color:var(--primary-light);">{{ $d->pfi_sn }}</code></td>
                    <td style="font-size:0.8rem;">{{ $d->doc_date?->format('d/m/Y') ?: '-' }}</td>
                    <td style="font-size:0.8rem;">{{ Str::limit($d->principal_name, 18) ?: '-' }}</td>
                    <td class="text-right font-mono" style="font-weight:700;color:var(--accent-red);">{{ number_format($d->ar_balance, 0, ',', '.') }}</td>
                    <td class="text-right">@if($d->overdue_days > 90)<span class="badge badge-red">{{ $d->overdue_days }} hr</span>@elseif($d->overdue_days > 30)<span class="badge badge-yellow">{{ $d->overdue_days }} hr</span>@elseif($d->overdue_days > 0)<span class="badge badge-blue">{{ $d->overdue_days }} hr</span>@else<span class="badge badge-green">Current</span>@endif</td>
                    <td class="text-right"><span class="badge {{ $d->cm >= 3 ? 'badge-red' : 'badge-blue' }}">{{ $d->cm }}x</span></td>
                    <td style="font-size:0.8rem;">{{ $d->due_date?->format('d/m/Y') ?: '-' }}</td>
                </tr>
            @empty
                <tr><td colspan="9" style="text-align:center;padding:2rem;color:var(--text-muted);">Tidak ada data</td></tr>
            @endforelse
            </tbody></table>
            </div>
            <div class="pagination-wrapper">{{ $details->links() }}</div>
        </div>
    @endif
@endif

{{-- Aging Chart Script --}}
@if(($hasData ?? false) && ($tab ?? '') === 'aging')
<script>
new ApexCharts(document.querySelector("#agingChart"), {
    chart: { type: 'bar', height: 300, background: 'transparent', toolbar: { show: false } },
    theme: { mode: 'dark' },
    series: [{ name: 'AR Balance', data: [{{ implode(',', array_column($agingBuckets, 'total')) }}] }],
    xaxis: { categories: ['Current', '1-30 hr', '31-60 hr', '61-90 hr', '>90 hr'], labels: { style: { colors: '#94a3b8', fontSize: '12px' } } },
    yaxis: { labels: { style: { colors: '#94a3b8' }, formatter: v => 'Rp ' + (v/1e6).toFixed(0) + 'jt' } },
    colors: ['#10b981', '#3b82f6', '#f59e0b', '#f97316', '#ef4444'],
    plotOptions: { bar: { distributed: true, borderRadius: 8, columnWidth: '55%' } },
    legend: { show: false }, grid: { borderColor: '#334155', strokeDashArray: 3 },
    tooltip: { y: { formatter: v => 'Rp ' + v.toLocaleString('id-ID') } },
    dataLabels: { enabled: true, formatter: v => (v/1e6).toFixed(0) + 'jt', style: { fontSize: '11px', colors: ['#f1f5f9'] }, offsetY: -10 }
}).render();
</script>
@endif

<script>
function applyDatePreset(preset) {
    const s = document.getElementById('filterStartDate');
    const e = document.getElementById('filterEndDate');
    const today = new Date();
    const fmt = d => d.getFullYear() + '-' + String(d.getMonth()+1).padStart(2,'0') + '-' + String(d.getDate()).padStart(2,'0');
    
    if(preset === 'today') { s.value = e.value = fmt(today); }
    else if(preset === 'this_month') { s.value = fmt(new Date(today.getFullYear(), today.getMonth(), 1)); e.value = fmt(new Date(today.getFullYear(), today.getMonth()+1, 0)); }
    else if(preset === 'last_month') { s.value = fmt(new Date(today.getFullYear(), today.getMonth()-1, 1)); e.value = fmt(new Date(today.getFullYear(), today.getMonth(), 0)); }
    else if(preset === '3_months') { s.value = fmt(new Date(today.getFullYear(), today.getMonth()-2, 1)); e.value = fmt(today); }
    else if(preset === 'this_year') { s.value = fmt(new Date(today.getFullYear(), 0, 1)); e.value = fmt(new Date(today.getFullYear(), 11, 31)); }
    
    document.getElementById('globalFilterForm').submit();
}
</script>

<style>
    .filter-hidden { max-height:0 !important; padding:0 !important; margin:0 !important; border:none !important; opacity:0; pointer-events:none; }
    #globalFilterPanel { transition: max-height 0.3s ease, opacity 0.2s ease, margin 0.2s ease; max-height: 300px; opacity:1; }
    /* Fix for giant pagination arrows */
    .pagination-wrapper svg {
        width: 1.25rem !important;
        height: 1.25rem !important;
        display: inline-block;
    }
    .pagination-wrapper nav div:first-child {
        display: none;
    }
    @media (min-width: 640px) {
        .pagination-wrapper nav div:first-child {
            display: flex;
        }
    }
</style>
@endsection

