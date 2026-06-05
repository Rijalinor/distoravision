<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function transactions()
    {
        return $this->hasMany(SalesPerTransaction::class);
    }
}
