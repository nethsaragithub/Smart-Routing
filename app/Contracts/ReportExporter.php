<?php

namespace App\Contracts;

use App\Services\Reports\Report;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns a report into a downloadable file (PDF, CSV ...).
 */
interface ReportExporter
{
    public function export(Report $report): Response;

    /** File extension without the dot, e.g. "pdf". */
    public function extension(): string;
}
