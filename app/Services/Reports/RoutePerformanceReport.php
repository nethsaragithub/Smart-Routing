<?php

namespace App\Services\Reports;

use App\Enums\TripStatus;
use App\Models\BusRoute;
use App\Models\Trip;
use App\Services\Fleet\FuelEfficiencyCalculator;
use Illuminate\Support\Collection;

class RoutePerformanceReport extends Report
{
    public static function key(): string
    {
        return 'route-performance';
    }

    public static function title(): string
    {
        return 'Route performance';
    }

    public static function description(): string
    {
        return 'Reliability, punctuality, ridership and fuel use for each route.';
    }

    public function columns(): array
    {
        return [
            'route' => ['Route', 'text'],
            'trips' => ['Trips', 'int'],
            'completion' => ['Completion', 'pct'],
            'punctuality' => ['On time', 'pct'],
            'avg_delay' => ['Avg delay (min)', 'dec'],
            'passengers' => ['Passengers', 'int'],
            'km' => ['Distance (km)', 'int'],
            'fuel' => ['Fuel (L/100 km)', 'dec'],
        ];
    }

    protected function buildRows(): Collection
    {
        $trips = Trip::query()
            ->whereBetween('trip_date', $this->period->dateRange())
            ->get()
            ->groupBy('bus_route_id');

        $fuel = app(FuelEfficiencyCalculator::class)->byRoute($this->period)->keyBy(fn ($r) => $r->route->id);

        return BusRoute::query()->orderBy('route_no')->get()
            ->map(function (BusRoute $route) use ($trips, $fuel) {
                $routeTrips = $trips->get($route->id, collect());
                $completed = $routeTrips->where('status', TripStatus::Completed);

                return [
                    'route' => "{$route->route_no} {$route->origin} – {$route->destination}",
                    'route_no' => $route->route_no,
                    'trips' => $routeTrips->count(),
                    'completion' => self::percent($completed->count(), $routeTrips->count()),
                    'punctuality' => self::percent($completed->filter->wasOnTime()->count(), $completed->count()),
                    'avg_delay' => $completed->isNotEmpty() ? round($completed->avg('delay_minutes'), 1) : null,
                    'passengers' => $completed->sum('passenger_count'),
                    'km' => round($completed->count() * $route->distance_km),
                    'fuel' => $fuel->get($route->id)?->l_per_100km,
                ];
            })
            ->filter(fn ($row) => $row['trips'] > 0)
            ->values();
    }

    public function summary(): array
    {
        $rows = $this->rows();

        return [
            'Routes operated' => number_format($rows->count()),
            'Trips' => number_format($rows->sum('trips')),
            'Passengers carried' => number_format($rows->sum('passengers')),
            'Distance covered' => number_format($rows->sum('km')).' km',
        ];
    }

    public function chart(): ?array
    {
        $rows = $this->rows()->sortByDesc('punctuality')->values();

        return [
            'type' => 'bar',
            'horizontal' => true,
            'unit' => '%',
            'labels' => $rows->pluck('route_no')->map(fn ($n) => "Route {$n}")->all(),
            'datasets' => [
                ['label' => 'On-time completed trips', 'data' => $rows->pluck('punctuality')->all(), 'color' => ChartPalette::series(0)],
            ],
        ];
    }

    public function insights(): array
    {
        $rows = $this->rows()->whereNotNull('punctuality');

        if ($rows->count() < 2) {
            return [];
        }

        $best = $rows->sortByDesc('punctuality')->first();
        $worst = $rows->sortBy('punctuality')->first();
        $insights = [
            "Route {$best['route_no']} is the most punctual ({$best['punctuality']}% on time).",
            "Route {$worst['route_no']} is the least punctual ({$worst['punctuality']}% on time, average delay {$worst['avg_delay']} min). Consider adding running time to its timetable.",
        ];

        $thirsty = $this->rows()->whereNotNull('fuel')->sortByDesc('fuel')->first();
        if ($thirsty) {
            $insights[] = "Route {$thirsty['route_no']} uses the most fuel per kilometre ({$thirsty['fuel']} L/100 km).";
        }

        return $insights;
    }
}
