<?php

namespace App\Services\Reports;

use App\Enums\TripStatus;
use App\Models\Driver;
use App\Models\Trip;
use Illuminate\Support\Collection;

class DriverHoursReport extends Report
{
    public static function key(): string
    {
        return 'driver-hours';
    }

    public static function title(): string
    {
        return 'Driver working hours';
    }

    public static function description(): string
    {
        return 'Trips and hours driven per driver, compared with their weekly limit.';
    }

    public function columns(): array
    {
        return [
            'driver' => ['Driver', 'text'],
            'employee_no' => ['Employee no.', 'text'],
            'trips' => ['Trips', 'int'],
            'hours' => ['Hours', 'dec'],
            'weekly' => ['Avg hours / week', 'dec'],
            'limit' => ['Weekly limit', 'int'],
            'licence' => ['Licence', 'text'],
        ];
    }

    protected function buildRows(): Collection
    {
        $weeks = max(1, $this->period->days() / 7);

        $trips = Trip::query()
            ->whereBetween('trip_date', $this->period->dateRange())
            ->where('status', '!=', TripStatus::Cancelled)
            ->get()
            ->groupBy('driver_id');

        return Driver::query()->orderBy('full_name')->get()->map(function (Driver $driver) use ($trips, $weeks) {
            $driverTrips = $trips->get($driver->id, collect());
            $hours = round($driverTrips->sum(fn (Trip $t) => $t->durationMinutes()) / 60, 1);

            return [
                'driver' => $driver->full_name,
                'employee_no' => $driver->employee_no,
                'trips' => $driverTrips->count(),
                'hours' => $hours,
                'weekly' => round($hours / $weeks, 1),
                'limit' => $driver->max_weekly_hours,
                'licence' => $driver->licenseStatus()->label(),
            ];
        })->sortByDesc('hours')->values();
    }

    public function summary(): array
    {
        $rows = $this->rows();
        $active = $rows->where('trips', '>', 0);

        return [
            'Drivers rostered' => number_format($active->count()),
            'Hours driven' => number_format($rows->sum('hours'), 1),
            'Average per driver' => $active->isNotEmpty() ? number_format($active->avg('hours'), 1).' h' : '–',
            'Over weekly limit' => number_format($rows->filter(fn ($r) => $r['weekly'] > $r['limit'])->count()),
        ];
    }

    public function chart(): ?array
    {
        $rows = $this->rows()->where('trips', '>', 0)->take(15);

        return [
            'type' => 'bar',
            'horizontal' => true,
            'unit' => 'h',
            'labels' => $rows->pluck('driver')->all(),
            'datasets' => [
                ['label' => 'Average hours per week', 'data' => $rows->pluck('weekly')->all(), 'color' => ChartPalette::series(0)],
            ],
        ];
    }

    public function insights(): array
    {
        $rows = $this->rows();
        $over = $rows->filter(fn ($r) => $r['weekly'] > $r['limit']);
        $idle = $rows->where('trips', 0);
        $insights = [];

        if ($over->isNotEmpty()) {
            $insights[] = 'Over their weekly limit: '.$over->pluck('driver')->implode(', ').'. Rebalance the roster to reduce fatigue risk.';
        }

        if ($idle->isNotEmpty()) {
            $insights[] = $idle->count().' driver(s) had no trips in this period and could take on extra work.';
        }

        return $insights;
    }
}
