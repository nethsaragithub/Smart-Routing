<?php

namespace App\Services\Reports;

use App\Support\ReportPeriod;
use Illuminate\Support\Collection;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Factory that creates a report object from its URL key.
 */
class ReportFactory
{
    /** @var list<class-string<Report>> */
    private const REPORTS = [
        TripCompletionReport::class,
        RoutePerformanceReport::class,
        FuelConsumptionReport::class,
        MaintenanceSummaryReport::class,
        DriverHoursReport::class,
    ];

    public function make(string $key, ReportPeriod $period): Report
    {
        foreach (self::REPORTS as $class) {
            if ($class::key() === $key) {
                return new $class($period);
            }
        }

        throw new NotFoundHttpException("Unknown report [{$key}].");
    }

    /** @return Collection<int, array{key: string, title: string, description: string}> */
    public function available(bool $includeManagement = true): Collection
    {
        return collect(self::REPORTS)
            ->filter(fn (string $class) => $includeManagement || ! $class::forManagement())
            ->map(fn (string $class) => [
                'key' => $class::key(),
                'title' => $class::title(),
                'description' => $class::description(),
            ])
            ->values();
    }
}
