<?php

namespace App\Imports;

use App\Imports\SalesPer\SalesPerPenjualanSheet;
use App\Imports\SalesPer\SalesPerReturnSheet;
use App\Imports\SalesPer\SalesPerStockBjmSheet;
use App\Models\SalesPerImportLog;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SalesPerDataImport implements WithMultipleSheets
{
    protected SalesPerImportLog $importLog;

    protected int $importedCount = 0;

    protected int $failedCount = 0;

    /** @var array<int, string> */
    protected array $errors = [];

    public function __construct(SalesPerImportLog $importLog)
    {
        $this->importLog = $importLog;
    }

    /**
     * Map each Excel sheet name to its dedicated sheet handler.
     *
     * @return array<string, ToCollection>
     */
    public function sheets(): array
    {
        return [
            'penjualan' => new SalesPerPenjualanSheet($this),
            'return' => new SalesPerReturnSheet($this),
            'stock bjm' => new SalesPerStockBjmSheet($this),
            'stock brb' => new SalesPerStockBjmSheet($this),
            'stock btl' => new SalesPerStockBjmSheet($this),
        ];
    }

    public function getImportLog(): SalesPerImportLog
    {
        return $this->importLog;
    }

    public function incrementImported(): void
    {
        $this->importedCount++;
    }

    public function incrementFailed(): void
    {
        $this->failedCount++;
    }

    public function addError(string $msg): void
    {
        if (count($this->errors) < 50) {
            $this->errors[] = $msg;
        }
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }

    public function getFailedCount(): int
    {
        return $this->failedCount;
    }

    /**
     * @return array<int, string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
