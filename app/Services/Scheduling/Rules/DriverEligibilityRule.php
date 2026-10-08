<?php

namespace App\Services\Scheduling\Rules;

use App\Contracts\ConflictRule;
use App\Enums\DriverStatus;
use App\Services\Scheduling\Conflict;
use App\Services\Scheduling\ScheduleProposal;

/**
 * The driver must be active and hold a valid licence for the schedule period.
 */
class DriverEligibilityRule implements ConflictRule
{
    public function check(ScheduleProposal $proposal): iterable
    {
        $driver = $proposal->driver;
        $rule = $proposal->rule;

        if ($driver->status !== DriverStatus::Active) {
            yield Conflict::error('DriverEligibility', "{$driver->full_name} is ".mb_strtolower($driver->status->label()).' and cannot be rostered.');
        }

        if (! $driver->isLicenseValidOn($rule->start)) {
            yield Conflict::error('DriverEligibility', sprintf(
                "%s's driving licence expired on %s.",
                $driver->full_name,
                $driver->license_expiry->format('j M Y'),
            ));

            return;
        }

        $lastDay = $rule->end ?? $rule->start->addDays(30);

        if ($driver->license_expiry->lt($lastDay)) {
            yield Conflict::warning('DriverEligibility', sprintf(
                "%s's licence expires on %s, before this schedule ends. Trips after that date will need another driver.",
                $driver->full_name,
                $driver->license_expiry->format('j M Y'),
            ));
        }
    }
}
