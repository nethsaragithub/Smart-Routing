<?php

namespace App\Services\Fleet;

use App\Enums\TripStatus;
use App\Models\Bus;
use App\Models\BusRoute;
use App\Models\Driver;
use App\Models\FuelLog;
use App\Models\Trip;
use App\Support\ReportPeriod;
use Illuminate\Support\Collection;

/**
 * Fuel economy figures used by the fuel log, bus profile and reports.
 *
 * Method ("tank-to-tank"): the litres added at a fill-up were burnt over
 * the distance driven since the previous fill-up. For driver and route
 * figures those litres are shared among the trips run between the two
 * fills, in proportion to each trip's distance.
 */
class FuelEfficiencyCalculator
{
    /** @var array<string, Collection> memoised per period */
    private array $allocationCache = [];

    /**
     * @return Collection<int, object{bus: Bus, litres: float, cost: float, km: int, km_per_litre: ?float, fills: int}>
     */
    public function byBus(ReportPeriod $period): Collection
    {
        return FuelLog::query()
            ->with('bus')
            ->whereBetween('filled_on', $period->dateRange())
            ->orderBy('odometer')
            ->get()
            ->groupBy('bus_id')
            ->map(function (Collection $logs) use ($period) {
                $baseline = $this->previousFill($logs->first()->bus_id, $period);

                // Without an earlier fill, the first fill in the period becomes the baseline.
                [$startOdometer, $litresForDistance] = $baseline
                    ? [$baseline->odometer, $logs->sum('litres')]
                    : [$logs->first()->odometer, $logs->slice(1)->sum('litres')];

                $km = (int) ($logs->max('odometer') - $startOdometer);

                return (object) [
                    'bus' => $logs->first()->bus,
                    'litres' => round($logs->sum('litres'), 1),
                    'cost' => round($logs->sum('total_cost'), 2),
                    'km' => $km,
                    'km_per_litre' => $km > 0 && $litresForDistance > 0 ? round($km / $litresForDistance, 2) : null,
                    'fills' => $logs->count(),
                ];
            })
            ->sortBy(fn ($row) => $row->km_per_litre ?? PHP_FLOAT_MAX)
            ->values();
    }

    /** Depot-wide km per litre for the period. */
    public function fleetAverage(ReportPeriod $period): ?float
    {
        $rows = $this->byBus($period)->filter(fn ($r) => $r->km_per_litre !== null);
        $litres = $rows->sum(fn ($r) => $r->km / $r->km_per_litre);

        return $litres > 0 ? round($rows->sum('km') / $litres, 2) : null;
    }

    public function forBus(Bus $bus, ReportPeriod $period): ?float
    {
        return $this->byBus($period)->first(fn ($row) => $row->bus->id === $bus->id)?->km_per_litre;
    }

    /**
     * Litres per 100 km for each route, so long and short routes compare
     * fairly. High values point to congested or hilly routes.
     *
     * @return Collection<int, object{route: BusRoute, litres: float, cost: float, km: float, l_per_100km: ?float}>
     */
    public function byRoute(ReportPeriod $period): Collection
    {
        $totals = $this->allocations($period)->groupBy('route_id');
        $routes = BusRoute::withTrashed()->whereIn('id', $totals->keys())->get()->keyBy('id');

        return $totals->map(function (Collection $rows, int $routeId) use ($routes) {
            $km = $rows->sum('km');
            $litres = $rows->sum('litres');

            return (object) [
                'route' => $routes[$routeId],
                'litres' => round($litres, 1),
                'cost' => round($rows->sum('cost'), 2),
                'km' => round($km, 1),
                'l_per_100km' => $km > 0 ? round($litres / $km * 100, 1) : null,
            ];
        })
            ->filter(fn ($row) => isset($row->route))
            ->sortByDesc(fn ($row) => $row->l_per_100km ?? -1)
            ->values();
    }

    /**
     * Driver economy. Raw km/L depends heavily on the vehicle (an A/C coach
     * uses more fuel than a city bus), so each driver is compared with the
     * depot average for the same class of bus:
     *
     *   index = litres expected at the class average / litres actually used
     *
     * 100% is typical; 85% means about 18% more fuel than usual. Drivers
     * below the alert ratio are flagged for eco-driving coaching.
     *
     * @return Collection<int, object{driver: Driver, litres: float, km: float, km_per_litre: ?float, index: ?float, flagged: bool}>
     */
    public function byDriver(ReportPeriod $period): Collection
    {
        $allocations = $this->allocations($period);
        $classAverage = $allocations->groupBy('service')
            ->map(fn (Collection $r) => $r->sum('litres') > 0 ? $r->sum('km') / $r->sum('litres') : null);

        $totals = $allocations->groupBy('driver_id');
        $drivers = Driver::withTrashed()->whereIn('id', $totals->keys())->get()->keyBy('id');

        return $totals->map(function (Collection $r, int $driverId) use ($classAverage, $drivers) {
            $litres = $r->sum('litres');
            $expected = $r->sum(fn ($a) => $classAverage[$a['service']] ? $a['km'] / $classAverage[$a['service']] : 0);
            $index = $litres > 0 && $expected > 0 ? round($expected / $litres * 100, 1) : null;

            return (object) [
                'driver' => $drivers[$driverId] ?? null,
                'litres' => round($litres, 1),
                'km' => round($r->sum('km'), 1),
                'km_per_litre' => $litres > 0 ? round($r->sum('km') / $litres, 2) : null,
                'index' => $index,
                'flagged' => $index !== null && $index < config('srmss.fuel_efficiency_alert_ratio') * 100,
            ];
        })
            ->filter(fn ($row) => $row->driver !== null)
            ->sortBy(fn ($row) => $row->index ?? PHP_FLOAT_MAX)
            ->values();
    }

    /**
     * Share every fill-up in the period among the trips it powered.
     *
     * @return Collection<int, array{bus_id: int, service: string, driver_id: int, route_id: int, km: float, litres: float, cost: float}>
     */
    private function allocations(ReportPeriod $period): Collection
    {
        $key = implode('|', $period->dateRange());

        return $this->allocationCache[$key] ??= $this->buildAllocations($period);
    }

    private function buildAllocations(ReportPeriod $period): Collection
    {
        $rows = collect();

        $fillsByBus = FuelLog::query()
            ->whereBetween('filled_on', $period->dateRange())
            ->orderBy('odometer')
            ->get()
            ->groupBy('bus_id');

        $serviceOf = Bus::withTrashed()->whereIn('id', $fillsByBus->keys())->get()
            ->mapWithKeys(fn (Bus $b) => [$b->id => $b->service_type->value]);

        foreach ($fillsByBus as $busId => $fills) {
            $previous = $this->previousFill($busId, $period);

            $trips = Trip::query()
                ->with('route')
                ->where('bus_id', $busId)
                ->where('status', TripStatus::Completed)
                ->whereBetween('trip_date', [
                    ($previous?->filled_on ?? $period->from)->toDateString(),
                    $period->to->toDateString(),
                ])
                ->orderBy('scheduled_departure')
                ->get();

            foreach ($fills as $fill) {
                if ($previous === null) {
                    $previous = $fill;  // nothing to measure against yet

                    continue;
                }

                $window = $trips->filter(fn (Trip $t) => $t->odometer_start !== null
                    ? $t->odometer_start >= $previous->odometer && $t->odometer_start < $fill->odometer
                    : $t->trip_date->betweenIncluded($previous->filled_on, $fill->filled_on));

                $distance = $window->sum(fn (Trip $t) => $t->distanceDriven() ?? $t->route->distance_km);

                if ($distance > 0) {
                    foreach ($window as $trip) {
                        $km = $trip->distanceDriven() ?? $trip->route->distance_km;
                        $share = $km / $distance;

                        $rows->push([
                            'bus_id' => $busId,
                            'service' => $serviceOf[$busId] ?? 'normal',
                            'driver_id' => $trip->driver_id,
                            'route_id' => $trip->bus_route_id,
                            'km' => $km,
                            'litres' => $fill->litres * $share,
                            'cost' => $fill->total_cost * $share,
                        ]);
                    }
                }

                $previous = $fill;
            }
        }

        return $rows;
    }

    private function previousFill(int $busId, ReportPeriod $period): ?FuelLog
    {
        return FuelLog::query()
            ->where('bus_id', $busId)
            ->whereDate('filled_on', '<', $period->from)
            ->orderByDesc('odometer')
            ->first();
    }
}
