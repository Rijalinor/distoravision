<?php

namespace App\Jobs;

use App\Imports\SecondaryDataImport;
use App\Models\ImportLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Maatwebsite\Excel\Facades\Excel;

class ProcessSecondaryDataImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 0; // No timeout
    public $failOnTimeout = false;

    protected $importLog;
    protected $filePath;

    /**
     * Create a new job instance.
     */
    public function __construct(ImportLog $importLog, string $filePath)
    {
        $this->importLog = $importLog;
        $this->filePath = $filePath;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        set_time_limit(0);
        ini_set('memory_limit', '1024M');

        try {
            $this->importLog->update(['status' => 'processing']);

            $import = new SecondaryDataImport($this->importLog);
            Excel::import($import, $this->filePath, 'local');

            $totalRows = $import->getImportedCount() + $import->getFailedCount() + $import->getDuplicateCount();
            $status = 'completed';
            if ($import->getImportedCount() === 0) {
                $status = 'failed';
            } elseif ($import->getFailedCount() > 0) {
                $status = 'completed';
            }

            $this->importLog->update([
                'total_rows' => $totalRows,
                'imported_rows' => $import->getImportedCount(),
                'skipped_rows' => $import->getDuplicateCount(),
                'failed_rows' => $import->getFailedCount(),
                'status' => $status,
                'errors' => !empty($import->getErrors()) ? implode("\n", $import->getErrors()) : null,
                'completed_at' => now(),
            ]);

            // Optional: Delete the temporary file after successful import
            if (file_exists(storage_path('app/private/' . $this->filePath))) {
                unlink(storage_path('app/private/' . $this->filePath));
            } elseif (file_exists(storage_path('app/' . $this->filePath))) {
                unlink(storage_path('app/' . $this->filePath));
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
