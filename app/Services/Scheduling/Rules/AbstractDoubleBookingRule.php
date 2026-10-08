<?php

namespace App\Services\Scheduling\Rules;

use App\Contracts\ConflictRule;
use App\Models\Schedule;
use App\Services\Scheduling\Conflict;
use App\Services\Scheduling\ScheduleProposal;
use Illuminate\Database\Eloquent\Builder;

/**
 * Template Method base class: finds other active schedules that share a
 * resource (bus, driver or route) with the proposal and run at an
 * overlapping time on at least one common date.
 */
abstract class AbstractDoubleBookingRule implements ConflictRule
{
    /** Restrict the candidate query to schedules sharing the resource. */
    abstract protected function scopeToResource(Builder $query, ScheduleProposal $proposal): void;

    /** Minutes of separation required between the two time windows. */
    abstract protected function bufferMinutes(): int;

    abstract protected function message(ScheduleProposal $proposal, Schedule $other, string $date): string;

    protected function key(): string
    {
        return static::class;
    }

    public function check(ScheduleProposal $proposal): iterable
    {
        $query = Schedule::query()
            ->active()
            ->with(['route', 'bus', 'driver'])
            ->when($proposal->ignoreScheduleId, fn (Builder $q, int $id) => $q->whereKeyNot($id))
            ->whereDate('start_date', '<=', $proposal->rule->end ?? now()->addYears(5))
            ->where(fn (Builder $q) => $q->whereNull('end_date')
                ->orWhereDate('end_date', '>=', $proposal->rule->start));

        $this->scopeToResource($query, $proposal);

        foreach ($query->get() as $other) {
            if (! $this->timesClash($proposal, $other)) {
                continue;
            }

            $sharedDate = $proposal->rule->firstSharedDateWith($other->rule());

            if ($sharedDate !== null) {
                yield Conflict::error(
                    class_basename($this->key()),
                    $this->message($proposal, $other, $sharedDate->format('D j M Y')),
                    $other->id,
                );
            }
        }
    }

    protected function timesClash(ScheduleProposal $proposal, Schedule $other): bool
    {
        return $proposal->overlapsTimeOf($other, $this->bufferMinutes());
    }
}
