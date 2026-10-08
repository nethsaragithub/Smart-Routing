<?php

namespace App\Services\Scheduling\Rules;

use App\Models\Schedule;
use App\Services\Scheduling\ScheduleProposal;
use Illuminate\Database\Eloquent\Builder;

/**
 * A driver cannot drive two buses at once and must rest between trips.
 */
class DriverDoubleBookingRule extends AbstractDoubleBookingRule
{
    protected function scopeToResource(Builder $query, ScheduleProposal $proposal): void
    {
        $query->where('driver_id', $proposal->driver->id);
    }

    protected function bufferMinutes(): int
    {
        return config('srmss.driver_rest_minutes');
    }

    protected function message(ScheduleProposal $proposal, Schedule $other, string $date): string
    {
        return sprintf(
            '%s is already rostered on route %s from %s to %s (first clash on %s). Drivers need %d minutes rest between trips.',
            $proposal->driver->full_name,
            $other->route->route_no,
            $other->departureLabel(),
            $other->arrivalLabel(),
            $date,
            $this->bufferMinutes(),
        );
    }
}
