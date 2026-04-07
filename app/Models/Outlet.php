<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Outlet extends Model
{
    protected $fillable = ['code', 'name', 'address', 'city', 'route', 'phone'];

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
