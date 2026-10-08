<?php

namespace Tests\Feature;

use App\Enums\BusStatus;
use App\Models\Bus;
use App\Models\BusRoute;
use App\Models\Driver;
use App\Models\FuelLog;
use App\Models\MaintenanceRecord;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Route planning, driver & vehicle database, fuel and maintenance logs.
 */
class FleetManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_is_created_with_ordered_stops(): void
    {
        $stops = [
            ['name' => 'Pettah', 'lat' => 6.9366, 'lng' => 79.8500],
            ['name' => 'Borella', 'lat' => 6.9147, 'lng' => 79.8778],
            ['name' => 'Kottawa', 'lat' => 6.8412, 'lng' => 79.9650],
        ];

        $this->actingAs($this->supervisor())->post('/routes', [
            'route_no' => '138', 'name' => 'Pettah – Kottawa', 'origin' => 'Pettah', 'destination' => 'Kottawa',
            'distance_km' => 18.4, 'estimated_duration_minutes' => 65, 'service_type' => 'normal',
            'is_active' => 1, 'stops' => json_encode($stops),
        ])->assertSessionHasNoErrors();

        $route = BusRoute::with('stops')->first();
        $this->assertSame(['Pettah', 'Borella', 'Kottawa'], $route->stops->pluck('name')->all());
        $this->assertEquals(18.4, $route->stops->last()->distance_from_start_km);
        $this->assertSame(65, $route->stops->last()->minutes_from_start);
    }

    public function test_route_needs_at_least_two_stops(): void
    {
        $this->actingAs($this->supervisor())->post('/routes', [
            'route_no' => '138', 'name' => 'Test', 'origin' => 'A', 'destination' => 'B',
            'distance_km' => 10, 'estimated_duration_minutes' => 30, 'service_type' => 'normal',
            'stops' => json_encode([['name' => 'A', 'lat' => 6.9, 'lng' => 79.8]]),
        ])->assertSessionHasErrors('stops');
    }

    public function test_route_number_must_be_unique_within_the_depot(): void
    {
        $this->route(['route_no' => '138']);

        $this->actingAs($this->supervisor())->post('/routes', [
            'route_no' => '138', 'name' => 'Dup', 'origin' => 'A', 'destination' => 'B', 'distance_km' => 10,
            'estimated_duration_minutes' => 30, 'service_type' => 'normal',
            'stops' => json_encode([['name' => 'A', 'lat' => 6.9, 'lng' => 79.8], ['name' => 'B', 'lat' => 6.8, 'lng' => 79.9]]),
        ])->assertSessionHasErrors('route_no');
    }

    public function test_bus_registration_follows_sri_lankan_format(): void
    {
        $data = [
            'make' => 'Ashok Leyland', 'model' => 'Viking', 'seating_capacity' => 54, 'service_type' => 'normal',
            'fuel_type' => 'diesel', 'current_mileage' => 1000, 'service_interval_km' => 10000, 'status' => 'active',
        ];

        $this->actingAs($this->supervisor())->post('/buses', [...$data, 'registration_no' => 'ABC123'])
            ->assertSessionHasErrors('registration_no');

        $this->actingAs($this->supervisor())->post('/buses', [...$data, 'registration_no' => 'wp nb-4521'])
            ->assertSessionHasNoErrors();

        $this->assertSame('WP NB-4521', Bus::first()->registration_no);
    }

    public function test_driver_nic_and_phone_are_validated(): void
    {
        $data = [
            'employee_no' => 'MHR-D900', 'full_name' => 'K. A. Test Driver', 'license_no' => 'B1234567',
            'license_class' => 'D', 'license_expiry' => '2028-01-01', 'status' => 'active', 'max_weekly_hours' => 60,
        ];

        $this->actingAs($this->supervisor())->post('/drivers', [...$data, 'nic' => '12345', 'phone' => '12345'])
            ->assertSessionHasErrors(['nic', 'phone']);

        $this->actingAs($this->supervisor())->post('/drivers', [...$data, 'nic' => '851234567v', 'phone' => '0771234567'])
            ->assertSessionHasNoErrors();

        $this->assertSame('851234567V', Driver::first()->nic);
    }

    public function test_fuel_entry_calculates_cost_and_updates_odometer(): void
    {
        $bus = $this->bus(['current_mileage' => 120000]);

        $this->actingAs($this->staff())->post('/fuel', [
            'bus_id' => $bus->id, 'filled_on' => '2026-10-08', 'odometer' => 120250,
            'litres' => 70, 'price_per_litre' => 283, 'full_tank' => 1,
        ])->assertSessionHasNoErrors();

        $this->assertEquals(19810.00, FuelLog::first()->total_cost);
        $this->assertSame(120250, $bus->fresh()->current_mileage);
    }

    public function test_fuel_odometer_must_increase(): void
    {
        $bus = $this->bus();
        $staff = $this->staff();
        $entry = ['bus_id' => $bus->id, 'filled_on' => '2026-10-08', 'litres' => 70, 'price_per_litre' => 283];

        $this->actingAs($staff)->post('/fuel', [...$entry, 'odometer' => 120500])->assertSessionHasNoErrors();
        $this->actingAs($staff)->post('/fuel', [...$entry, 'odometer' => 120400])->assertSessionHasErrors('odometer');
    }

    public function test_maintenance_takes_bus_off_road_and_returns_it_when_completed(): void
    {
        $bus = $this->bus(['current_mileage' => 130000, 'last_service_mileage' => 120000]);
        $staff = $this->staff();

        $this->actingAs($staff)->post('/maintenance', [
            'bus_id' => $bus->id, 'type' => 'routine', 'category' => 'general_service',
            'title' => '10,000 km service', 'scheduled_for' => '2026-10-08', 'start_now' => 1,
        ]);
        $record = MaintenanceRecord::first();

        $this->assertSame(BusStatus::Maintenance, $bus->fresh()->status);

        $this->actingAs($staff)->post("/maintenance/{$record->id}/complete", [
            'completed_on' => '2026-10-08', 'cost' => 52000, 'odometer' => 130010,
        ]);

        $bus->refresh();
        $this->assertSame(BusStatus::Active, $bus->status);
        $this->assertSame(130010, $bus->last_service_mileage);
        $this->assertFalse($bus->isServiceDue());
    }
}
