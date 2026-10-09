<?php

namespace Tests\Feature;

use App\Enums\MaintenanceCategory;
use App\Enums\MaintenanceStatus;
use App\Enums\MaintenanceType;
use App\Models\Bus;
use App\Models\Depot;
use App\Models\FuelLog;
use App\Models\MaintenanceRecord;
use App\Models\Trip;
use App\Models\User;
use App\Services\Trips\TripGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public static function staffForbiddenPages(): array
    {
        return [
            'create route' => ['/routes/create'],
            'create schedule' => ['/schedules/create'],
            'add bus' => ['/buses/create'],
            'add driver' => ['/drivers/create'],
            'bus list' => ['/buses'],
            'driver list' => ['/drivers'],
            'route list' => ['/routes'],
            'schedule list' => ['/schedules'],
            'timetable' => ['/timetable'],
            'reports' => ['/reports'],
            'users' => ['/users'],
            'depots' => ['/depots'],
        ];
    }

    #[DataProvider('staffForbiddenPages')]
    public function test_operations_staff_cannot_open_management_pages(string $url): void
    {
        $this->actingAs($this->staff())->get($url)->assertForbidden();
    }

    public function test_operations_staff_can_use_daily_operation_pages(): void
    {
        $staff = $this->staff();

        foreach (['/dashboard', '/profile', '/trips', '/fuel', '/fuel/create', '/maintenance', '/maintenance/create'] as $url) {
            $this->actingAs($staff)->get($url)->assertOk();
        }
    }

    public function test_operations_staff_can_view_the_bus_driver_and_route_of_a_trip(): void
    {
        $schedule = $this->schedule();
        $staff = $this->staff();

        $this->actingAs($staff)->get("/buses/{$schedule->bus_id}")->assertOk();
        $this->actingAs($staff)->get("/drivers/{$schedule->driver_id}")->assertOk();
        $this->actingAs($staff)->get("/routes/{$schedule->bus_route_id}")->assertOk();
        $this->actingAs($staff)->get("/schedules/{$schedule->id}")->assertOk();
    }

    public function test_operations_staff_run_trips_but_cannot_assign_them(): void
    {
        $trip = $this->todaysTrip();
        $staff = $this->staff();

        $this->actingAs($staff)->post("/trips/{$trip->id}/depart", ['time' => '09:00'])->assertSessionHas('success');
        $this->actingAs($staff)->post("/trips/{$trip->id}/delay", ['minutes' => 5, 'reason' => 'traffic'])->assertSessionHas('success');
        $this->actingAs($staff)->post("/trips/{$trip->id}/reassign", ['bus_id' => $this->bus()->id, 'reason' => 'breakdown'])->assertForbidden();
        $this->actingAs($staff)->post('/trips/generate', ['from' => '2026-10-08', 'to' => '2026-10-08'])->assertForbidden();
        $this->actingAs($staff)->get("/trips/{$trip->id}/edit")->assertForbidden();
    }

    public function test_operations_staff_can_record_but_not_delete_fuel_and_maintenance(): void
    {
        [$log, $job] = $this->fuelAndMaintenance();
        $staff = $this->staff();

        $this->actingAs($staff)->get("/fuel/{$log->id}/edit")->assertOk()->assertDontSee('Delete entry');
        $this->actingAs($staff)->get("/maintenance/{$job->id}/edit")->assertOk()->assertDontSee('Delete job');
        $this->actingAs($staff)->delete("/fuel/{$log->id}")->assertForbidden();
        $this->actingAs($staff)->delete("/maintenance/{$job->id}")->assertForbidden();

        $this->actingAs($this->admin())->delete("/fuel/{$log->id}")->assertRedirect('/fuel');
        $this->assertModelMissing($log);
    }

    public static function supervisorForbiddenRequests(): array
    {
        return [
            'users' => ['get', '/users'],
            'depots' => ['get', '/depots'],
            'add bus' => ['get', '/buses/create'],
            'add driver' => ['get', '/drivers/create'],
            'create route' => ['get', '/routes/create'],
            'create schedule' => ['get', '/schedules/create'],
            'log fuel' => ['get', '/fuel/create'],
            'log maintenance' => ['get', '/maintenance/create'],
            'save bus' => ['post', '/buses'],
            'save driver' => ['post', '/drivers'],
            'save route' => ['post', '/routes'],
            'save schedule' => ['post', '/schedules'],
        ];
    }

    #[DataProvider('supervisorForbiddenRequests')]
    public function test_supervisor_cannot_manage_master_data_or_logs(string $method, string $url): void
    {
        $this->actingAs($this->supervisor())->{$method}($url)->assertForbidden();
    }

    public function test_supervisor_can_view_depot_records(): void
    {
        $schedule = $this->schedule();
        [$log, $job] = $this->fuelAndMaintenance();
        $supervisor = $this->supervisor();

        $pages = [
            '/dashboard', '/profile', '/trips', '/buses', '/drivers', '/routes', '/schedules', '/timetable', '/fuel', '/maintenance', '/reports',
            "/buses/{$schedule->bus_id}", "/drivers/{$schedule->driver_id}", "/routes/{$schedule->bus_route_id}", "/schedules/{$schedule->id}",
        ];

        foreach ($pages as $url) {
            $this->actingAs($supervisor)->get($url)->assertOk();
        }

        $this->actingAs($supervisor)->get("/fuel/{$log->id}/edit")->assertForbidden();
        $this->actingAs($supervisor)->delete("/maintenance/{$job->id}")->assertForbidden();
    }

    public function test_supervisor_assigns_trips_and_records_delays_but_does_not_run_them(): void
    {
        $this->schedule(['departure_time' => '09:00', 'arrival_time' => '10:00']);
        $supervisor = $this->supervisor();

        $this->actingAs($supervisor)->post('/trips/generate', ['from' => '2026-10-08', 'to' => '2026-10-08'])->assertSessionHas('success');
        $trip = Trip::firstOrFail();

        $this->actingAs($supervisor)->post("/trips/{$trip->id}/reassign", ['bus_id' => $this->bus()->id, 'reason' => 'breakdown'])->assertSessionHas('success');
        $this->actingAs($supervisor)->post("/trips/{$trip->id}/delay", ['minutes' => 15, 'reason' => 'traffic'])->assertSessionHas('success');
        $this->actingAs($supervisor)->post("/trips/{$trip->id}/depart")->assertForbidden();
        $this->actingAs($supervisor)->post("/trips/{$trip->id}/arrive")->assertForbidden();
        $this->actingAs($supervisor)->post("/trips/{$trip->id}/cancel", ['reason' => 'breakdown'])->assertForbidden();
        $this->actingAs($supervisor)->get("/trips/{$trip->id}/edit")->assertForbidden();
    }

    public function test_trip_page_shows_the_controls_each_role_may_use(): void
    {
        $trip = $this->todaysTrip();
        $url = "/trips/{$trip->id}";

        $this->actingAs($this->staff())->get($url)->assertOk()
            ->assertSee('Record departure')->assertSee('Report delay')->assertSee('Cancel trip')
            ->assertDontSee('Swap bus/driver')->assertDontSee('Edit trip');

        $this->actingAs($this->supervisor())->get($url)->assertOk()
            ->assertSee('Swap bus/driver')->assertSee('Report delay')
            ->assertDontSee('Record departure')->assertDontSee('Cancel trip')->assertDontSee('Edit trip');

        $this->actingAs($this->admin())->get($url)->assertOk()
            ->assertSee('Record departure')->assertSee('Swap bus/driver')->assertSee('Edit trip');
    }

    public function test_users_only_see_records_from_their_own_depot(): void
    {
        $ownBus = $this->bus(['registration_no' => 'NB-1111']);
        $otherBus = Bus::factory()->for(Depot::factory())->create(['registration_no' => 'NB-2222']);

        $supervisor = $this->supervisor();

        $this->actingAs($supervisor)->get('/buses')->assertSee('NB-1111')->assertDontSee('NB-2222');
        $this->actingAs($supervisor)->get("/buses/{$ownBus->id}")->assertOk();
        $this->actingAs($supervisor)->get("/buses/{$otherBus->id}")->assertNotFound();
        $this->actingAs($this->staff())->get("/buses/{$otherBus->id}")->assertNotFound();
    }

    public function test_administrator_can_switch_depot(): void
    {
        $admin = $this->admin();
        $other = Depot::factory()->create(['name' => 'Galle Depot']);
        Bus::factory()->for($other)->create(['registration_no' => 'NG-9999']);

        $this->actingAs($admin)->post('/depot/switch', ['depot_id' => $other->id])->assertRedirect('/dashboard');
        $this->actingAs($admin)->get('/buses')->assertSee('NG-9999');
    }

    public function test_supervisor_cannot_switch_depot(): void
    {
        $other = Depot::factory()->create();

        $this->actingAs($this->supervisor())->post('/depot/switch', ['depot_id' => $other->id])->assertForbidden();
    }

    public function test_deactivated_user_is_signed_out_on_next_request(): void
    {
        $user = User::factory()->inactive()->for($this->depot())->create();

        $this->actingAs($user)->get('/dashboard')->assertRedirect('/login');
        $this->assertGuest();
    }

    private function todaysTrip(): Trip
    {
        $this->schedule(['departure_time' => '09:00', 'arrival_time' => '10:00']);
        app(TripGenerator::class)->generateFor(today());

        return Trip::firstOrFail();
    }

    /** @return array{0: FuelLog, 1: MaintenanceRecord} */
    private function fuelAndMaintenance(): array
    {
        $bus = $this->bus();

        return [
            FuelLog::create([
                'depot_id' => $bus->depot_id, 'bus_id' => $bus->id, 'filled_on' => '2026-10-07',
                'odometer' => $bus->current_mileage + 100, 'litres' => 50, 'price_per_litre' => 300,
            ]),
            MaintenanceRecord::create([
                'depot_id' => $bus->depot_id, 'bus_id' => $bus->id, 'type' => MaintenanceType::Routine,
                'category' => MaintenanceCategory::GeneralService, 'title' => 'Oil change',
                'status' => MaintenanceStatus::Scheduled, 'scheduled_for' => '2026-10-10',
            ]),
        ];
    }
}
