<?php

namespace Database\Factories;

use App\Enums\BusStatus;
use App\Enums\ServiceType;
use App\Models\Bus;
use App\Models\Depot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Bus>
 */
class BusFactory extends Factory
{
    public function definition(): array
    {
        return [
            'depot_id' => Depot::factory(),
            'registration_no' => strtoupper(fake()->unique()->bothify('N?-####')),
            'make' => 'Ashok Leyland',
            'model' => 'Viking',
            'year_of_manufacture' => 2018,
            'seating_capacity' => 54,
            'service_type' => ServiceType::Normal,
            'fuel_type' => 'diesel',
            'current_mileage' => 120000,
            'service_interval_km' => 10000,
            'last_service_mileage' => 118000,
            'status' => BusStatus::Active,
        ];
    }

    public function inMaintenance(): static
    {
        return $this->state(['status' => BusStatus::Maintenance]);
    }
}
