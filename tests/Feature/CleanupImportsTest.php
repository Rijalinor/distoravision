<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CleanupImportsTest extends TestCase
{
    public function test_cleanup_imports_command_deletes_files_older_than_24_hours(): void
    {
        $disk = Storage::disk('local');
        $oldFile = 'imports/test_temp_old.xlsx';
        $newFile = 'imports/test_temp_new.xlsx';

        // Write files
        $disk->put($oldFile, 'dummy content');
        $disk->put($newFile, 'dummy content');

        // Touch the old file to be 25 hours ago
        $oldPath = $disk->path($oldFile);
        $newPath = $disk->path($newFile);

        // Ensure directory structure exists
        if (! file_exists(dirname($oldPath))) {
            mkdir(dirname($oldPath), 0755, true);
        }

        touch($oldPath, time() - (25 * 3600)); // 25 hours ago
        touch($newPath, time()); // now

        // Run command
        $this->artisan('app:cleanup-imports')
            ->assertExitCode(0);

        // Assert old file is gone, new file is still there
        $this->assertFalse($disk->exists($oldFile));
        $this->assertTrue($disk->exists($newFile));

        // Cleanup
        $disk->delete($newFile);
    }
}
