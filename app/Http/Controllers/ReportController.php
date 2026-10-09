<?php

namespace App\Http\Controllers;

use App\Contracts\ReportExporter;
use App\Services\Reports\Exporters\CsvReportExporter;
use App\Services\Reports\Exporters\PdfReportExporter;
use App\Services\Reports\Report;
use App\Services\Reports\ReportFactory;
use App\Support\ReportPeriod;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

class ReportController extends Controller
{
    /** @var array<string, class-string<ReportExporter>> */
    private const EXPORTERS = [
        'pdf' => PdfReportExporter::class,
        'csv' => CsvReportExporter::class,
    ];

    public function __construct(private readonly ReportFactory $reports)
    {
    }

    public function index(Request $request): View
    {
        return view('reports.index', ['reports' => $this->available($request)]);
    }

    public function show(Request $request, string $report): View
    {
        $period = $this->period($request);

        return view('reports.show', [
            'report' => $this->make($request, $report, $period),
            'period' => $period,
            'reports' => $this->available($request),
        ]);
    }

    public function export(Request $request, string $report, string $format): Response
    {
        $exporter = app(self::EXPORTERS[$format]);

        return $exporter->export($this->make($request, $report, $this->period($request)));
    }

    /** Supervisors get the operational reports; cost reports are for administrators. */
    private function make(Request $request, string $key, ReportPeriod $period): Report
    {
        $report = $this->reports->make($key, $period);

        abort_if($report::forManagement() && $request->user()->cannot('view-management-reports'), 403);

        return $report;
    }

    private function available(Request $request): Collection
    {
        return $this->reports->available($request->user()->can('view-management-reports'));
    }

    private function period(Request $request): ReportPeriod
    {
        try {
            return ReportPeriod::fromInput($request->query());
        } catch (InvalidArgumentException) {
            return ReportPeriod::month();
        }
    }
}
