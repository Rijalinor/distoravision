<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesmanTarget extends Model
{
    protected $fillable = ['salesman_id', 'period', 'target_amount'];

    public function salesman(): BelongsTo
    {
        return $this->belongsTo(Salesman::class);
    }
}
