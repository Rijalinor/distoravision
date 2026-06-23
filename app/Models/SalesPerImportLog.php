<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SalesPerImportLog extends Model
{
    protected $fillable = [
        'user_id', 'filename', 'period', 'status',
        'total_rows', 'imported_rows', 'skipped_rows', 'failed_rows',
        'errors', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * The user who initiated this import.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * All sales transactions created from this import batch.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(SalesPerTransaction::class);
    }

    /**
     * All stock records created from this import batch.
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(SalesPerStock::class);
    }
}
