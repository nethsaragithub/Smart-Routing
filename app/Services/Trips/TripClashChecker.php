<?php

namespace App\Services\Trips;

use App\Enums\TripStatus;
use App\Exceptions\SchedulingException;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\Trip;
use Illuminate\Database\Eloquent\Builder;

/**
 * Checks a single day's trips for double bookings when a bus or driver is
 * swapped at short notice.
 */
class TripClashChecker
{
    public function assertBusFree(Trip $trip, ?Bus $bus): void
    {
        if (! $bus || $bus->id === $trip->bus_id) {
            return;
        }

        if (! $bus->status->isOperational()) {
            throw new SchedulingException("Bus {$bus->registration_no} is {$bus->status->label()}.");
        }

        if ($clash = $this->findClash($trip, 'bus_id', $bus->id, config('srmss.bus_turnaround_minutes'))) {
            throw new SchedulingException(sprintf(
                'Bus %s is already on route %s from %s to %s that day.',
                $bus->registration_no,
                $clash->route->route_no,
                $clash->scheduled_departure->format('H:i'),
                $clash->scheduled_arrival->format('H:i'),
            ));
        }
    }

    public function assertDriverFree(Trip $trip, ?Driver $driver): void
    {
        if (! $driver || $driver->id === $trip->driver_id) {
            return;
        }

        if (! $driver->isAvailableOn($trip->trip_date)) {
            throw new SchedulingException("{$driver->full_name} is not available (status or licence) on {$trip->trip_date->format('j M')}.");
        }

        if ($clash = $this->findClash($trip, 'driver_id', $driver->id, config('srmss.driver_rest_minutes'))) {
            throw new SchedulingException(sprintf(
                '%s is already driving route %s from %s to %s that day.',
                $driver->full_name,
                $clash->route->route_no,
                $clash->scheduled_departure->format('H:i'),
                $clash->scheduled_arrival->format('H:i'),
            ));
        }
    }

    /**
     * IDs of buses / drivers busy during the trip's time window.
     *
     * @return array{buses: list<int>, drivers: list<int>}
     */
    public function busyResources(Trip $trip): array
    {
        $trips = $this->overlapping($trip, max(config('srmss.bus_turnaround_minutes'), config('srmss.driver_rest_minutes')))->get(['bus_id', 'driver_id']);

        return [
            'buses' => $trips->pluck('bus_id')->unique()->values()->all(),
            'drivers' => $trips->pluck('driver_id')->unique()->values()->all(),
        ];
    }

    private function findClash(Trip $trip, string $column, int $id, int $buffer): ?Trip
    {
        return $this->overlapping($trip, $buffer)->where($column, $id)->with('route')->first();
    }

    private function overlapping(Trip $trip, int $buffer): Builder
    {
        $start = $trip->scheduled_departure->copy()->subMinutes($buffer);
        $end = $trip->scheduled_arrival->copy()->addMinutes($buffer);

        return Trip::query()
            ->whereKeyNot($trip->id)
            ->whereDate('trip_date', $trip->trip_date)
            ->where('status', '!=', TripStatus::Cancelled)
            ->where('scheduled_departure', '<', $end)
            ->where('scheduled_arrival', '>', $start);
    }
}
