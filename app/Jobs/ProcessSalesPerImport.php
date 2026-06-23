<?php

namespace App\Jobs;

use App\Models\SalesPerImportLog;
use App\Models\SalesPerStock;
use App\Models\SalesPerTransaction;
use App\Support\ChunkReadFilter;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ProcessSalesPerImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Maximum time (in seconds) the job may run before being killed.
     * 30 minutes — generous for large files but prevents infinite hangs.
     */
    public int $timeout = 1800;

    public bool $failOnTimeout = false;

    protected SalesPerImportLog $importLog;

    protected string $filePath;

    protected string $importMode;

    public function __construct(SalesPerImportLog $importLog, string $filePath, string $importMode = 'tambah')
    {
        $this->importLog = $importLog;
        $this->filePath = $filePath;
        $this->importMode = $importMode;
    }

    public function handle(): void
    {
        set_time_limit(0);
        // Try to request as much as possible, though the system might limit us to 1GB
        ini_set('memory_limit', '2048M');

        try {
            $this->importLog->update(['status' => 'processing']);

            $fullPath = $this->resolveFilePath();

            if ($this->importMode === 'ganti') {
                SalesPerTransaction::withoutGlobalScope('acl')
                    ->where('period', $this->importLog->period)->delete();
                SalesPerStock::withoutGlobalScope('acl')
                    ->where('period', $this->importLog->period)->delete();
            }

            $importedCount = 0;
            $failedCount = 0;
            $errors = [];
            $chunkSize = 3000; // Smaller chunks to be safe with 1GB limit
            $filter = new ChunkReadFilter;

            $reader = IOFactory::createReaderForFile($fullPath);
            $reader->setReadDataOnly(true);
            $reader->setReadFilter($filter);

            $sheetsToProcess = [
                'penjualan' => 'processPenjualanChunk',
                'return' => 'processReturnChunk',
                'stock bjm' => 'processStockChunk',
                'stock brb' => 'processStockChunk',
                'stock btl' => 'processStockChunk',
            ];

            $availableSheets = $reader->listWorksheetNames($fullPath);

            foreach ($sheetsToProcess as $sheetName => $method) {
                if (! in_array($sheetName, $availableSheets)) {
                    continue;
                }

                // 1. Get total rows for this sheet first (minimal memory)
                $tempReader = IOFactory::createReaderForFile($fullPath);
                $tempReader->setReadDataOnly(true);
                $info = $tempReader->listWorksheetInfo($fullPath);
                $totalRows = 0;
                foreach ($info as $sheetInfo) {
                    if ($sheetInfo['worksheetName'] === $sheetName) {
                        $totalRows = $sheetInfo['totalRows'];
                        break;
                    }
                }
                unset($tempReader);

                // 2. Process in chunks
                for ($startRow = 2; $startRow <= $totalRows; $startRow += $chunkSize) {
                    $filter->setRows($startRow, $chunkSize, $sheetName);
                    $reader->setLoadSheetsOnly([$sheetName]);

                    $spreadsheet = $reader->load($fullPath);
                    $sheet = $spreadsheet->getActiveSheet();

                    $result = $this->$method($sheet, $startRow, $startRow + $chunkSize - 1);

                    $importedCount += $result['imported'];
                    $failedCount += $result['failed'];
                    $errors = array_merge($errors, $result['errors']);

                    // Clear memory
                    $spreadsheet->disconnectWorksheets();
                    unset($spreadsheet);
                    gc_collect_cycles();

                    // Update progress
                    $this->importLog->update(['imported_rows' => $importedCount, 'failed_rows' => $failedCount]);
                }
            }

            $this->importLog->update([
                'total_rows' => $importedCount + $failedCount,
                'imported_rows' => $importedCount,
                'failed_rows' => $failedCount,
                'status' => $importedCount > 0 ? 'completed' : 'failed',
                'errors' => ! empty($errors) ? implode("\n", array_slice($errors, 0, 50)) : null,
                'completed_at' => now(),
            ]);

        } catch (\Throwable $e) {
            $this->importLog->update([
                'status' => 'failed',
                'errors' => $e->getMessage().' at '.$e->getFile().':'.$e->getLine(),
                'completed_at' => now(),
            ]);
        } finally {
            try {
                $fullPath = $this->resolveFilePath();
                if (file_exists($fullPath)) {
                    @unlink($fullPath);
                }
            } catch (\Exception $ex) {
                // Ignore if path cannot be resolved
            }

            cache()->flush();
        }
    }

    /**
     * Handle a job failure — ensures the import log is always updated.
     */
    public function failed(?\Throwable $exception): void
    {
        $this->importLog->update([
            'status' => 'failed',
            'errors' => $exception ? $exception->getMessage() : 'Job failed unexpectedly',
            'completed_at' => now(),
        ]);

        cache()->flush();
    }

    /**
     * Resolve the uploaded file path from storage.
     *
     * @throws \Exception
     */
    protected function resolveFilePath(): string
    {
        foreach (['app/private/', 'app/'] as $prefix) {
            $p = storage_path($prefix.$this->filePath);
            if (file_exists($p)) {
                return $p;
            }
        }

        throw new \Exception('File not found: '.$this->filePath);
    }

    /**
     * Calculate Stock Week Cover from on-hand stock and Weekly Average Sales.
     */
    protected function calculateSwc(int $onHandBase, float $was): float
    {
        return $was > 0 ? ceil($onHandBase / $was) : 0;
    }

    /**
     * @return array{imported: int, failed: int, errors: array<string>}
     */
    protected function processPenjualanChunk($sheet, int $start, int $end): array
    {
        $imported = 0;
        $failed = 0;
        $errors = [];
        $logId = $this->importLog->id;
        $period = $this->importLog->period;
        $batch = [];

        // highestRow is limited by the filter
        $highestRow = min($sheet->getHighestRow(), $end);

        for ($row = $start; $row <= $highestRow; $row++) {
            try {
                $distId = trim((string) $sheet->getCell('A'.$row)->getValue());
                $salesCode = trim((string) $sheet->getCell('N'.$row)->getValue());

                if (empty($distId) && empty($salesCode)) {
                    continue;
                }

                $batch[] = [
                    'sales_per_import_log_id' => $logId, 'type' => 'I', 'branch_code' => $distId,
                    'sales_code' => $salesCode, 'sales_name' => trim((string) $sheet->getCell('O'.$row)->getValue()),
                    'outlet_code' => trim((string) $sheet->getCell('R'.$row)->getValue()), 'outlet_name' => trim((string) $sheet->getCell('S'.$row)->getValue()),
                    'principal_code' => trim((string) $sheet->getCell('W'.$row)->getValue()), 'principal_name' => trim((string) $sheet->getCell('X'.$row)->getValue()),
                    'item_no' => trim((string) $sheet->getCell('AB'.$row)->getValue()), 'item_name' => trim((string) $sheet->getCell('AC'.$row)->getValue()),
                    'so_no' => trim((string) $sheet->getCell('F'.$row)->getValue()), 'pfi_no' => trim((string) $sheet->getCell('D'.$row)->getValue()),
                    'so_date' => $this->parseExcelDate($sheet->getCell('J'.$row)->getValue()),
                    'qty' => (int) ($sheet->getCell('AF'.$row)->getValue() ?? 0), 'subtotal' => (float) ($sheet->getCell('AM'.$row)->getValue() ?? 0),
                    'vat' => (float) ($sheet->getCell('AN'.$row)->getValue() ?? 0), 'period' => $period, 'created_at' => now(), 'updated_at' => now(),
                ];
                $imported++;
            } catch (\Exception $e) {
                $failed++;
                if (count($errors) < 5) {
                    $errors[] = "Penjualan Row {$row}: ".$e->getMessage();
                }
            }
        }
        if (! empty($batch)) {
            SalesPerTransaction::insert($batch);
        }

        return compact('imported', 'failed', 'errors');
    }

    /**
     * @return array{imported: int, failed: int, errors: array<string>}
     */
    protected function processReturnChunk($sheet, int $start, int $end): array
    {
        $imported = 0;
        $failed = 0;
        $errors = [];
        $logId = $this->importLog->id;
        $period = $this->importLog->period;
        $batch = [];
        $highestRow = min($sheet->getHighestRow(), $end);

        for ($row = $start; $row <= $highestRow; $row++) {
            try {
                $branch = trim((string) $sheet->getCell('A'.$row)->getValue());
                $salesCode = trim((string) $sheet->getCell('D'.$row)->getValue());
                if (empty($branch) && empty($salesCode)) {
                    continue;
                }

                $batch[] = [
                    'sales_per_import_log_id' => $logId, 'type' => 'R', 'branch_code' => $branch,
                    'sales_code' => $salesCode, 'sales_name' => trim((string) $sheet->getCell('E'.$row)->getValue()),
                    'outlet_code' => trim((string) $sheet->getCell('S'.$row)->getValue()), 'outlet_name' => trim((string) $sheet->getCell('T'.$row)->getValue()),
                    'principal_code' => trim((string) $sheet->getCell('AJ'.$row)->getValue()), 'principal_name' => trim((string) $sheet->getCell('AK'.$row)->getValue()),
                    'item_no' => trim((string) $sheet->getCell('AM'.$row)->getValue()), 'item_name' => trim((string) $sheet->getCell('AN'.$row)->getValue()),
                    'so_no' => trim((string) $sheet->getCell('H'.$row)->getValue()), 'pfi_no' => '',
                    'so_date' => $this->parseExcelDate($sheet->getCell('G'.$row)->getValue()),
                    'qty' => abs((int) ($sheet->getCell('BB'.$row)->getValue() ?? 0)),
                    'subtotal' => abs((float) ($sheet->getCell('BS'.$row)->getValue() ?? 0)),
                    'vat' => abs((float) ($sheet->getCell('BT'.$row)->getValue() ?? 0)),
                    'period' => $period, 'created_at' => now(), 'updated_at' => now(),
                ];
                $imported++;
            } catch (\Exception $e) {
                $failed++;
                if (count($errors) < 5) {
                    $errors[] = "Return Row {$row}: ".$e->getMessage();
                }
            }
        }
        if (! empty($batch)) {
            SalesPerTransaction::insert($batch);
        }

        return compact('imported', 'failed', 'errors');
    }

    /**
     * @return array{imported: int, failed: int, errors: array<string>}
     */
    protected function processStockChunk($sheet, int $start, int $end): array
    {
        $imported = 0;
        $failed = 0;
        $errors = [];
        $logId = $this->importLog->id;
        $period = $this->importLog->period;
        $batch = [];
        $highestRow = min($sheet->getHighestRow(), $end);

        for ($row = $start; $row <= $highestRow; $row++) {
            try {
                $principalCode = trim((string) $sheet->getCell('A'.$row)->getValue());
                $itemNo = trim((string) $sheet->getCell('G'.$row)->getValue());
                if (empty($principalCode) && empty($itemNo)) {
                    continue;
                }

                $onHandBase = (int) ($sheet->getCell('L'.$row)->getValue() ?? 0);
                $was = (float) ($sheet->getCell('Q'.$row)->getValue() ?? 0);

                $batch[] = [
                    'sales_per_import_log_id' => $logId, 'principal_code' => $principalCode, 'principal_name' => trim((string) $sheet->getCell('B'.$row)->getValue()),
                    'warehouse_code' => trim((string) $sheet->getCell('C'.$row)->getValue()), 'warehouse_name' => trim((string) $sheet->getCell('D'.$row)->getValue()),
                    'item_no' => $itemNo, 'item_name' => trim((string) $sheet->getCell('H'.$row)->getValue()), 'size' => trim((string) $sheet->getCell('I'.$row)->getValue()),
                    'on_hand_base' => $onHandBase, 'on_sales_base' => (int) ($sheet->getCell('M'.$row)->getValue() ?? 0),
                    'stock_value_on_hand' => (float) ($sheet->getCell('N'.$row)->getValue() ?? 0), 'stock_value_on_sales' => (float) ($sheet->getCell('O'.$row)->getValue() ?? 0),
                    'was' => $was,
                    'swc' => $this->calculateSwc($onHandBase, $was),
                    'age_of_goods' => (int) ($sheet->getCell('S'.$row)->getValue() ?? 0),
                    'period' => $period, 'created_at' => now(), 'updated_at' => now(),
                ];
                $imported++;
            } catch (\Exception $e) {
                $failed++;
                if (count($errors) < 5) {
                    $errors[] = "Stock Row {$row}: ".$e->getMessage();
                }
            }
        }
        if (! empty($batch)) {
            SalesPerStock::insert($batch);
        }

        return compact('imported', 'failed', 'errors');
    }

    protected function parseExcelDate($value): ?string
    {
        if (empty($value)) {
            return null;
        }
        try {
            if (is_numeric($value)) {
                return Carbon::createFromFormat('Y-m-d', gmdate('Y-m-d', ($value - 25569) * 86400))->format('Y-m-d');
            }

            return Carbon::parse($value)->format('Y-m-d');
        } catch (\Exception $e) {
            return null;
        }
    }
}
