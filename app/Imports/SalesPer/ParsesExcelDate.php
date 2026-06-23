<?php

namespace App\Imports\SalesPer;

use Carbon\Carbon;

/**
 * Shared trait for parsing Excel date values across Sales Per import sheets.
 * Handles both Excel serial date numbers and string date formats.
 */
trait ParsesExcelDate
{
    protected function parseExcelDate($value): ?string
    {
        if (empty($value)) {
            return null;
        }
        try {
            if (is_numeric($value)) {
                // Excel serial date number
                return Carbon::createFromFormat('Y-m-d', gmdate('Y-m-d', ($value - 25569) * 86400))->format('Y-m-d');
            }

            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }
}
