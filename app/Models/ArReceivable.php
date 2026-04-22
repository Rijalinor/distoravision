<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArReceivable extends Model
{
    protected static function booted(): void
    {
        // === ACL GLOBAL SCOPES ===
        static::addGlobalScope('acl', function (\Illuminate\Database\Eloquent\Builder $builder) {
            if (auth()->check()) {
                $user = auth()->user();
                if ($user->isSalesman() && $user->salesman) {
                    $builder->where($builder->qualifyColumn('salesman_name'), $user->salesman->name);
                } elseif ($user->isSupervisor()) {
                    $principalNames = $user->principals()->pluck('name');
                    if ($principalNames->isNotEmpty()) {
                        $builder->whereIn($builder->qualifyColumn('principal_name'), $principalNames);
                    } else {
                        // If supervisor has no principals assigned, return nothing
                        $builder->whereRaw('0 = 1');
                    }
                }
            }
        });
    }

    protected $fillable = [
        'ar_import_log_id', 'outlet_code', 'outlet_name', 'outlet_ref',
        'supervisor', 'salesman_code', 'salesman_name',
        'principal_code', 'principal_name',
        'pfi_sn', 'doc_date', 'due_date', 'inv_exchange_date', 'top', 'si_cn',
        'cm', 'cn_balance', 'ar_amount', 'ar_paid', 'ar_balance', 'credit_limit',
        'paid_date', 'overdue_days',
        'giro_no', 'bank_code', 'bank_name', 'giro_amount', 'giro_due_date',
        'branch_sheet',
    ];

    protected $casts = [
        'doc_date' => 'date',
        'due_date' => 'date',
        'inv_exchange_date' => 'date',
        'paid_date' => 'date',
        'giro_due_date' => 'date',
        'cn_balance' => 'decimal:2',
        'ar_amount' => 'decimal:2',
        'ar_paid' => 'decimal:2',
        'ar_balance' => 'decimal:2',
        'credit_limit' => 'decimal:2',
        'giro_amount' => 'decimal:2',
        'overdue_days' => 'integer',
    ];

    public function importLog(): BelongsTo
    {
        return $this->belongsTo(ArImportLog::class, 'ar_import_log_id');
    }

    /**
     * Scope: only rows with outstanding balance
     */
    public function scopeOutstanding($query)
    {
        return $query->where('ar_balance', '>', 0);
    }

    /**
     * Scope: only overdue rows
     */
    public function scopeOverdue($query)
    {
        return $query->where('overdue_days', '>', 0)->where('ar_balance', '>', 0);
    }

    /**
     * Scope: filter by branch sheet
     */
    public function scopeForBranch($query, string $branch)
    {
        return $query->where('branch_sheet', $branch);
    }

    /**
     * Get aging bucket label
     */
    public function getAgingBucketAttribute(): string
    {
        if ($this->overdue_days <= 0) return 'Current';
        if ($this->overdue_days <= 30) return '1-30';
        if ($this->overdue_days <= 60) return '31-60';
        if ($this->overdue_days <= 90) return '61-90';
        return '>90';
    }
}
