<?php

namespace App\Services\Scheduling;

use App\Enums\ScheduleStatus;
use App\Enums\TripStatus;
use App\Models\Schedule;
use App\Models\User;
use App\Services\Trips\TripGenerator;
use Illuminate\Support\Facades\DB;

/**
 * Application service for timetable changes. Keeps the already-generated
 * future trips consistent with the schedule they came from.
 */
class ScheduleManager
{
    /** Days of trips generated ahead whenever a timetable changes. */
    public const LOOKAHEAD_DAYS = 7;

    public function __construct(
        private readonly ScheduleConflictDetector $detector,
        private readonly TripGenerator $generator,
    ) {
    }

    /** @param array<string, mixed> $data */
    public function check(array $data, ?Schedule $existing = null): ConflictReport
    {
        return $this->detector->detect(ScheduleProposal::fromArray($data, $existing?->id));
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, ?User $by): Schedule
    {
        return DB::transaction(function () use ($data, $by) {
            $schedule = Schedule::create([...$data, 'status' => ScheduleStatus::Active, 'created_by' => $by?->id]);
            $this->refreshUpcomingTrips($schedule);

            return $schedule;
        });
    }

    /** @param array<string, mixed> $data */
    public function update(Schedule $schedule, array $data): Schedule
    {
        return DB::transaction(function () use ($schedule, $data) {
            $schedule->update($data);
            $this->refreshUpcomingTrips($schedule);

            return $schedule;
        });
    }

    public function suspend(Schedule $schedule): void
    {
        DB::transaction(function () use ($schedule) {
            $schedule->update(['status' => ScheduleStatus::Suspended]);
            $this->removeUnstartedTrips($schedule);
        });
    }

    public function resume(Schedule $schedule): void
    {
        DB::transaction(function () use ($schedule) {
            $schedule->update(['status' => ScheduleStatus::Active]);
            $this->refreshUpcomingTrips($schedule);
        });
    }

    public function delete(Schedule $schedule): void
    {
        DB::transaction(function () use ($schedule) {
            $this->removeUnstartedTrips($schedule);
            $schedule->delete(); // past trips keep their history (schedule_id set to null)
        });
    }

    /**
     * Replace trips that have not started yet so they reflect the latest
     * timetable, then generate the coming week.
     */
    private function refreshUpcomingTrips(Schedule $schedule): void
    {
        $this->removeUnstartedTrips($schedule);

        if ($schedule->status === ScheduleStatus::Active) {
            $this->generator->generateBetween(today(), today()->addDays(self::LOOKAHEAD_DAYS));
        }
    }

    private function removeUnstartedTrips(Schedule $schedule): void
    {
        $schedule->trips()
            ->where('status', TripStatus::Scheduled)
            ->whereNull('actual_departure')
            ->whereDate('trip_date', '>=', today())
            ->doesntHave('adjustments')
            ->delete();
    }
}
