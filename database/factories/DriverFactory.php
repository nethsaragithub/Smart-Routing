<?php

namespace Database\Factories;

use App\Enums\DriverStatus;
use App\Models\Depot;
use App\Models\Driver;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Driver>
 */
class DriverFactory extends Factory
{
    public function definition(): array
    {
        return [
            'depot_id' => Depot::factory(),
            'employee_no' => 'EMP'.fake()->unique()->numerify('#####'),
            'full_name' => fake()->name(),
            'nic' => fake()->unique()->numerify('19##########'),
            'phone' => '07'.fake()->numerify('########'),
            'license_no' => 'B'.fake()->unique()->numerify('#######'),
            'license_class' => 'D',
            'license_expiry' => now()->addYears(2)->toDateString(),
            'joined_on' => now()->subYears(3)->toDateString(),
            'status' => DriverStatus::Active,
            'max_weekly_hours' => 60,
        ];
    }

    public function expiredLicence(): static
    {
        return $this->state(['license_expiry' => now()->subDays(5)->toDateString()]);
    }
}
