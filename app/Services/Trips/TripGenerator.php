<?php

namespace App\Services\Trips;

use App\Enums\TripStatus;
use App\Models\Schedule;
use App\Models\Trip;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Turns recurring schedules into concrete daily trips. Generation is
 * idempotent: running it twice for the same day creates no duplicates.
 */
class TripGenerator
{
    /**
     * @return int number of trips created
     */
    public function generateBetween(CarbonInterface $from, CarbonInterface $to): int
    {
        $created = 0;
        $from = CarbonImmutable::instance($from)->startOfDay();
        $to = CarbonImmutable::instance($to)->startOfDay();

        $schedules = Schedule::query()
            ->active()
            ->whereDate('start_date', '<=', $to)
            ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $from))
            ->with('route')
            ->get();

        for ($date = $from; $date->lte($to); $date = $date->addDay()) {
            foreach ($schedules as $schedule) {
                if ($schedule->occursOn($date) && $this->createTrip($schedule, $date)) {
                    $created++;
                }
            }
        }

        return $created;
    }

    public function generateFor(CarbonInterface $date): int
    {
        return $this->generateBetween($date, $date);
    }

    /** Returns true when a new trip was created. */
    private function createTrip(Schedule $schedule, CarbonImmutable $date): bool
    {
        $trip = Trip::withoutGlobalScopes()->firstOrCreate(
            ['schedule_id' => $schedule->id, 'trip_date' => $date->toDateString()],
            [
                'depot_id' => $schedule->depot_id,
                'bus_route_id' => $schedule->bus_route_id,
                'bus_id' => $schedule->bus_id,
                'driver_id' => $schedule->driver_id,
                'scheduled_departure' => $date->setTimeFromTimeString($schedule->departure_time),
                'scheduled_arrival' => $date->setTimeFromTimeString($schedule->arrival_time),
                'status' => TripStatus::Scheduled,
            ],
        );

        return $trip->wasRecentlyCreated;
    }
}
