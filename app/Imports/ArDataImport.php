<?php

namespace App\Imports;

use App\Models\ArImportLog;
use App\Models\ArReceivable;
use Carbon\Carbon;

class ArDataImport
{
    protected ArImportLog $importLog;
    protected string $sheetName;
    protected array $columnMap = [];

    public function __construct(ArImportLog $importLog, string $sheetName)
    {
        $this->importLog = $importLog;
        $this->sheetName = $sheetName;
        $this->columnMap = config('import_columns', []);
    }

    /**
     * Get a value from a mapped row using config.
     * Public so the Job can call it for skip-check.
     */
    public function getColumnValue(array $row, string $key, $default = null)
    {
        $excelColumn = $this->columnMap["ar_{$key}"] ?? $this->columnMap[$key] ?? $key;
        return $row[$excelColumn] ?? $default;
    }

    /**
     * Process a single row of data (already mapped to header keys).
     * Public so the Job can call it.
     */
    public function processRowData(array $row): void
    {
        $pfiSn = trim((string) ($this->getColumnValue($row, 'pfi_sn', '') ?? ''));
        $outletCode = trim((string) ($this->getColumnValue($row, 'outlet_id', '') ?? ''));

        if ($pfiSn === '') {
            throw new \InvalidArgumentException("PFI/SN wajib diisi.");
        }

        $principalCode = trim((string) $this->getColumnValue($row, 'ar_principle'));
        if ($principalCode === '') $principalCode = 'UNKNOWN';

        $principalName = trim((string) $this->getColumnValue($row, 'ar_principle_name'));
        if ($principalName === '') $principalName = 'UNKNOWN PRINCIPAL';

        $supervisor = trim((string) $this->getColumnValue($row, 'supervisor'));
        if ($supervisor === '') $supervisor = 'UNASSIGNED';

        $docDateStr = $this->parseExcelDate($this->getColumnValue($row, 'doc_date'));
        $dueDateStr = $this->parseExcelDate($this->getColumnValue($row, 'due_date'));
        
        $topValue = $this->getColumnValue($row, 'top');
        $top = ($topValue !== null && $topValue !== '') ? (int) $topValue : null;

        // Auto-patch due date if missing
        if (empty($dueDateStr) && !empty($docDateStr) && $top !== null) {
            $dueDateStr = Carbon::parse($docDateStr)->addDays($top)->format('Y-m-d');
        }

        $overdueDays = (int) ($this->getColumnValue($row, 'overdue_days', 0) ?? 0);
        // Recalculate overdue days if it's 0 but due_date is past the report date
        if ($overdueDays <= 0 && !empty($dueDateStr)) {
            $reportDate = Carbon::parse($this->importLog->report_date);
            $due = Carbon::parse($dueDateStr);
            if ($reportDate->gt($due)) {
                $overdueDays = (int) $due->diffInDays($reportDate);
            }
        }

        ArReceivable::create([
            'ar_import_log_id' => $this->importLog->id,
            'outlet_code' => $outletCode,
            'outlet_name' => trim((string) ($this->getColumnValue($row, 'outlet_name', '') ?? '')),
            'outlet_ref' => $this->getColumnValue($row, 'outlet_ref'),
            'supervisor' => $supervisor,
            'salesman_code' => trim((string) ($this->getColumnValue($row, 'salesman_id', '') ?? '')),
            'salesman_name' => trim((string) ($this->getColumnValue($row, 'salesman_name', '') ?? '')),
            'principal_code' => $principalCode,
            'principal_name' => $principalName,
            'pfi_sn' => $pfiSn,
            'doc_date' => $docDateStr,
            'due_date' => $dueDateStr,
            'inv_exchange_date' => $this->parseExcelDate($this->getColumnValue($row, 'inv_exchange_date')),
            'top' => $top,
            'si_cn' => $this->getColumnValue($row, 'ar_si_cn'),
            'cm' => (int) ($this->getColumnValue($row, 'cm', 0) ?? 0),
            'cn_balance' => $this->parseNumber($this->getColumnValue($row, 'cn_balance', 0)),
            'ar_amount' => $this->parseNumber($this->getColumnValue($row, 'ar_amount', 0)),
            'ar_paid' => $this->parseNumber($this->getColumnValue($row, 'ar_paid', 0)),
            'ar_balance' => $this->parseNumber($this->getColumnValue($row, 'ar_balance', 0)),
            'credit_limit' => $this->parseNumber($this->getColumnValue($row, 'credit_limit', 0)),
            'paid_date' => $this->parseExcelDate($this->getColumnValue($row, 'paid_date')),
            'overdue_days' => $overdueDays,
            'giro_no' => $this->getColumnValue($row, 'giro_no'),
            'bank_code' => $this->getColumnValue($row, 'bank_code'),
            'bank_name' => $this->getColumnValue($row, 'bank_name'),
            'giro_amount' => $this->parseNumber($this->getColumnValue($row, 'giro_amount')),
            'giro_due_date' => $this->parseExcelDate($this->getColumnValue($row, 'giro_due_date')),
            'branch_sheet' => $this->sheetName,
        ]);
    }

    /**
     * Parse Excel serial date or string date to Y-m-d
     */
    protected function parseExcelDate($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        try {
            if (is_numeric($value) && (int) $value > 30000) {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((int) $value)->format('Y-m-d');
            }

            if (is_string($value)) {
                if (preg_match('/^\d{2}-\d{2}-\d{4}$/', $value)) {
                    return Carbon::createFromFormat('d-m-Y', $value)->format('Y-m-d');
                }
                return Carbon::parse($value)->format('Y-m-d');
            }
        } catch (\Exception $e) {
            // Return null for unparseable dates
        }

        return null;
    }

    protected function parseNumber($value): float
    {
        if (is_numeric($value)) {
            return (float) $value;
        }
        if (is_string($value)) {
            $value = trim($value, "\" '");
            $value = str_replace(',', '', $value);
            if (empty($value) || $value === '-') {
                return 0;
            }
            return (float) $value;
        }
        return 0;
    }
}
