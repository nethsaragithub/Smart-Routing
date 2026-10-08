<?php

namespace App\Services\Scheduling;

use App\Enums\Recurrence;
use App\Models\Bus;
use App\Models\BusRoute;
use App\Models\Driver;
use App\Models\Schedule;
use App\Support\RecurrenceRule;
use Carbon\CarbonImmutable;

/**
 * Immutable description of a schedule that someone wants to save. Conflict
 * rules inspect a proposal rather than a model so that a schedule can be
 * checked before anything is written to the database.
 */
final class ScheduleProposal
{
    public function __construct(
        public readonly BusRoute $route,
        public readonly Bus $bus,
        public readonly Driver $driver,
        public readonly string $departureTime,
        public readonly string $arrivalTime,
        public readonly RecurrenceRule $rule,
        public readonly ?int $ignoreScheduleId = null,
    ) {
    }

    /**
     * @param  array{bus_route_id:int, bus_id:int, driver_id:int, departure_time:string, arrival_time:string,
     *               recurrence:string, weekdays?:array|null, month_days?:array|null, start_date:string, end_date?:string|null}  $data
     */
    public static function fromArray(array $data, ?int $ignoreScheduleId = null): self
    {
        return new self(
            BusRoute::findOrFail($data['bus_route_id']),
            Bus::findOrFail($data['bus_id']),
            Driver::findOrFail($data['driver_id']),
            substr($data['departure_time'], 0, 5),
            substr($data['arrival_time'], 0, 5),
            new RecurrenceRule(
                Recurrence::from($data['recurrence']),
                CarbonImmutable::parse($data['start_date']),
                empty($data['end_date']) ? null : CarbonImmutable::parse($data['end_date']),
                array_map('intval', $data['weekdays'] ?? []),
                array_map('intval', $data['month_days'] ?? []),
            ),
            $ignoreScheduleId,
        );
    }

    public static function fromSchedule(Schedule $schedule): self
    {
        return new self(
            $schedule->route,
            $schedule->bus,
            $schedule->driver,
            $schedule->departureLabel(),
            $schedule->arrivalLabel(),
            $schedule->rule(),
            $schedule->id,
        );
    }

    public function departureMinutes(): int
    {
        return Schedule::minutesOfDay($this->departureTime);
    }

    public function arrivalMinutes(): int
    {
        return Schedule::minutesOfDay($this->arrivalTime);
    }

    public function durationMinutes(): int
    {
        return $this->arrivalMinutes() - $this->departureMinutes();
    }

    /**
     * True if this proposal's time window (padded by a buffer on both sides)
     * overlaps another schedule's window on the same day.
     */
    public function overlapsTimeOf(Schedule $other, int $bufferMinutes = 0): bool
    {
        $otherStart = Schedule::minutesOfDay($other->departure_time);
        $otherEnd = Schedule::minutesOfDay($other->arrival_time);

        return $this->departureMinutes() < $otherEnd + $bufferMinutes
            && $otherStart < $this->arrivalMinutes() + $bufferMinutes;
    }
}
