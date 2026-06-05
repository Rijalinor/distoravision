<div class="card" style="margin-bottom: 1.5rem;">
    <div class="card-header"><span class="card-title">⏱️ Performa DSO (Days Sales Outstanding)</span></div>
    <p style="padding:0.75rem 1rem 0;font-size:0.75rem;color:var(--text-muted);">DSO mengukur rata-rata hari yang dibutuhkan outlet untuk membayar invoice. Semakin kecil nilainya, semakin cepat pembayaran diterima.</p>
    
    <div style="display:flex;gap:1.5rem;padding:1rem;flex-wrap:wrap;">
        <div style="flex:1;min-width:250px;">
            <div style="font-size:0.8rem;color:var(--text-muted);font-weight:600;margin-bottom:0.5rem;">DSO RATA-RATA (GLOBAL)</div>
            <div class="font-mono" style="font-size:2.5rem;font-weight:800;color:var(--accent-blue);line-height:1;">
                {{ number_format($dsoKpi->global_avg_dso ?? 0, 1) }} <span style="font-size:1rem;font-weight:500;color:var(--text-muted);">hari</span>
            </div>
            <div style="font-size:0.75rem;color:var(--text-muted);margin-top:0.5rem;">
                Dihitung dari {{ number_format($dsoKpi->total_invoices ?? 0) }} invoice aktif
            </div>
        </div>
        
        <div style="flex:2;min-width:300px;">
            <div style="font-size:0.8rem;color:var(--text-muted);font-weight:600;margin-bottom:0.5rem;">DISTRIBUSI UMUR PIUTANG (AGING)</div>
            <div id="dsoChart" style="height:120px;"></div>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr;gap:1.5rem;margin-bottom:1.5rem;">
    <!-- Salesman DSO -->
    <div class="card">
        <div class="card-header"><span class="card-title">📊 Kecepatan Penagihan per Salesman</span></div>
        <div style="overflow-x:auto;">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Salesman</th>
                        <th class="text-right">Rata-rata DSO</th>
                        <th class="text-right">Max DSO</th>
                        <th class="text-right">Piutang Kritis (>60 Hari)</th>
                        <th class="text-right">Total Outstanding</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($dsoPerSalesman as $s)
                    <tr>
                        <td>
                            <div style="font-weight:600;">{{ Str::limit($s->salesman_name, 25) }}</div>
                            <div style="font-size:0.7rem;color:var(--text-muted);">{{ $s->invoice_count }} invoices, {{ $s->outlet_count }} outlets</div>
                        </td>
                        <td class="text-right">
                            <span class="badge {{ $s->avg_dso > 45 ? 'badge-red' : ($s->avg_dso > 30 ? 'badge-yellow' : 'badge-green') }}" style="font-size:0.9rem;">
                                {{ number_format($s->avg_dso, 1) }} hari
                            </span>
                        </td>
                        <td class="text-right font-mono text-muted">{{ number_format($s->max_dso) }} hr</td>
                        <td class="text-right font-mono {{ $s->overdue_60_value > 0 ? 'text-red font-bold' : 'text-muted' }}">
                            Rp {{ number_format($s->overdue_60_value, 0, ',', '.') }}
                        </td>
                        <td class="text-right font-mono" style="font-weight:600;">Rp {{ number_format($s->total_outstanding, 0, ',', '.') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" style="text-align:center;padding:2rem;color:var(--text-muted);">Tidak ada data</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><span class="card-title">🚨 Outlet dengan Pembayaran Terlambat (Worst Payers by DSO)</span></div>
    <div style="overflow-x:auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Outlet</th>
                    <th>Salesman</th>
                    <th class="text-center">Status</th>
                    <th class="text-right">Avg DSO</th>
                    <th class="text-right">Max DSO</th>
                    <th class="text-right">% Bayar</th>
                    <th class="text-right">Outstanding</th>
                </tr>
            </thead>
            <tbody>
                @forelse($dsoPerOutlet as $o)
                <tr>
                    <td>
                        <div style="font-weight:600;">{{ Str::limit($o->outlet_name, 25) }}</div>
                        <div style="font-size:0.7rem;color:var(--text-muted);">{{ $o->outlet_code }}</div>
                    </td>
                    <td style="font-size:0.8rem;">{{ Str::limit($o->salesman_name, 18) }}</td>
                    <td class="text-center">
                        <span class="badge {{ $o->risk_color }}">{{ $o->risk_level }}</span>
                    </td>
                    <td class="text-right font-mono font-bold" style="color:{{ $o->avg_dso > 60 ? 'var(--accent-red)' : ($o->avg_dso > 30 ? 'var(--accent-yellow)' : 'var(--text-primary)') }}">
                        {{ number_format($o->avg_dso, 1) }} hr
                    </td>
                    <td class="text-right font-mono text-muted">{{ $o->max_dso }} hr</td>
                    <td class="text-right">
                        <span class="badge {{ $o->payment_rate < 50 ? 'badge-red' : ($o->payment_rate < 80 ? 'badge-yellow' : 'badge-green') }}">
                            {{ number_format($o->payment_rate, 0) }}%
                        </span>
                    </td>
                    <td class="text-right font-mono font-bold">Rp {{ number_format($o->total_outstanding, 0, ',', '.') }}</td>
                </tr>
                @empty
                <tr><td colspan="7" style="text-align:center;padding:2rem;color:var(--text-muted);">Tidak ada data</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="pagination-wrapper" style="padding:1rem;">{{ $dsoPerOutlet->links() }}</div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    new ApexCharts(document.querySelector("#dsoChart"), {
        chart: { type: 'bar', height: 120, background: 'transparent', toolbar: { show: false }, sparkline: { enabled: true } },
        theme: { mode: 'dark' },
        series: [{ name: 'Total Outstanding', data: [{{ implode(',', $dsoChartValues) }}] }],
        xaxis: { categories: {!! json_encode($dsoChartLabels) !!}, crosshairs: { width: 1 } },
        colors: ['#10b981', '#3b82f6', '#f59e0b', '#f97316', '#ef4444', '#b91c1c', '#7f1d1d'],
        plotOptions: { bar: { distributed: true, borderRadius: 4, columnWidth: '60%' } },
        tooltip: { 
            theme: 'dark',
            y: { formatter: function(val) { return 'Rp ' + val.toLocaleString('id-ID'); } },
            x: { show: true }
        }
    }).render();
});
</script>
