<?php

namespace Tests;

use App\Models\Bus;
use App\Models\BusRoute;
use App\Models\Depot;
use App\Models\Driver;
use App\Models\Schedule;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Carbon;

abstract class TestCase extends BaseTestCase
{
    protected Depot $depot;

    protected function setUp(): void
    {
        parent::setUp();

        // A fixed "now" keeps date-based rules (licence expiry, trip days) predictable.
        Carbon::setTestNow(Carbon::parse('2026-10-08 10:00:00'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function depot(): Depot
    {
        return $this->depot ??= Depot::factory()->create();
    }

    protected function supervisor(): User
    {
        return User::factory()->supervisor()->for($this->depot())->create();
    }

    protected function staff(): User
    {
        return User::factory()->staff()->for($this->depot())->create();
    }

    protected function admin(): User
    {
        return User::factory()->admin()->for($this->depot())->create();
    }

    protected function route(array $attributes = []): BusRoute
    {
        return BusRoute::factory()->for($this->depot())->create($attributes);
    }

    protected function bus(array $attributes = []): Bus
    {
        return Bus::factory()->for($this->depot())->create($attributes);
    }

    protected function driver(array $attributes = []): Driver
    {
        return Driver::factory()->for($this->depot())->create($attributes);
    }

    /** An active daily schedule in the test depot. */
    protected function schedule(array $attributes = []): Schedule
    {
        return Schedule::factory()->create([
            'depot_id' => $this->depot()->id,
            'bus_route_id' => $attributes['bus_route_id'] ?? $this->route()->id,
            'bus_id' => $attributes['bus_id'] ?? $this->bus()->id,
            'driver_id' => $attributes['driver_id'] ?? $this->driver()->id,
            'start_date' => '2026-10-01',
            ...$attributes,
        ]);
    }

    /** Valid form data for a new schedule. */
    protected function scheduleForm(array $overrides = []): array
    {
        return [
            'bus_route_id' => $overrides['bus_route_id'] ?? $this->route()->id,
            'bus_id' => $overrides['bus_id'] ?? $this->bus()->id,
            'driver_id' => $overrides['driver_id'] ?? $this->driver()->id,
            'departure_time' => '06:00',
            'arrival_time' => '07:00',
            'recurrence' => 'daily',
            'start_date' => '2026-10-08',
            ...$overrides,
        ];
    }
}
