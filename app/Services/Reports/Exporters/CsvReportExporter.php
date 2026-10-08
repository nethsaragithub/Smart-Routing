<?php

namespace App\Services\Reports\Exporters;

use App\Contracts\ReportExporter;
use App\Services\Reports\Report;
use Symfony\Component\HttpFoundation\Response;

class CsvReportExporter implements ReportExporter
{
    public function export(Report $report): Response
    {
        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows Sinhala/Tamil names correctly

            fputcsv($out, [$report::title(), $report->period()->label()]);
            fputcsv($out, []);

            $columns = $report->columns();
            fputcsv($out, array_map(fn ($c) => $c[0], $columns));

            foreach ($report->rows() as $row) {
                fputcsv($out, array_map(fn ($key) => $row[$key] ?? '', array_keys($columns)));
            }

            fclose($out);
        }, $report->fileName($this->extension()), ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function extension(): string
    {
        return 'csv';
    }
}
