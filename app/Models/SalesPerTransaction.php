<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesPerTransaction extends Model
{
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
