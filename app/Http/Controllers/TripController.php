<?php

namespace App\Http\Controllers;

use App\Enums\AdjustmentReason;
use App\Enums\TripStatus;
use App\Models\Bus;
use App\Models\BusRoute;
use App\Models\Driver;
use App\Models\Trip;
use App\Services\Trips\TripClashChecker;
use App\Services\Trips\TripGenerator;
use App\Services\Trips\TripOperations;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TripController extends Controller
{
    public function __construct(private readonly TripOperations $operations)
    {
    }

    public function index(Request $request): View
    {
        $date = CarbonImmutable::parse($request->query('date', today()->toDateString()));

        $trips = Trip::query()
            ->on($date)
            ->with(['route', 'bus', 'driver'])
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('route'), fn ($q, $id) => $q->where('bus_route_id', $id))
            ->orderBy('scheduled_departure')
            ->get();

        $counts = Trip::query()->on($date)->get(['status'])->countBy(fn ($t) => $t->status->value);

        return view('trips.index', [
            'date' => $date,
            'trips' => $trips,
            'counts' => $counts,
            'statuses' => TripStatus::cases(),
            'routes' => BusRoute::query()->orderByRaw('LENGTH(route_no), route_no')->get(),
            'reasons' => AdjustmentReason::options(),
        ]);
    }

    public function show(Trip $trip, TripClashChecker $clashes): View
    {
        $trip->load(['route.stops', 'bus', 'driver', 'schedule', 'adjustments.user']);
        $busy = $trip->status->isOpen() ? $clashes->busyResources($trip) : ['buses' => [], 'drivers' => []];

        return view('trips.show', [
            'trip' => $trip,
            'reasons' => AdjustmentReason::options(),
            'buses' => Bus::query()->orderBy('registration_no')->get()
                ->map(fn (Bus $b) => ['model' => $b, 'busy' => in_array($b->id, $busy['buses'], true)]),
            'drivers' => Driver::query()->orderBy('full_name')->get()
                ->map(fn (Driver $d) => ['model' => $d, 'busy' => in_array($d->id, $busy['drivers'], true)]),
        ]);
    }

    public function generate(Request $request, TripGenerator $generator): RedirectResponse
    {
        $data = $request->validate([
            'from' => ['required', 'date'],
            'to' => ['required', 'date', 'after_or_equal:from', 'before_or_equal:'.Carbon::parse($request->input('from'))->addDays(31)->toDateString()],
        ], ['to.before_or_equal' => 'Generate at most one month of trips at a time.']);

        $created = $generator->generateBetween(Carbon::parse($data['from']), Carbon::parse($data['to']));

        return back()->with('success', $created > 0
            ? "{$created} trip(s) generated from the timetables."
            : 'Trips for these dates were already generated. Nothing new to add.');
    }

    public function depart(Request $request, Trip $trip): RedirectResponse
    {
        $data = $request->validate([
            'time' => ['nullable', 'date_format:H:i'],
            'odometer' => ['nullable', 'integer', 'min:0'],
        ]);

        $this->operations->depart($trip, $this->timeOn($trip, $data['time'] ?? null), $data['odometer'] ?? null, $request->user());

        return back()->with('success', "Route {$trip->route->route_no} departure recorded.");
    }

    public function arrive(Request $request, Trip $trip): RedirectResponse
    {
        $data = $request->validate([
            'time' => ['nullable', 'date_format:H:i'],
            'odometer' => ['nullable', 'integer', 'min:0'],
            'passengers' => ['nullable', 'integer', 'min:0', 'max:300'],
        ]);

        $this->operations->arrive($trip, $this->timeOn($trip, $data['time'] ?? null), $data['odometer'] ?? null, $data['passengers'] ?? null, $request->user());

        return back()->with('success', "Route {$trip->route->route_no} marked as completed.");
    }

    public function delay(Request $request, Trip $trip): RedirectResponse
    {
        $data = $request->validate([
            'minutes' => ['required', 'integer', 'min:1', 'max:600'],
            'reason' => ['required', Rule::enum(AdjustmentReason::class)],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->operations->reportDelay($trip, $data['minutes'], AdjustmentReason::from($data['reason']), $data['note'] ?? null, $request->user());

        return back()->with('success', "Delay of {$data['minutes']} minutes recorded.");
    }

    public function cancel(Request $request, Trip $trip): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', Rule::enum(AdjustmentReason::class)],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $this->operations->cancel($trip, AdjustmentReason::from($data['reason']), $data['note'] ?? null, $request->user());

        return back()->with('success', 'Trip cancelled.');
    }

    public function reassign(Request $request, Trip $trip): RedirectResponse
    {
        $data = $request->validate([
            'bus_id' => ['nullable', 'integer'],
            'driver_id' => ['nullable', 'integer'],
            'reason' => ['required', Rule::enum(AdjustmentReason::class)],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $bus = ! empty($data['bus_id']) ? Bus::findOrFail($data['bus_id']) : null;
        $driver = ! empty($data['driver_id']) ? Driver::findOrFail($data['driver_id']) : null;

        if (! $bus && ! $driver) {
            return back()->with('error', 'Choose a replacement bus or driver.');
        }

        $this->operations->reassign($trip, $bus, $driver, AdjustmentReason::from($data['reason']), $data['note'] ?? null, $request->user());

        return back()->with('success', 'Trip reassigned.');
    }

    /** Combine an HH:MM entered by the clerk with the trip's date (default: now). */
    private function timeOn(Trip $trip, ?string $time): CarbonImmutable
    {
        return $time
            ? CarbonImmutable::parse($trip->trip_date->toDateString().' '.$time)
            : CarbonImmutable::now();
    }
}
