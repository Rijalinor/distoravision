<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesPerStock extends Model
{
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
