<?php

namespace App\Services\Scheduling;

use App\Enums\DriverStatus;
use App\Enums\LicenseStatus;
use App\Models\Bus;
use App\Models\BusRoute;
use App\Models\Driver;
use App\Models\Schedule;
use Illuminate\Support\Collection;

/**
 * Suggests which buses and drivers suit a route at a given time, based on
 * service type, seating capacity, current status and existing rosters.
 * Used by the schedule form to rank the dropdown options.
 */
class AssignmentAdvisor
{
    /**
     * @return Collection<int, array{id:int, label:string, suitable:bool, notes:list<string>}>
     */
    public function busesFor(BusRoute $route, ?string $departure, ?string $arrival, ?int $ignoreScheduleId = null): Collection
    {
        $busy = $this->busyIds('bus_id', $departure, $arrival, config('srmss.bus_turnaround_minutes'), $ignoreScheduleId);

        return Bus::query()->orderBy('registration_no')->get()
            ->map(function (Bus $bus) use ($route, $busy) {
                $notes = [];

                if (! $bus->status->isOperational()) {
                    $notes[] = $bus->status->label();
                }
                if ($bus->service_type !== $route->service_type) {
                    $notes[] = $bus->service_type->label().' bus';
                }
                if ($route->min_capacity && $bus->seating_capacity < $route->min_capacity) {
                    $notes[] = "Only {$bus->seating_capacity} seats";
                }
                if (in_array($bus->id, $busy, true)) {
                    $notes[] = 'Busy at this time';
                }

                return [
                    'id' => $bus->id,
                    'label' => "{$bus->registration_no} · {$bus->seating_capacity} seats",
                    'suitable' => $notes === [],
                    'notes' => $notes,
                ];
            })
            ->sortByDesc('suitable')
            ->values();
    }

    /**
     * @return Collection<int, array{id:int, label:string, suitable:bool, notes:list<string>}>
     */
    public function driversFor(?string $departure, ?string $arrival, ?int $ignoreScheduleId = null): Collection
    {
        $busy = $this->busyIds('driver_id', $departure, $arrival, config('srmss.driver_rest_minutes'), $ignoreScheduleId);

        return Driver::query()->orderBy('full_name')->get()
            ->map(function (Driver $driver) use ($busy) {
                $notes = [];
                $licence = $driver->licenseStatus();

                if ($driver->status !== DriverStatus::Active) {
                    $notes[] = $driver->status->label();
                }
                if ($licence !== LicenseStatus::Valid) {
                    $notes[] = 'Licence '.mb_strtolower($licence->label());
                }
                if (in_array($driver->id, $busy, true)) {
                    $notes[] = 'Busy at this time';
                }

                return [
                    'id' => $driver->id,
                    'label' => "{$driver->full_name} ({$driver->employee_no})",
                    'suitable' => $notes === [],
                    'notes' => $notes,
                ];
            })
            ->sortByDesc('suitable')
            ->values();
    }

    /** @return list<int> */
    private function busyIds(string $column, ?string $departure, ?string $arrival, int $buffer, ?int $ignore): array
    {
        if (! $departure || ! $arrival) {
            return [];
        }

        $start = Schedule::minutesOfDay($departure);
        $end = Schedule::minutesOfDay($arrival);

        return Schedule::query()->active()
            ->when($ignore, fn ($q) => $q->whereKeyNot($ignore))
            ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', today()))
            ->get([$column, 'departure_time', 'arrival_time'])
            ->filter(fn (Schedule $s) => $start < Schedule::minutesOfDay($s->arrival_time) + $buffer
                && Schedule::minutesOfDay($s->departure_time) < $end + $buffer)
            ->pluck($column)
            ->unique()
            ->values()
            ->all();
    }
}
