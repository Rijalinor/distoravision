<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

/**
 * Chunk Reading Filter for PhpSpreadsheet to keep memory usage low.
 * Only allows reading a specific range of rows within a specific sheet.
 */
class ChunkReadFilter implements IReadFilter
{
    private int $startRow = 0;

    private int $endRow = 0;

    private string $sheetName = '';

    /**
     * Configure the filter to only read rows within the given range.
     */
    public function setRows(int $startRow, int $chunkSize, string $sheetName): void
    {
        $this->startRow = $startRow;
        $this->endRow = $startRow + $chunkSize;
        $this->sheetName = $sheetName;
    }

    public function readCell($columnAddress, $row, $worksheetName = ''): bool
    {
        // Always read the header row (1)
        if ($row == 1) {
            return true;
        }

        // Only read if within the current chunk range and matching sheet
        if ($worksheetName === $this->sheetName) {
            if ($row >= $this->startRow && $row < $this->endRow) {
                return true;
            }
        }

        return false;
    }
}
