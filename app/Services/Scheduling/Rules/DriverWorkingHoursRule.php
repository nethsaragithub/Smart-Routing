<?php

namespace App\Services\Scheduling\Rules;

use App\Contracts\ConflictRule;
use App\Models\Schedule;
use App\Services\Scheduling\Conflict;
use App\Services\Scheduling\ScheduleProposal;

/**
 * Warns when the driver's average rostered hours per week would exceed
 * their contracted maximum.
 */
class DriverWorkingHoursRule implements ConflictRule
{
    public function check(ScheduleProposal $proposal): iterable
    {
        $driver = $proposal->driver;

        $existingMinutes = Schedule::query()
            ->active()
            ->where('driver_id', $driver->id)
            ->when($proposal->ignoreScheduleId, fn ($q, $id) => $q->whereKeyNot($id))
            ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $proposal->rule->start))
            ->get()
            ->sum(fn (Schedule $s) => $s->durationMinutes() * $s->rule()->runsPerWeek());

        $proposedMinutes = $proposal->durationMinutes() * $proposal->rule->runsPerWeek();
        $weeklyHours = ($existingMinutes + $proposedMinutes) / 60;

        if ($weeklyHours > $driver->max_weekly_hours) {
            yield Conflict::warning('DriverWorkingHours', sprintf(
                '%s would be rostered for about %.1f hours a week, above the %d-hour limit.',
                $driver->full_name,
                $weeklyHours,
                $driver->max_weekly_hours,
            ));
        }
    }
}
