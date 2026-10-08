<?php

namespace App\Services\Scheduling\Rules;

use App\Models\Schedule;
use App\Services\Scheduling\ScheduleProposal;
use Illuminate\Database\Eloquent\Builder;

/**
 * A bus cannot run two trips at once, and needs turnaround time between trips.
 */
class BusDoubleBookingRule extends AbstractDoubleBookingRule
{
    protected function scopeToResource(Builder $query, ScheduleProposal $proposal): void
    {
        $query->where('bus_id', $proposal->bus->id);
    }

    protected function bufferMinutes(): int
    {
        return config('srmss.bus_turnaround_minutes');
    }

    protected function message(ScheduleProposal $proposal, Schedule $other, string $date): string
    {
        return sprintf(
            'Bus %s is already running route %s from %s to %s (first clash on %s). Buses need %d minutes turnaround between trips.',
            $proposal->bus->registration_no,
            $other->route->route_no,
            $other->departureLabel(),
            $other->arrivalLabel(),
            $date,
            $this->bufferMinutes(),
        );
    }
}
