<?php

namespace App\Support;

use App\Models\ArImportLog;
use App\Models\ArReceivable;
use App\Models\SalesPerStock;
use App\Models\Transaction;
use Illuminate\Support\Facades\Cache;

class AnalyticsCache
{
    private const VERSION_KEY = 'analytics_cache_version';

    public static function version(): int
    {
        Cache::add(self::VERSION_KEY, 1);

        return (int) Cache::get(self::VERSION_KEY, 1);
    }

    public static function key(string $name, array $parts = []): string
    {
        $suffix = collect($parts)
            ->map(fn ($part) => is_bool($part) ? (int) $part : (string) $part)
            ->implode(':');

        return 'analytics:v'.self::version().':'.$name.($suffix !== '' ? ':'.$suffix : '');
    }

    public static function invalidate(): void
    {
        Cache::add(self::VERSION_KEY, 1);
        Cache::increment(self::VERSION_KEY);
    }

    /**
     * @return array<int, array{icon: string, bg: string, title: string, desc: string, url: string}>
     */
    public static function bellAlerts(?int $userId): array
    {
        return Cache::remember(self::key('bell_alerts', [$userId ?? 'guest']), 300, function () {
            $period = Transaction::max('period') ?? date('Y-m');

            $criticalStock = SalesPerStock::where('period', $period)
                ->where('swc', '<=', 2)
                ->where('swc', '>', 0)
                ->count();

            $overstock = SalesPerStock::where('period', $period)
                ->where('swc', '>=', 12)
                ->count();

            $latestAr = ArImportLog::where('status', 'completed')->orderByDesc('report_date')->first();
            $overdueAr = $latestAr
                ? ArReceivable::where('ar_import_log_id', $latestAr->id)
                    ->where('overdue_days', '>', 60)
                    ->where('ar_balance', '>', 0)
                    ->count()
                : 0;

            $alerts = [];
            if ($criticalStock > 0) {
                $alerts[] = ['icon' => '!', 'bg' => 'rgba(239,68,68,0.15)', 'title' => $criticalStock.' SKU Stok Kritis', 'desc' => 'Produk hampir habis (SWC <= 2)', 'url' => route('sales-per.stock')];
            }
            if ($overstock > 0) {
                $alerts[] = ['icon' => '!', 'bg' => 'rgba(245,158,11,0.15)', 'title' => $overstock.' SKU Overstock', 'desc' => 'Produk macet di gudang (SWC >= 12)', 'url' => route('sales-per.stock')];
            }
            if ($overdueAr > 0) {
                $alerts[] = ['icon' => 'Rp', 'bg' => 'rgba(239,68,68,0.15)', 'title' => $overdueAr.' Invoice Kritis', 'desc' => 'Piutang overdue > 60 hari', 'url' => route('ar.dashboard')];
            }

            return $alerts;
        });
    }
}
