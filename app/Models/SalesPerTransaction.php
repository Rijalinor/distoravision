<?php

namespace App\Models;

use App\Traits\ScopesDemoMode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SalesPerTransaction extends Model
{
    use ScopesDemoMode;

    protected static function booted(): void
    {
        // === ACL GLOBAL SCOPES ===
        static::addGlobalScope('acl', function (Builder $builder) {
            if (auth()->check()) {
                $user = auth()->user();
                if ($user->isSalesman() && $user->salesman) {
                    $builder->where($builder->qualifyColumn('sales_code'), $user->salesman->sales_code);
                } elseif ($user->isSupervisor()) {
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
        'sales_per_import_log_id', 'type',
        'branch_code', 'sales_code', 'sales_name',
        'outlet_code', 'outlet_name',
        'principal_code', 'principal_name',
        'item_no', 'item_name',
        'so_no', 'pfi_no', 'so_date',
        'qty', 'subtotal', 'vat', 'period',
    ];

    protected $casts = [
        'so_date' => 'date',
        'subtotal' => 'decimal:4',
        'vat' => 'decimal:4',
    ];

    public function importLog()
    {
        return $this->belongsTo(SalesPerImportLog::class, 'sales_per_import_log_id');
    }

    public function scopeInvoices($query)
    {
        return $query->where('type', 'I');
    }

    public function scopeReturns($query)
    {
        return $query->where('type', 'R');
    }
}
