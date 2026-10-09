<?php

namespace Tests\Feature;

use App\Enums\ScheduleStatus;
use App\Enums\TripStatus;
use App\Models\Bus;
use App\Models\Driver;
use App\Models\FuelLog;
use App\Models\Trip;
use App\Services\Trips\TripGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Removing a bus, driver or route must not change or hide past records.
 */
class RemovedRecordsTest extends TestCase
{
    use RefreshDatabase;

    /** A completed trip from yesterday whose timetable has since been suspended. */
    private function pastTrip(): Trip
    {
        $schedule = $this->schedule(['departure_time' => '09:00', 'arrival_time' => '10:00']);
        app(TripGenerator::class)->generateFor(today()->subDay());
        $schedule->update(['status' => ScheduleStatus::Suspended]);

        $trip = Trip::firstOrFail();
        $trip->update([
            'status' => TripStatus::Completed,
            'actual_departure' => $trip->scheduled_departure,
            'actual_arrival' => $trip->scheduled_arrival,
        ]);

        return $trip;
    }

    public function test_administrator_can_edit_and_remove_buses_and_drivers(): void
    {
        $bus = $this->bus();
        $driver = $this->driver();
        $admin = $this->admin();

        $this->actingAs($admin)->get("/buses/{$bus->id}/edit")->assertOk();
        $this->actingAs($admin)->get("/drivers/{$driver->id}/edit")->assertOk();

        $this->actingAs($admin)->delete("/buses/{$bus->id}")->assertRedirect('/buses');
        $this->actingAs($admin)->delete("/drivers/{$driver->id}")->assertRedirect('/drivers');

        $this->assertSoftDeleted($bus);
        $this->assertSoftDeleted($driver);
    }

    public function test_removing_a_bus_and_driver_keeps_their_trips_and_fuel_history(): void
    {
        $trip = $this->pastTrip();
        $fuel = FuelLog::create([
            'depot_id' => $trip->depot_id, 'bus_id' => $trip->bus_id, 'driver_id' => $trip->driver_id,
            'filled_on' => '2026-10-07', 'odometer' => 130000, 'litres' => 80, 'price_per_litre' => 300,
        ]);
        $admin = $this->admin();

        $this->actingAs($admin)->delete("/buses/{$trip->bus_id}")->assertSessionHas('success');
        $this->actingAs($admin)->delete("/drivers/{$trip->driver_id}")->assertSessionHas('success');

        // The records still point at the same bus and driver.
        $this->assertSame($trip->bus_id, $trip->fresh()->bus_id);
        $this->assertSame($trip->driver_id, $fuel->fresh()->driver_id);

        $staff = $this->staff();
        $this->actingAs($staff)->get("/trips/{$trip->id}")->assertOk()->assertSee($trip->bus->registration_no);
        $this->actingAs($staff)->get("/buses/{$trip->bus_id}")->assertOk()->assertSee('was removed');
        $this->actingAs($staff)->get("/drivers/{$trip->driver_id}")->assertOk()->assertSee('was removed');
        $this->actingAs($staff)->get('/fuel?period=monthly&date=2026-10-07')->assertOk()->assertSee($trip->bus->registration_no);
        $this->actingAs($staff)->get("/fuel/{$fuel->id}/edit")->assertOk()->assertSee('(removed)', false);
    }

    public function test_reports_still_include_removed_drivers_and_routes(): void
    {
        $trip = $this->pastTrip();
        $driver = Driver::find($trip->driver_id);
        $routeNo = $trip->route->route_no;

        $admin = $this->admin();
        $this->actingAs($admin)->delete("/drivers/{$driver->id}");
        $this->actingAs($admin)->delete("/routes/{$trip->bus_route_id}");
        $this->assertSoftDeleted($driver);

        $this->actingAs($admin)->get('/reports/driver-hours?period=weekly')->assertOk()->assertSee($driver->full_name);
        $this->actingAs($admin)->get('/reports/route-performance?period=weekly')->assertOk()->assertSee("Route {$routeNo}", false);
    }

    public function test_bus_with_upcoming_trips_cannot_be_removed(): void
    {
        $schedule = $this->schedule();
        app(TripGenerator::class)->generateFor(today()->addDay());
        $schedule->update(['status' => ScheduleStatus::Suspended]);
        Trip::query()->update(['status' => TripStatus::Scheduled]);

        $this->actingAs($this->admin())->delete("/buses/{$schedule->bus_id}")->assertSessionHas('error');

        $this->assertNotSoftDeleted(Bus::find($schedule->bus_id));
    }

    public function test_removed_bus_cannot_be_chosen_for_new_records(): void
    {
        $bus = $this->bus();
        $bus->delete();

        $this->actingAs($this->staff())->post('/fuel', [
            'bus_id' => $bus->id, 'filled_on' => '2026-10-08', 'odometer' => 200000, 'litres' => 50, 'price_per_litre' => 300,
        ])->assertSessionHasErrors('bus_id');
    }

    public function test_timetable_with_a_removed_bus_cannot_resume(): void
    {
        $schedule = $this->schedule(['status' => ScheduleStatus::Suspended]);
        Bus::find($schedule->bus_id)->delete();

        $this->actingAs($this->admin())->patch("/schedules/{$schedule->id}/status")->assertSessionHas('error');

        $this->assertSame(ScheduleStatus::Suspended, $schedule->fresh()->status);
    }
}
