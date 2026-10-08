<?php

namespace App\Services\Scheduling\Rules;

use App\Contracts\ConflictRule;
use App\Services\Scheduling\Conflict;
use App\Services\Scheduling\ScheduleProposal;

/**
 * Checks the timetable itself: the journey time should be realistic for the
 * route and the recurrence must produce at least one trip.
 */
class TimetableSanityRule implements ConflictRule
{
    public function check(ScheduleProposal $proposal): iterable
    {
        $planned = $proposal->durationMinutes();
        $expected = $proposal->route->estimated_duration_minutes;

        if ($planned <= 0) {
            yield Conflict::error('Timetable', 'Arrival time must be after departure time.');

            return;
        }

        if ($expected > 0 && $planned < $expected * 0.8) {
            yield Conflict::warning('Timetable', sprintf(
                'The planned journey of %d minutes is much shorter than the usual %d minutes for route %s.',
                $planned,
                $expected,
                $proposal->route->route_no,
            ));
        }

        $runs = $proposal->rule->occurrencesBetween($proposal->rule->start, $proposal->rule->start->addDays(62));

        if ($runs === []) {
            yield Conflict::error('Timetable', 'This timetable never runs. Check the selected days and the date range.');
        }
    }
}
