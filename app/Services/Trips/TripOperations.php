<?php

namespace App\Services\Trips;

use App\Enums\AdjustmentReason;
use App\Enums\AdjustmentType;
use App\Enums\TripStatus;
use App\Exceptions\SchedulingException;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\Trip;
use App\Models\TripAdjustment;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * All state changes to a trip on the day of operation. Every change is
 * written to the trip_adjustments audit log.
 */
class TripOperations
{
    public function __construct(private readonly TripClashChecker $clashes)
    {
    }

    public function depart(Trip $trip, CarbonInterface $at, ?int $odometer, ?User $by): Trip
    {
        $this->ensureOpen($trip);

        if ($trip->hasDeparted()) {
            throw new SchedulingException('This trip has already departed.');
        }

        if ($trip->trip_date->isAfter(today())) {
            throw new SchedulingException('A departure can only be recorded on the day of the trip.');
        }

        $delay = max(0, (int) $trip->scheduled_departure->diffInMinutes($at, false));

        return DB::transaction(function () use ($trip, $at, $odometer, $by, $delay) {
            $trip->update([
                'actual_departure' => $at,
                'odometer_start' => $odometer ?? $trip->bus->current_mileage,
                'delay_minutes' => $delay,
                'status' => $delay > config('srmss.on_time_grace_minutes') ? TripStatus::Delayed : TripStatus::OnTime,
            ]);

            $this->log($trip, AdjustmentType::Departure, null, sprintf(
                'Departed at %s%s',
                $at->format('H:i'),
                $delay > 0 ? " ({$delay} min late)" : '',
            ), null, $by);

            return $trip;
        });
    }

    public function arrive(Trip $trip, CarbonInterface $at, ?int $odometer, ?int $passengers, ?User $by): Trip
    {
        $this->ensureOpen($trip);

        if (! $trip->hasDeparted()) {
            throw new SchedulingException('Record the departure before recording the arrival.');
        }

        if ($at->lt($trip->actual_departure)) {
            throw new SchedulingException('Arrival time cannot be earlier than the departure time.');
        }

        if ($odometer !== null && $trip->odometer_start !== null && $odometer < $trip->odometer_start) {
            throw new SchedulingException("The closing odometer must be at least {$trip->odometer_start} km.");
        }

        return DB::transaction(function () use ($trip, $at, $odometer, $passengers, $by) {
            $trip->update([
                'actual_arrival' => $at,
                'odometer_end' => $odometer,
                'passenger_count' => $passengers,
                'status' => TripStatus::Completed,
            ]);

            $trip->bus->recordMileage($odometer);

            $this->log($trip, AdjustmentType::Arrival, null, "Arrived at {$at->format('H:i')}", null, $by);

            return $trip;
        });
    }

    public function reportDelay(Trip $trip, int $minutes, AdjustmentReason $reason, ?string $note, ?User $by): Trip
    {
        $this->ensureOpen($trip);

        $trip->update(['delay_minutes' => $minutes, 'status' => TripStatus::Delayed]);
        $this->log($trip, AdjustmentType::Delay, $reason, "Delay of {$minutes} min reported", $note, $by);

        return $trip;
    }

    public function cancel(Trip $trip, AdjustmentReason $reason, ?string $note, ?User $by): Trip
    {
        $this->ensureOpen($trip);

        $trip->update(['status' => TripStatus::Cancelled]);
        $this->log($trip, AdjustmentType::Cancellation, $reason, 'Trip cancelled', $note, $by);

        return $trip;
    }

    /**
     * Swap the bus and/or driver for one trip, e.g. after a breakdown.
     */
    public function reassign(Trip $trip, ?Bus $bus, ?Driver $driver, AdjustmentReason $reason, ?string $note, ?User $by): Trip
    {
        $this->ensureOpen($trip);

        $this->clashes->assertBusFree($trip, $bus);
        $this->clashes->assertDriverFree($trip, $driver);

        return DB::transaction(function () use ($trip, $bus, $driver, $reason, $note, $by) {
            if ($bus && $bus->id !== $trip->bus_id) {
                $old = $trip->bus->registration_no;
                $trip->update(['bus_id' => $bus->id]);
                $this->log($trip, AdjustmentType::BusChange, $reason, "Bus {$old} replaced by {$bus->registration_no}", $note, $by);
            }

            if ($driver && $driver->id !== $trip->driver_id) {
                $old = $trip->driver->full_name;
                $trip->update(['driver_id' => $driver->id]);
                $this->log($trip, AdjustmentType::DriverChange, $reason, "Driver {$old} replaced by {$driver->full_name}", $note, $by);
            }

            return $trip->refresh();
        });
    }

    private function ensureOpen(Trip $trip): void
    {
        if (! $trip->status->isOpen()) {
            throw new SchedulingException("This trip is already {$trip->status->label()} and can no longer be changed.");
        }
    }

    private function log(Trip $trip, AdjustmentType $type, ?AdjustmentReason $reason, string $details, ?string $note, ?User $by): void
    {
        TripAdjustment::create([
            'trip_id' => $trip->id,
            'user_id' => $by?->id,
            'type' => $type,
            'reason' => $reason,
            'details' => $details,
            'note' => $note,
        ]);
    }
}
