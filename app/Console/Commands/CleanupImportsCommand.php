<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class CleanupImportsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:cleanup-imports';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Clean up temporary uploaded import files older than 24 hours';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $disk = Storage::disk('local');
        $directory = 'imports';

        if (! $disk->exists($directory)) {
            $this->info("Directory '{$directory}' does not exist. Nothing to clean up.");

            return Command::SUCCESS;
        }

        $files = $disk->files($directory);
        $deletedCount = 0;
        $now = time();
        $cutoff = $now - 86400; // 24 hours ago

        foreach ($files as $file) {
            try {
                $lastModified = $disk->lastModified($file);
                if ($lastModified < $cutoff) {
                    $disk->delete($file);
                    $deletedCount++;
                    $this->line("Deleted old import file: {$file} (Modified: ".date('Y-m-d H:i:s', $lastModified).')');
                }
            } catch (\Exception $e) {
                $this->error("Failed to delete file '{$file}': ".$e->getMessage());
            }
        }

        $this->info("Cleanup completed. Deleted {$deletedCount} file(s) from '{$directory}'.");

        return Command::SUCCESS;
    }
}
