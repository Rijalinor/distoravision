<?php

namespace App\Http\Controllers\Traits;

use Symfony\Component\HttpFoundation\StreamedResponse;

trait CsvExportable
{
    /**
     * Stream a CSV download from an array of headers and rows.
     *
     * @param  string  $filename  The filename for the downloaded file
     * @param  array<string>  $headers  Column header labels
     * @param  array<array>  $rows  Array of row data (each row is an array of values)
     */
    protected function streamCsv(string $filename, array $headers, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $handle = fopen('php://output', 'w');

            // UTF-8 BOM so Excel opens it correctly
            fwrite($handle, "\xEF\xBB\xBF");

            fputcsv($handle, $headers);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
