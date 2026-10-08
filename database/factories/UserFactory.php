<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Depot;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'depot_id' => Depot::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'role' => UserRole::Staff,
            'is_active' => true,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(['role' => UserRole::Admin]);
    }

    public function supervisor(): static
    {
        return $this->state(['role' => UserRole::Supervisor]);
    }

    public function staff(): static
    {
        return $this->state(['role' => UserRole::Staff]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
