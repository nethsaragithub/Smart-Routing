<?php

namespace App\Support;

use App\Enums\Recurrence;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Immutable value object describing on which dates a timetable runs.
 *
 *  - daily:   every day between start and end
 *  - weekly:  on the listed ISO weekdays (1 = Monday ... 7 = Sunday)
 *  - monthly: on the listed days of the month (1 ... 31)
 */
final class RecurrenceRule
{
    /** How far ahead to look when the rule has no end date. */
    public const HORIZON_DAYS = 366;

    public readonly CarbonImmutable $start;

    public readonly ?CarbonImmutable $end;

    /**
     * @param  list<int>  $weekdays
     * @param  list<int>  $monthDays
     */
    public function __construct(
        public readonly Recurrence $recurrence,
        CarbonInterface $start,
        ?CarbonInterface $end = null,
        public readonly array $weekdays = [],
        public readonly array $monthDays = [],
    ) {
        $this->start = CarbonImmutable::instance($start)->startOfDay();
        $this->end = $end ? CarbonImmutable::instance($end)->startOfDay() : null;
    }

    public function occursOn(CarbonInterface $date): bool
    {
        $date = CarbonImmutable::instance($date)->startOfDay();

        if ($date->lt($this->start) || ($this->end && $date->gt($this->end))) {
            return false;
        }

        return match ($this->recurrence) {
            Recurrence::Daily => true,
            Recurrence::Weekly => in_array($date->isoWeekday(), $this->weekdays, true),
            Recurrence::Monthly => in_array($date->day, $this->monthDays, true),
        };
    }

    /**
     * Dates on which this rule runs within the given window.
     *
     * @return list<CarbonImmutable>
     */
    public function occurrencesBetween(CarbonInterface $from, CarbonInterface $to): array
    {
        $dates = [];
        $cursor = CarbonImmutable::instance($from)->startOfDay()->max($this->start);
        $last = CarbonImmutable::instance($to)->startOfDay();

        if ($this->end) {
            $last = $last->min($this->end);
        }

        for (; $cursor->lte($last); $cursor = $cursor->addDay()) {
            if ($this->occursOn($cursor)) {
                $dates[] = $cursor;
            }
        }

        return $dates;
    }

    /**
     * First date on which both rules run, or null if they never coincide
     * within the look-ahead horizon.
     */
    public function firstSharedDateWith(self $other, ?CarbonInterface $notBefore = null): ?CarbonImmutable
    {
        $from = $this->start->max($other->start);

        if ($notBefore) {
            $from = $from->max(CarbonImmutable::instance($notBefore)->startOfDay());
        }

        $to = $from->addDays(self::HORIZON_DAYS);

        foreach ([$this->end, $other->end] as $end) {
            if ($end) {
                $to = $to->min($end);
            }
        }

        for ($date = $from; $date->lte($to); $date = $date->addDay()) {
            if ($this->occursOn($date) && $other->occursOn($date)) {
                return $date;
            }
        }

        return null;
    }

    /** Average number of runs per week, used for working-hour estimates. */
    public function runsPerWeek(): float
    {
        return match ($this->recurrence) {
            Recurrence::Daily => 7.0,
            Recurrence::Weekly => (float) count($this->weekdays),
            Recurrence::Monthly => count($this->monthDays) * 7 / 30.44,
        };
    }

    public function describe(): string
    {
        $names = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];

        return match ($this->recurrence) {
            Recurrence::Daily => 'Every day',
            Recurrence::Weekly => count($this->weekdays) === 7
                ? 'Every day'
                : implode(', ', array_map(fn ($d) => $names[$d], $this->sorted($this->weekdays))),
            Recurrence::Monthly => 'Monthly on day '.implode(', ', $this->sorted($this->monthDays)),
        };
    }

    /** @param list<int> $values @return list<int> */
    private function sorted(array $values): array
    {
        sort($values);

        return $values;
    }
}
