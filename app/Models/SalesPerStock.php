<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SalesPerStock extends Model
{
    protected static function booted(): void
    {
        // === ACL GLOBAL SCOPES ===
        static::addGlobalScope('acl', function (Builder $builder) {
            if (auth()->check()) {
                $user = auth()->user();
                if ($user->isSupervisor()) {
                    $principalNames = $user->principals()->pluck('name');
                    if ($principalNames->isNotEmpty()) {
                        $builder->whereIn($builder->qualifyColumn('principal_name'), $principalNames);
                    } else {
                        $builder->whereRaw('0 = 1');
                    }
                }
            }
        });
    }

    protected $fillable = [
        'sales_per_import_log_id', 'principal_code', 'principal_name',
        'warehouse_code', 'warehouse_name', 'item_no', 'item_name', 'size',
        'on_hand_base', 'on_sales_base',
        'stock_value_on_hand', 'stock_value_on_sales',
        'was', 'swc', 'age_of_goods', 'period',
    ];

    protected $casts = [
        'stock_value_on_hand' => 'decimal:4',
        'stock_value_on_sales' => 'decimal:4',
        'was' => 'decimal:4',
        'swc' => 'decimal:2',
    ];

    public function importLog()
    {
        return $this->belongsTo(SalesPerImportLog::class, 'sales_per_import_log_id');
    }
}
