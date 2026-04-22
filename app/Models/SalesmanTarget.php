<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

class SalesmanTarget extends Model
{
    use LogsActivity;

    protected $fillable = ['salesman_id', 'period', 'target_amount'];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->setDescriptionForEvent(fn(string $eventName) => "Salesman Target has been {$eventName}");
    }

    public function salesman(): BelongsTo
    {
        return $this->belongsTo(Salesman::class);
    }
}
