<?php

namespace Database\Factories;

use App\Models\Depot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Depot>
 */
class DepotFactory extends Factory
{
    public function definition(): array
    {
        $town = fake()->unique()->randomElement(['Maharagama', 'Kandy', 'Galle', 'Kurunegala', 'Ratnapura', 'Matara', 'Jaffna', 'Anuradhapura', 'Badulla', 'Negombo']);

        return [
            'code' => strtoupper(substr($town, 0, 3)).fake()->unique()->numberBetween(1, 99),
            'name' => "{$town} Depot",
            'location' => $town,
            'phone' => '0112'.fake()->numerify('######'),
        ];
    }
}
