<?php

namespace App\Services\Reports\Exporters;

use App\Contracts\ReportExporter;
use App\Services\Reports\Report;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class PdfReportExporter implements ReportExporter
{
    public function export(Report $report): Response
    {
        return Pdf::loadView('reports.pdf', ['report' => $report, 'generatedAt' => now()])
            ->setPaper('a4', 'portrait')
            ->download($report->fileName($this->extension()));
    }

    public function extension(): string
    {
        return 'pdf';
    }
}
