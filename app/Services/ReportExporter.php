<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CSV export for the report screens.
 *
 * Streamed rather than built in memory: a year of payments is a large result
 * set, and the point of exporting is usually that the range is big.
 *
 * CSV is deliberately the only format. It opens in every spreadsheet an ISP
 * office already has, and adding XLSX would mean a dependency for no gain.
 */
class ReportExporter
{
    /**
     * @param  array<int, string>  $headings
     * @param  iterable<int, array<int, string|int|float|null>>  $rows
     */
    public function csv(string $name, array $headings, iterable $rows): StreamedResponse
    {
        $filename = sprintf('%s-%s.csv', $name, Carbon::now()->format('Y-m-d'));

        return response()->streamDownload(function () use ($headings, $rows): void {
            $handle = fopen('php://output', 'wb');

            // BOM so Excel reads UTF-8 correctly; without it the peso sign and
            // any accented customer name arrive mangled.
            fwrite($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, $headings);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
