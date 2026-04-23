<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClosingSnapshot extends Model
{
    protected $fillable = [
        'accounting_period_id',
        // Sales
        'total_sales', 'total_returns', 'net_sales', 'total_cogs',
        'invoice_count', 'return_count', 'return_rate', 'margin',
        'top_products', 'top_outlets', 'principal_breakdown', 'salesman_sales_data',
        // AR
        'total_outstanding', 'total_overdue', 'total_ar_amount', 'total_ar_paid',
        'ar_outlet_count', 'ar_invoice_count', 'avg_overdue_days', 'max_overdue_days',
        'aging_data', 'salesman_ar_data',
        'snapshot_at',
    ];

    protected $casts = [
        'total_sales'       => 'decimal:2',
        'total_returns'     => 'decimal:2',
        'net_sales'         => 'decimal:2',
        'total_cogs'        => 'decimal:2',
        'return_rate'       => 'decimal:2',
        'margin'            => 'decimal:2',
        'total_outstanding' => 'decimal:2',
        'total_overdue'     => 'decimal:2',
        'total_ar_amount'   => 'decimal:2',
        'total_ar_paid'     => 'decimal:2',
        'top_products'         => 'array',
        'top_outlets'          => 'array',
        'principal_breakdown'  => 'array',
        'salesman_sales_data'  => 'array',
        'aging_data'           => 'array',
        'salesman_ar_data'     => 'array',
        'snapshot_at'          => 'datetime',
    ];

    public function period(): BelongsTo
    {
        return $this->belongsTo(AccountingPeriod::class, 'accounting_period_id');
    }
}
