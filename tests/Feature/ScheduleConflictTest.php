<?php

namespace Tests\Feature;

use App\Enums\BusStatus;
use App\Enums\ServiceType;
use App\Models\Schedule;
use App\Models\Trip;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Black-box tests of the Schedule Management module: timetables that would
 * double-book a bus or driver, or crowd a route, must be rejected.
 */
class ScheduleConflictTest extends TestCase
{
    use RefreshDatabase;

    public function test_valid_timetable_is_saved_and_trips_are_generated(): void
    {
        $response = $this->actingAs($this->supervisor())->post('/schedules', $this->scheduleForm());

        $schedule = Schedule::first();
        $response->assertRedirect("/schedules/{$schedule->id}");
        // Today plus the 7-day look-ahead.
        $this->assertSame(8, Trip::where('schedule_id', $schedule->id)->count());
    }

    public function test_bus_cannot_be_on_two_trips_at_the_same_time(): void
    {
        $existing = $this->schedule(['departure_time' => '06:00', 'arrival_time' => '07:00']);

        $this->actingAs($this->supervisor())
            ->post('/schedules', $this->scheduleForm(['bus_id' => $existing->bus_id, 'departure_time' => '06:30', 'arrival_time' => '07:30']))
            ->assertSessionHas('conflicts', fn ($c) => str_contains($c['errors'][0]->message, 'is already running route'));

        $this->assertSame(1, Schedule::count());
    }

    public function test_bus_needs_turnaround_time_between_trips(): void
    {
        $existing = $this->schedule(['departure_time' => '06:00', 'arrival_time' => '07:00']);

        // Leaves 10 minutes after the previous trip ends: inside the 15-minute turnaround.
        $this->actingAs($this->supervisor())
            ->post('/schedules', $this->scheduleForm(['bus_id' => $existing->bus_id, 'departure_time' => '07:10', 'arrival_time' => '08:10']))
            ->assertSessionHas('conflicts');

        // Leaving 20 minutes later is fine.
        $this->actingAs($this->supervisor())
            ->post('/schedules', $this->scheduleForm(['bus_id' => $existing->bus_id, 'departure_time' => '07:20', 'arrival_time' => '08:20']))
            ->assertSessionMissing('conflicts');

        $this->assertSame(2, Schedule::count());
    }

    public function test_driver_cannot_be_double_booked(): void
    {
        $existing = $this->schedule(['departure_time' => '06:00', 'arrival_time' => '07:00']);

        $this->actingAs($this->supervisor())
            ->post('/schedules', $this->scheduleForm(['driver_id' => $existing->driver_id, 'departure_time' => '07:15', 'arrival_time' => '08:15']))
            ->assertSessionHas('conflicts', fn ($c) => str_contains($c['errors'][0]->message, 'rest between trips'));
    }

    public function test_departures_on_the_same_route_must_respect_the_headway(): void
    {
        $existing = $this->schedule(['departure_time' => '06:00', 'arrival_time' => '07:00']);

        $this->actingAs($this->supervisor())
            ->post('/schedules', $this->scheduleForm(['bus_route_id' => $existing->bus_route_id, 'departure_time' => '06:05', 'arrival_time' => '07:05']))
            ->assertSessionHas('conflicts', fn ($c) => str_contains($c['errors'][0]->message, 'at least 10 minutes apart'));
    }

    public function test_timetables_on_different_days_do_not_clash(): void
    {
        $existing = $this->schedule(['recurrence' => 'weekly', 'weekdays' => [6, 7]]); // weekends

        $this->actingAs($this->supervisor())
            ->post('/schedules', $this->scheduleForm(['bus_id' => $existing->bus_id, 'recurrence' => 'weekly', 'weekdays' => [1, 2, 3, 4, 5]]))
            ->assertSessionMissing('conflicts');

        $this->assertSame(2, Schedule::count());
    }

    public function test_driver_with_expired_licence_cannot_be_rostered(): void
    {
        $driver = $this->driver(['license_expiry' => '2026-10-01']);

        $this->actingAs($this->supervisor())
            ->post('/schedules', $this->scheduleForm(['driver_id' => $driver->id]))
            ->assertSessionHas('conflicts', fn ($c) => str_contains($c['errors'][0]->message, 'licence expired'));
    }

    public function test_bus_under_maintenance_cannot_be_scheduled(): void
    {
        $bus = $this->bus(['status' => BusStatus::Maintenance]);

        $this->actingAs($this->supervisor())
            ->post('/schedules', $this->scheduleForm(['bus_id' => $bus->id]))
            ->assertSessionHas('conflicts');

        $this->assertSame(0, Schedule::count());
    }

    public function test_bus_must_match_the_route_service_type(): void
    {
        $route = $this->route(['service_type' => ServiceType::Luxury]);

        $this->actingAs($this->supervisor())
            ->post('/schedules', $this->scheduleForm(['bus_route_id' => $route->id]))
            ->assertSessionHas('conflicts', fn ($c) => str_contains($c['errors'][0]->message, 'luxury'));
    }

    public function test_warnings_must_be_acknowledged_before_saving(): void
    {
        $route = $this->route(['min_capacity' => 60]);   // bus only seats 54: a warning, not an error
        $form = $this->scheduleForm(['bus_route_id' => $route->id]);

        $this->actingAs($this->supervisor())->post('/schedules', $form)
            ->assertSessionHas('conflicts', fn ($c) => $c['errors'] === [] && count($c['warnings']) === 1);
        $this->assertSame(0, Schedule::count());

        $this->actingAs($this->supervisor())->post('/schedules', [...$form, 'acknowledge_warnings' => 1]);
        $this->assertSame(1, Schedule::count());
    }

    public function test_arrival_must_be_after_departure(): void
    {
        $this->actingAs($this->supervisor())
            ->post('/schedules', $this->scheduleForm(['departure_time' => '09:00', 'arrival_time' => '08:00']))
            ->assertSessionHasErrors('arrival_time');
    }

    public function test_live_check_endpoint_reports_conflicts_as_json(): void
    {
        $existing = $this->schedule();

        $this->actingAs($this->supervisor())
            ->postJson('/schedules/check', $this->scheduleForm(['bus_id' => $existing->bus_id]))
            ->assertOk()
            ->assertJsonPath('clear', false)
            ->assertJsonCount(1, 'errors');
    }

    public function test_suspending_a_timetable_removes_its_upcoming_trips(): void
    {
        $this->actingAs($this->supervisor())->post('/schedules', $this->scheduleForm());
        $schedule = Schedule::first();

        $this->actingAs($this->supervisor())->patch("/schedules/{$schedule->id}/status");

        $this->assertSame('suspended', $schedule->fresh()->status->value);
        $this->assertSame(0, $schedule->trips()->count());
    }
}
