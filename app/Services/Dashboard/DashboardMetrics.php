<?php

namespace App\Services\Dashboard;

use App\Enums\BusStatus;
use App\Enums\DriverStatus;
use App\Enums\LicenseStatus;
use App\Enums\TripStatus;
use App\Models\Bus;
use App\Models\BusRoute;
use App\Models\Driver;
use App\Models\MaintenanceRecord;
use App\Models\Trip;
use App\Services\Fleet\MaintenanceService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

/**
 * Read-only figures for the Depot Management Dashboard.
 */
class DashboardMetrics
{
    public function __construct(private readonly MaintenanceService $maintenance)
    {
    }

    /** @return Collection<int, Trip> */
    public function tripsOn(CarbonImmutable $date): Collection
    {
        return Trip::query()
            ->on($date)
            ->with(['route', 'bus', 'driver'])
            ->orderBy('scheduled_departure')
            ->get();
    }

    /**
     * @param  Collection<int, Trip>  $trips
     * @return array<string, mixed>
     */
    public function summary(Collection $trips): array
    {
        $buses = Bus::query()->get(['id', 'status']);
        $activeBuses = $buses->where('status', BusStatus::Active);
        $busesOnRoad = $trips->filter->isRunning()->pluck('bus_id')->unique();
        $busesUsedToday = $trips->where('status', '!=', TripStatus::Cancelled)->pluck('bus_id')->unique();

        $byStatus = $trips->countBy(fn (Trip $t) => $t->status->value);
        $finished = ($byStatus[TripStatus::Completed->value] ?? 0);
        $departed = $trips->filter->hasDeparted();
        $onTimeDepartures = $departed->filter(fn (Trip $t) => $t->delay_minutes <= config('srmss.on_time_grace_minutes'));
        $nonCancelled = $trips->count() - ($byStatus[TripStatus::Cancelled->value] ?? 0);

        return [
            'routes_active' => BusRoute::query()->active()->count(),
            'routes_total' => BusRoute::query()->count(),
            'buses_total' => $buses->count(),
            'buses_active' => $activeBuses->count(),
            'buses_available' => $activeBuses->whereNotIn('id', $busesOnRoad)->count(),
            'buses_on_road' => $busesOnRoad->count(),
            'buses_in_maintenance' => $buses->where('status', BusStatus::Maintenance)->count(),
            'drivers_active' => Driver::query()->active()->count(),
            'drivers_on_duty' => $trips->where('status', '!=', TripStatus::Cancelled)->pluck('driver_id')->unique()->count(),
            'trips_total' => $trips->count(),
            'trips_by_status' => collect(TripStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $byStatus[$s->value] ?? 0])->all(),
            'completion_rate' => $nonCancelled > 0 ? round($finished / $nonCancelled * 100) : null,
            'on_time_rate' => $departed->count() > 0 ? round($onTimeDepartures->count() / $departed->count() * 100) : null,
            'utilisation_rate' => $activeBuses->count() > 0 ? round($busesUsedToday->intersect($activeBuses->pluck('id'))->count() / $activeBuses->count() * 100) : null,
        ];
    }

    /**
     * Daily on-time / late / cancelled counts for the trend chart.
     *
     * @return array{labels: list<string>, on_time: list<int>, late: list<int>, cancelled: list<int>}
     */
    public function lastDays(int $days = 7): array
    {
        $from = today()->subDays($days - 1);

        $rows = Trip::query()
            ->whereBetween('trip_date', [$from->toDateString(), today()->toDateString()])
            ->get(['trip_date', 'status', 'delay_minutes'])
            ->groupBy(fn (Trip $t) => $t->trip_date->toDateString());

        $result = ['labels' => [], 'on_time' => [], 'late' => [], 'cancelled' => []];

        for ($d = $from->copy(); $d->lte(today()); $d->addDay()) {
            $dayTrips = $rows->get($d->toDateString(), collect());
            $completed = $dayTrips->where('status', TripStatus::Completed);
            $result['labels'][] = $d->format('D j');
            $result['on_time'][] = $completed->filter->wasOnTime()->count();
            $result['late'][] = $completed->reject->wasOnTime()->count();
            $result['cancelled'][] = $dayTrips->where('status', TripStatus::Cancelled)->count();
        }

        return $result;
    }

    /**
     * Things a depot manager should act on today.
     *
     * @return list<array{tone: string, title: string, detail: string, url: ?string}>
     */
    public function alerts(): array
    {
        $alerts = [];

        foreach ($this->maintenance->tripsAffectedByUnavailableBuses() as $trip) {
            $alerts[] = [
                'tone' => 'red',
                'title' => "Route {$trip->route->route_no} at {$trip->scheduled_departure->format('H:i')} needs a bus",
                'detail' => "{$trip->bus->registration_no} is ".mb_strtolower($trip->bus->status->label()).' ('.$trip->trip_date->format('D j M').')',
                'url' => route('trips.show', $trip),
            ];
        }

        $drivers = Driver::query()->where('status', DriverStatus::Active)
            ->whereDate('license_expiry', '<=', today()->addDays(LicenseStatus::WARNING_DAYS))
            ->orderBy('license_expiry')->get();

        foreach ($drivers as $driver) {
            $expired = $driver->licenseStatus() === LicenseStatus::Expired;
            $alerts[] = [
                'tone' => $expired ? 'red' : 'amber',
                'title' => $expired ? "{$driver->full_name}'s licence has expired" : "{$driver->full_name}'s licence expires soon",
                'detail' => 'Expiry date '.$driver->license_expiry->format('j M Y'),
                'url' => route('drivers.show', $driver),
            ];
        }

        foreach (Bus::query()->operational()->get()->filter(fn (Bus $b) => $b->isServiceDue()) as $bus) {
            $km = $bus->kmToNextService();
            $alerts[] = [
                'tone' => $km < 0 ? 'red' : 'amber',
                'title' => "{$bus->registration_no} is due for service",
                'detail' => $km < 0 ? number_format(-$km).' km overdue' : number_format($km).' km remaining',
                'url' => route('buses.show', $bus),
            ];
        }

        $workshop = MaintenanceRecord::query()->open()->whereDate('scheduled_for', '<', today())
            ->where('status', 'scheduled')->with('bus')->get();

        foreach ($workshop as $job) {
            $alerts[] = [
                'tone' => 'amber',
                'title' => "Overdue maintenance: {$job->title}",
                'detail' => "{$job->bus->registration_no}, planned for ".$job->scheduled_for->format('j M'),
                'url' => Gate::allows('log-fuel-maintenance') ? route('maintenance.edit', $job) : route('maintenance.index', ['bus' => $job->bus_id]),
            ];
        }

        return $alerts;
    }
}
