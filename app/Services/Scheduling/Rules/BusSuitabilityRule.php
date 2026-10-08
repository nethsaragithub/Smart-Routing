<?php

namespace App\Services\Scheduling\Rules;

use App\Contracts\ConflictRule;
use App\Enums\BusStatus;
use App\Services\Scheduling\Conflict;
use App\Services\Scheduling\ScheduleProposal;

/**
 * The bus must be roadworthy and suit the route's service type and demand.
 */
class BusSuitabilityRule implements ConflictRule
{
    public function check(ScheduleProposal $proposal): iterable
    {
        $bus = $proposal->bus;
        $route = $proposal->route;

        if ($bus->status !== BusStatus::Active) {
            yield Conflict::error('BusSuitability', "Bus {$bus->registration_no} is {$this->lower($bus->status->label())} and cannot be scheduled.");
        }

        if ($bus->service_type !== $route->service_type) {
            yield Conflict::error('BusSuitability', sprintf(
                'Route %s is a %s service but bus %s is %s.',
                $route->route_no,
                $this->lower($route->service_type->label()),
                $bus->registration_no,
                $this->lower($bus->service_type->label()),
            ));
        }

        if ($route->min_capacity > 0 && $bus->seating_capacity < $route->min_capacity) {
            yield Conflict::warning('BusSuitability', sprintf(
                'Bus %s seats %d passengers; route %s normally needs at least %d.',
                $bus->registration_no,
                $bus->seating_capacity,
                $route->route_no,
                $route->min_capacity,
            ));
        }

        if ($bus->isServiceDue(0)) {
            yield Conflict::warning('BusSuitability', "Bus {$bus->registration_no} is overdue for its routine service.");
        }
    }

    private function lower(string $text): string
    {
        return mb_strtolower($text);
    }
}
