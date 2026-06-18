<?php

namespace App\Models;

use App\Traits\FilterableTransactions;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    use FilterableTransactions;

    protected static function booted(): void
    {
        // === ACL GLOBAL SCOPES ===
        static::addGlobalScope('acl', function (Builder $builder) {
            if (auth()->check()) {
                $user = auth()->user();
                if ($user->isSalesman() && $user->salesman_id) {
                    $builder->where($builder->qualifyColumn('salesman_id'), $user->salesman_id);
                } elseif ($user->isSupervisor()) {
                    $productIds = Product::whereIn('principal_id', $user->principals()->pluck('principals.id'))->pluck('id');
                    $builder->whereIn($builder->qualifyColumn('product_id'), $productIds);
                }
            }
        });
    }

    protected $fillable = [
        'branch_id', 'salesman_id', 'outlet_id', 'product_id',
        'type', 'so_no', 'so_date', 'ref_no', 'pfi_cn_no', 'pfi_cn_date',
        'gi_gr_no', 'gi_gr_date', 'si_cn_no', 'month', 'week', 'warehouse',
        'tax_invoice', 'qty_base', 'price_base', 'gross', 'disc_total',
        'taxed_amt', 'vat', 'ar_amt', 'cogs', 'period',
        'import_log_id', 'dedupe_key',
    ];

    protected $casts = [
        'so_date' => 'date',
        'pfi_cn_date' => 'date',
        'gi_gr_date' => 'date',
        'qty_base' => 'integer',
        'price_base' => 'decimal:4',
        'gross' => 'decimal:4',
        'disc_total' => 'decimal:4',
        'taxed_amt' => 'decimal:4',
        'vat' => 'decimal:4',
        'ar_amt' => 'decimal:4',
        'cogs' => 'decimal:4',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function salesman(): BelongsTo
    {
        return $this->belongsTo(Salesman::class);
    }

    public function outlet(): BelongsTo
    {
        return $this->belongsTo(Outlet::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function scopeInvoices($query)
    {
        return $query->where('type', 'I');
    }

    public function scopeReturns($query)
    {
        return $query->where('type', 'R');
    }

    public function scopeForPeriod($query, string $period)
    {
        return $query->where('period', $period);
    }
}
