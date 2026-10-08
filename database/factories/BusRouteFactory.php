<?php

namespace Database\Factories;

use App\Enums\ServiceType;
use App\Models\BusRoute;
use App\Models\Depot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BusRoute>
 */
class BusRouteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'depot_id' => Depot::factory(),
            'route_no' => (string) fake()->unique()->numberBetween(100, 999),
            'name' => 'Test route',
            'origin' => 'Pettah',
            'destination' => 'Kottawa',
            'distance_km' => 18,
            'estimated_duration_minutes' => 60,
            'service_type' => ServiceType::Normal,
            'min_capacity' => 0,
            'is_active' => true,
        ];
    }
}
