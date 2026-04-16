<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ArImportLog extends Model
{
    protected $fillable = [
        'user_id', 'filename', 'report_date', 'sheet_name',
        'total_rows', 'imported_rows', 'failed_rows',
        'status', 'errors', 'started_at', 'completed_at',
    ];

    protected $casts = [
        'report_date' => 'date',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function receivables(): HasMany
    {
        return $this->hasMany(ArReceivable::class);
    }
}
