<?php

namespace Tests\Feature;

use App\Enums\TripStatus;
use App\Models\Trip;
use App\Services\Trips\TripGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TripOperationsTest extends TestCase
{
    use RefreshDatabase;

    private function todaysTrip(array $scheduleAttributes = []): Trip
    {
        $schedule = $this->schedule(['departure_time' => '09:00', 'arrival_time' => '10:00', ...$scheduleAttributes]);
        app(TripGenerator::class)->generateFor(today());

        return Trip::where('schedule_id', $schedule->id)->firstOrFail();
    }

    public function test_trip_generation_is_idempotent(): void
    {
        $this->schedule();
        $generator = app(TripGenerator::class);

        $this->assertSame(7, $generator->generateBetween(Carbon::parse('2026-10-08'), Carbon::parse('2026-10-14')));
        $this->assertSame(0, $generator->generateBetween(Carbon::parse('2026-10-08'), Carbon::parse('2026-10-14')));
        $this->assertSame(7, Trip::count());
    }

    public function test_weekday_timetable_creates_no_weekend_trips(): void
    {
        $this->schedule(['recurrence' => 'weekly', 'weekdays' => [1, 2, 3, 4, 5]]);

        // Thu 8 Oct to Sun 11 Oct: only Thursday and Friday.
        app(TripGenerator::class)->generateBetween(Carbon::parse('2026-10-08'), Carbon::parse('2026-10-11'));

        $this->assertSame(['2026-10-08', '2026-10-09'], Trip::orderBy('trip_date')->get()->map(fn ($t) => $t->trip_date->toDateString())->all());
    }

    public function test_on_time_departure_is_recorded(): void
    {
        $trip = $this->todaysTrip();

        $this->actingAs($this->staff())->post("/trips/{$trip->id}/depart", ['time' => '09:03', 'odometer' => 120500]);

        $trip->refresh();
        $this->assertSame(TripStatus::OnTime, $trip->status);
        $this->assertSame(3, $trip->delay_minutes);
        $this->assertSame(1, $trip->adjustments()->count());
    }

    public function test_late_departure_is_marked_delayed(): void
    {
        $trip = $this->todaysTrip();

        $this->actingAs($this->staff())->post("/trips/{$trip->id}/depart", ['time' => '09:20']);

        $this->assertSame(TripStatus::Delayed, $trip->fresh()->status);
        $this->assertSame(20, $trip->fresh()->delay_minutes);
    }

    public function test_arrival_completes_the_trip_and_updates_bus_mileage(): void
    {
        $trip = $this->todaysTrip();
        $staff = $this->staff();

        $this->actingAs($staff)->post("/trips/{$trip->id}/depart", ['time' => '09:00', 'odometer' => 120000]);
        $this->actingAs($staff)->post("/trips/{$trip->id}/arrive", ['time' => '10:05', 'odometer' => 120019, 'passengers' => 61]);

        $trip->refresh();
        $this->assertSame(TripStatus::Completed, $trip->status);
        $this->assertSame(19, $trip->distanceDriven());
        $this->assertSame(120019, $trip->bus->fresh()->current_mileage);
    }

    public function test_arrival_cannot_be_recorded_before_departure(): void
    {
        $trip = $this->todaysTrip();

        $this->actingAs($this->staff())->from("/trips/{$trip->id}")
            ->post("/trips/{$trip->id}/arrive", ['time' => '10:00'])
            ->assertSessionHas('error', 'Record the departure before recording the arrival.');
    }

    public function test_departure_cannot_be_recorded_for_a_future_day(): void
    {
        $this->schedule();
        app(TripGenerator::class)->generateFor(Carbon::parse('2026-10-09'));
        $trip = Trip::first();

        $this->actingAs($this->staff())->post("/trips/{$trip->id}/depart")
            ->assertSessionHas('error', 'A departure can only be recorded on the day of the trip.');
    }

    public function test_cancelled_trip_cannot_be_changed_again(): void
    {
        $trip = $this->todaysTrip();
        $staff = $this->staff();

        $this->actingAs($staff)->post("/trips/{$trip->id}/cancel", ['reason' => 'breakdown']);
        $this->assertSame(TripStatus::Cancelled, $trip->fresh()->status);

        $this->actingAs($staff)->post("/trips/{$trip->id}/depart")->assertSessionHas('error');
    }

    public function test_bus_can_be_swapped_for_a_free_bus(): void
    {
        $trip = $this->todaysTrip();
        $spare = $this->bus();

        $this->actingAs($this->staff())->post("/trips/{$trip->id}/reassign", ['bus_id' => $spare->id, 'reason' => 'breakdown'])
            ->assertSessionHas('success');

        $this->assertSame($spare->id, $trip->fresh()->bus_id);
        $this->assertSame('bus_change', $trip->adjustments()->first()->type->value);
    }

    public function test_bus_already_on_another_trip_cannot_be_swapped_in(): void
    {
        $trip = $this->todaysTrip();
        $busyTrip = $this->todaysTrip(['departure_time' => '09:30', 'arrival_time' => '10:30']);

        $this->actingAs($this->staff())->post("/trips/{$trip->id}/reassign", ['bus_id' => $busyTrip->bus_id, 'reason' => 'breakdown'])
            ->assertSessionHas('error');

        $this->assertNotSame($busyTrip->bus_id, $trip->fresh()->bus_id);
    }

    public function test_delay_report_is_logged_with_reason(): void
    {
        $trip = $this->todaysTrip();

        $this->actingAs($this->staff())->post("/trips/{$trip->id}/delay", ['minutes' => 25, 'reason' => 'traffic', 'note' => 'Accident at Nugegoda junction']);

        $adjustment = $trip->adjustments()->first();
        $this->assertSame(TripStatus::Delayed, $trip->fresh()->status);
        $this->assertSame('traffic', $adjustment->reason->value);
        $this->assertSame('Accident at Nugegoda junction', $adjustment->note);
    }
}
