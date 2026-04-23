<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class AccountingPeriod extends Model
{
    protected $fillable = [
        'year', 'month', 'status', 'closed_at', 'closed_by', 'notes',
    ];

    protected $casts = [
        'year' => 'integer',
        'month' => 'integer',
        'closed_at' => 'datetime',
    ];

    // ── Relationships ──────────────────────────────────────────

    public function closedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function snapshot(): HasOne
    {
        return $this->hasOne(ClosingSnapshot::class);
    }

    // ── Helpers ────────────────────────────────────────────────

    public function isClosed(): bool
    {
        return $this->status === 'closed';
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    /**
     * Get human-readable label, e.g. "April 2026"
     */
    public function getLabelAttribute(): string
    {
        return Carbon::createFromDate($this->year, $this->month, 1)
            ->translatedFormat('F Y');
    }

    /**
     * Get YYYY-MM format matching Transaction.period
     */
    public function getFormatPeriodAttribute(): string
    {
        return sprintf('%04d-%02d', $this->year, $this->month);
    }

    // ── Scopes ─────────────────────────────────────────────────

    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    /**
     * Find or create a period for the given date.
     */
    public static function findOrCreateForDate($date): self
    {
        $date = Carbon::parse($date);
        return static::firstOrCreate(
            ['year' => $date->year, 'month' => $date->month],
            ['status' => 'open']
        );
    }

    /**
     * Check if a given period string (YYYY-MM) or date is closed.
     */
    public static function isPeriodClosed($periodOrDate): bool
    {
        if (str_contains($periodOrDate, '-') && strlen($periodOrDate) === 7) {
            // YYYY-MM format
            [$year, $month] = explode('-', $periodOrDate);
        } else {
            $date = Carbon::parse($periodOrDate);
            $year = $date->year;
            $month = $date->month;
        }

        $period = static::where('year', (int) $year)
            ->where('month', (int) $month)
            ->first();

        return $period ? $period->isClosed() : false;
    }
}
