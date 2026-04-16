<?php

namespace App\Jobs;

use App\Imports\ArDataImport;
use App\Models\ArImportLog;
use App\Models\ArReceivable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessArImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 0;
    public $failOnTimeout = false;

    protected $importLog;
    protected $filePath;
    protected $sheetName;

    public function __construct(ArImportLog $importLog, string $filePath, string $sheetName)
    {
        $this->importLog = $importLog;
        $this->filePath = $filePath;
        $this->sheetName = $sheetName;
    }

    public function handle(): void
    {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');

        try {
            $this->importLog->update(['status' => 'processing']);

            // Resolve full path
            $fullPath = storage_path('app/private/' . $this->filePath);
            if (!file_exists($fullPath)) {
                $fullPath = storage_path('app/' . $this->filePath);
            }

            // Load the spreadsheet and find the matching sheet
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($fullPath);
            $sheetNames = $spreadsheet->getSheetNames();

            // Find sheet by partial match (e.g., "BJM" matches "BJM (8-4-26)")
            $targetSheet = null;
            $actualSheetName = null;
            foreach ($sheetNames as $name) {
                if (stripos($name, $this->sheetName) !== false) {
                    $targetSheet = $spreadsheet->getSheetByName($name);
                    $actualSheetName = $name;
                    break;
                }
            }

            if (!$targetSheet) {
                $spreadsheet->disconnectWorksheets();
                throw new \Exception("Sheet '{$this->sheetName}' tidak ditemukan. Sheet tersedia: " . implode(', ', $sheetNames));
            }

            // Read ONLY the target sheet into an array
            $rows = $targetSheet->toArray(null, true, true, true);
            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            if (count($rows) < 2) {
                throw new \Exception("Sheet '{$actualSheetName}' kosong atau hanya berisi header.");
            }

            // Parse the header row (row 1)
            $headerRow = array_shift($rows);
            $headers = [];
            foreach ($headerRow as $col => $val) {
                if ($val !== null) {
                    $headers[$col] = \Illuminate\Support\Str::slug(trim($val), '_');
                }
            }

            // Process data rows
            $import = new ArDataImport($this->importLog, $this->sheetName);
            $importedCount = 0;
            $failedCount = 0;
            $errors = [];

            foreach ($rows as $rowIndex => $rowData) {
                // Map row data using headers
                $mappedRow = [];
                foreach ($headers as $col => $headerKey) {
                    $mappedRow[$headerKey] = $rowData[$col] ?? null;
                }

                // Skip completely empty rows
                $pfiSn = trim((string) ($import->getColumnValue($mappedRow, 'pfi_sn', '') ?? ''));
                $outletCode = trim((string) ($import->getColumnValue($mappedRow, 'outlet_id', '') ?? ''));
                if ($pfiSn === '' && $outletCode === '') {
                    continue;
                }

                try {
                    $import->processRowData($mappedRow);
                    $importedCount++;
                } catch (\Exception $e) {
                    $failedCount++;
                    if (count($errors) < 50) {
                        $errors[] = "Row " . ($rowIndex + 1) . ": " . $e->getMessage();
                    }
                }
            }

            $status = $importedCount > 0 ? 'completed' : 'failed';
            $this->importLog->update([
                'total_rows' => $importedCount + $failedCount,
                'imported_rows' => $importedCount,
                'failed_rows' => $failedCount,
                'status' => $status,
                'errors' => !empty($errors) ? implode("\n", $errors) : null,
                'completed_at' => now(),
            ]);

            // Cleanup temp file
            if (file_exists(storage_path('app/private/' . $this->filePath))) {
                @unlink(storage_path('app/private/' . $this->filePath));
            } elseif (file_exists(storage_path('app/' . $this->filePath))) {
                @unlink(storage_path('app/' . $this->filePath));
            }
        } catch (\Exception $e) {
            $this->importLog->update([
                'status' => 'failed',
                'errors' => $e->getMessage(),
                'completed_at' => now(),
            ]);
        }
    }
}
