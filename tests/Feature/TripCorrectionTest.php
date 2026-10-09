<?php

namespace Tests\Feature;

use App\Enums\AdjustmentType;
use App\Enums\TripStatus;
use App\Models\Trip;
use App\Services\Trips\TripGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TripCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private function completedTrip(): Trip
    {
        $this->schedule(['departure_time' => '09:00', 'arrival_time' => '10:00']);
        app(TripGenerator::class)->generateFor(today());
        $trip = Trip::firstOrFail();

        $staff = $this->staff();
        $this->actingAs($staff)->post("/trips/{$trip->id}/depart", ['time' => '09:00', 'odometer' => 120000]);
        $this->actingAs($staff)->post("/trips/{$trip->id}/arrive", ['time' => '10:05', 'odometer' => 120019, 'passengers' => 61]);

        return $trip->fresh();
    }

    /** Form data that leaves the trip unchanged. */
    private function form(Trip $trip, array $overrides = []): array
    {
        return [
            'bus_id' => $trip->bus_id,
            'driver_id' => $trip->driver_id,
            'status' => $trip->status->value,
            'actual_departure' => $trip->actual_departure?->format('Y-m-d\TH:i'),
            'actual_arrival' => $trip->actual_arrival?->format('Y-m-d\TH:i'),
            'delay_minutes' => $trip->delay_minutes,
            'odometer_start' => $trip->odometer_start,
            'odometer_end' => $trip->odometer_end,
            'passenger_count' => $trip->passenger_count,
            ...$overrides,
        ];
    }

    public function test_administrator_can_correct_a_completed_trip(): void
    {
        $trip = $this->completedTrip();
        $spare = $this->bus();
        $admin = $this->admin();

        $this->actingAs($admin)->get("/trips/{$trip->id}/edit")->assertOk();

        $this->actingAs($admin)->put("/trips/{$trip->id}", $this->form($trip, [
            'bus_id' => $spare->id,
            'actual_arrival' => '2026-10-08T10:35',
            'passenger_count' => 58,
            'note' => 'Arrival keyed in late',
        ]))->assertRedirect("/trips/{$trip->id}");

        $trip->refresh();
        $this->assertSame(TripStatus::Completed, $trip->status);
        $this->assertSame($spare->id, $trip->bus_id);
        $this->assertSame('10:35', $trip->actual_arrival->format('H:i'));
        $this->assertSame(58, $trip->passenger_count);

        $log = $trip->adjustments()->first();
        $this->assertSame(AdjustmentType::Correction, $log->type);
        $this->assertSame('Corrected bus, actual arrival, passenger count', $log->details);
        $this->assertSame('Arrival keyed in late', $log->note);
        $this->assertSame($admin->id, $log->user_id);
    }

    public function test_correction_without_changes_is_not_logged(): void
    {
        $trip = $this->completedTrip();
        $before = $trip->adjustments()->count();

        $this->actingAs($this->admin())->put("/trips/{$trip->id}", $this->form($trip))->assertSessionHasNoErrors();

        $this->assertSame($before, $trip->adjustments()->count());
    }

    public function test_completed_trip_needs_departure_and_arrival_times(): void
    {
        $trip = $this->completedTrip();

        $this->actingAs($this->admin())->put("/trips/{$trip->id}", $this->form($trip, ['actual_arrival' => '']))
            ->assertSessionHasErrors('actual_arrival');

        $this->actingAs($this->admin())->put("/trips/{$trip->id}", $this->form($trip, ['actual_arrival' => '2026-10-08T08:30']))
            ->assertSessionHasErrors('actual_arrival');

        $this->actingAs($this->admin())->put("/trips/{$trip->id}", $this->form($trip, ['odometer_end' => 119000]))
            ->assertSessionHasErrors('odometer_end');
    }

    public function test_only_administrators_can_correct_trips(): void
    {
        $trip = $this->completedTrip();

        $this->actingAs($this->supervisor())->put("/trips/{$trip->id}", $this->form($trip, ['passenger_count' => 1]))->assertForbidden();
        $this->actingAs($this->staff())->put("/trips/{$trip->id}", $this->form($trip, ['passenger_count' => 1]))->assertForbidden();

        $this->assertSame(61, $trip->fresh()->passenger_count);
    }

    public function test_administrator_can_edit_and_delete_activity_entries(): void
    {
        $trip = $this->completedTrip();
        [$arrival, $departure] = $trip->adjustments()->get();
        $admin = $this->admin();

        $this->actingAs($admin)->put("/trips/{$trip->id}/activity/{$departure->id}", [
            'details' => 'Departed at 09:00 from bay 4',
            'reason' => 'traffic',
            'note' => 'Checked with timekeeper',
        ])->assertSessionHas('success');

        $departure->refresh();
        $this->assertSame('Departed at 09:00 from bay 4', $departure->details);
        $this->assertSame('traffic', $departure->reason->value);
        $this->assertSame(AdjustmentType::Departure, $departure->type);

        $this->actingAs($admin)->delete("/trips/{$trip->id}/activity/{$arrival->id}")->assertSessionHas('success');
        $this->assertModelMissing($arrival);
    }

    public function test_activity_entry_must_belong_to_the_trip(): void
    {
        $trip = $this->completedTrip();
        $entry = $trip->adjustments()->first();

        $other = Trip::create([
            'depot_id' => $trip->depot_id, 'bus_route_id' => $trip->bus_route_id, 'bus_id' => $trip->bus_id, 'driver_id' => $trip->driver_id,
            'trip_date' => '2026-10-08', 'scheduled_departure' => '2026-10-08 15:00', 'scheduled_arrival' => '2026-10-08 16:00', 'status' => TripStatus::Scheduled,
        ]);

        $this->actingAs($this->admin())->delete("/trips/{$other->id}/activity/{$entry->id}")->assertNotFound();
        $this->assertModelExists($entry);
    }

    public function test_only_administrators_can_change_activity_entries(): void
    {
        $trip = $this->completedTrip();
        $entry = $trip->adjustments()->first();

        $this->actingAs($this->supervisor())->delete("/trips/{$trip->id}/activity/{$entry->id}")->assertForbidden();
        $this->actingAs($this->staff())->put("/trips/{$trip->id}/activity/{$entry->id}", ['details' => 'x'])->assertForbidden();

        $this->assertModelExists($entry);
    }
}
