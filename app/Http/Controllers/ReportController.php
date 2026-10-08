<?php

namespace App\Http\Controllers;

use App\Contracts\ReportExporter;
use App\Services\Reports\Exporters\CsvReportExporter;
use App\Services\Reports\Exporters\PdfReportExporter;
use App\Services\Reports\ReportFactory;
use App\Support\ReportPeriod;
use Illuminate\Http\Request;
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

    public function index(): View
    {
        return view('reports.index', ['reports' => $this->reports->available()]);
    }

    public function show(Request $request, string $report): View
    {
        $period = $this->period($request);

        return view('reports.show', [
            'report' => $this->reports->make($report, $period),
            'period' => $period,
            'reports' => $this->reports->available(),
        ]);
    }

    public function export(Request $request, string $report, string $format): Response
    {
        $exporter = app(self::EXPORTERS[$format]);

        return $exporter->export($this->reports->make($report, $this->period($request)));
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
