<?php

namespace Database\Factories;

use App\Enums\Recurrence;
use App\Enums\ScheduleStatus;
use App\Models\Bus;
use App\Models\BusRoute;
use App\Models\Depot;
use App\Models\Driver;
use App\Models\Schedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Schedule>
 */
class ScheduleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'depot_id' => Depot::factory(),
            'bus_route_id' => BusRoute::factory(),
            'bus_id' => Bus::factory(),
            'driver_id' => Driver::factory(),
            'departure_time' => '06:00',
            'arrival_time' => '07:00',
            'recurrence' => Recurrence::Daily,
            'start_date' => today()->toDateString(),
            'status' => ScheduleStatus::Active,
        ];
    }

    /** Point every relation at the same depot. */
    public function forDepot(Depot $depot): static
    {
        return $this->state([
            'depot_id' => $depot->id,
            'bus_route_id' => BusRoute::factory()->for($depot),
            'bus_id' => Bus::factory()->for($depot),
            'driver_id' => Driver::factory()->for($depot),
        ]);
    }
}
