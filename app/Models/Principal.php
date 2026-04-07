<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Principal extends Model
{
    protected $fillable = ['code', 'name'];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'product_id')
            ->whereHas('product', fn($q) => $q->where('principal_id', $this->id));
    }
}
