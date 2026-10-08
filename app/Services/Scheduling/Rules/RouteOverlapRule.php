<?php

namespace App\Services\Scheduling\Rules;

use App\Models\Schedule;
use App\Services\Scheduling\ScheduleProposal;
use Illuminate\Database\Eloquent\Builder;

/**
 * Two departures on the same route must be separated by the minimum headway,
 * otherwise buses bunch together and compete for the same passengers.
 */
class RouteOverlapRule extends AbstractDoubleBookingRule
{
    protected function scopeToResource(Builder $query, ScheduleProposal $proposal): void
    {
        $query->where('bus_route_id', $proposal->route->id);
    }

    protected function bufferMinutes(): int
    {
        return config('srmss.route_headway_minutes');
    }

    /** Only the departure times matter for headway, not the whole trip. */
    protected function timesClash(ScheduleProposal $proposal, Schedule $other): bool
    {
        $gap = abs($proposal->departureMinutes() - Schedule::minutesOfDay($other->departure_time));

        return $gap < $this->bufferMinutes();
    }

    protected function message(ScheduleProposal $proposal, Schedule $other, string $date): string
    {
        return sprintf(
            'Route %s already has a departure at %s (bus %s). Departures on the same route must be at least %d minutes apart (first clash on %s).',
            $proposal->route->route_no,
            $other->departureLabel(),
            $other->bus->registration_no,
            $this->bufferMinutes(),
            $date,
        );
    }
}
