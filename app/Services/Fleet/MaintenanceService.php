<?php

namespace App\Services\Fleet;

use App\Enums\BusStatus;
use App\Enums\MaintenanceStatus;
use App\Enums\MaintenanceType;
use App\Enums\TripStatus;
use App\Exceptions\SchedulingException;
use App\Models\MaintenanceRecord;
use App\Models\Trip;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Moves maintenance jobs through scheduled -> in workshop -> completed and
 * keeps the bus status and service counters in step.
 */
class MaintenanceService
{
    public function start(MaintenanceRecord $record, CarbonInterface $on): MaintenanceRecord
    {
        if ($record->status !== MaintenanceStatus::Scheduled) {
            throw new SchedulingException('Only scheduled jobs can be started.');
        }

        return DB::transaction(function () use ($record, $on) {
            $record->update(['status' => MaintenanceStatus::InProgress, 'started_on' => $on]);
            $record->bus->update(['status' => BusStatus::Maintenance]);

            return $record;
        });
    }

    public function complete(MaintenanceRecord $record, CarbonInterface $on, ?float $cost, ?int $odometer): MaintenanceRecord
    {
        if ($record->status === MaintenanceStatus::Completed) {
            throw new SchedulingException('This job is already completed.');
        }

        return DB::transaction(function () use ($record, $on, $cost, $odometer) {
            $record->update([
                'status' => MaintenanceStatus::Completed,
                'started_on' => $record->started_on ?? $on,
                'completed_on' => $on,
                'cost' => $cost ?? $record->cost,
                'odometer' => $odometer ?? $record->odometer ?? $record->bus->current_mileage,
            ]);

            $bus = $record->bus;
            $bus->recordMileage($odometer);

            $updates = [];

            // Return the bus to service unless another job still holds it.
            $stillInWorkshop = $bus->maintenanceRecords()
                ->whereKeyNot($record->id)
                ->where('status', MaintenanceStatus::InProgress)
                ->exists();

            if (! $stillInWorkshop && $bus->status === BusStatus::Maintenance) {
                $updates['status'] = BusStatus::Active;
            }

            if ($record->type === MaintenanceType::Routine) {
                $updates['last_service_date'] = $on;
                $updates['last_service_mileage'] = $record->odometer;
            }

            if ($updates) {
                $bus->update($updates);
            }

            return $record;
        });
    }

    /**
     * Upcoming trips that use a bus which is currently off the road, so the
     * depot officer can reassign them.
     *
     * @return Collection<int, Trip>
     */
    public function tripsAffectedByUnavailableBuses(int $days = 2): Collection
    {
        return Trip::query()
            ->with(['route', 'bus', 'driver'])
            ->whereIn('status', TripStatus::open())
            ->whereBetween('trip_date', [today()->toDateString(), today()->addDays($days)->toDateString()])
            ->whereHas('bus', fn ($q) => $q->where('status', '!=', BusStatus::Active))
            ->orderBy('scheduled_departure')
            ->get();
    }
}
