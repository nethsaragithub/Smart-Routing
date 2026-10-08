<?php

namespace App\Http\Controllers;

use App\Enums\BusStatus;
use App\Enums\ServiceType;
use App\Enums\TripStatus;
use App\Http\Requests\BusRequest;
use App\Models\Bus;
use App\Services\Fleet\FuelEfficiencyCalculator;
use App\Support\ReportPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BusController extends Controller
{
    public function index(Request $request): View
    {
        $buses = Bus::query()
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('registration_no', 'like', "%{$term}%")
                ->orWhere('fleet_no', 'like', "%{$term}%")
                ->orWhere('make', 'like', "%{$term}%")
                ->orWhere('model', 'like', "%{$term}%")))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('service'), fn ($q, $s) => $q->where('service_type', $s))
            ->orderBy('registration_no')
            ->paginate(15)
            ->withQueryString();

        $all = Bus::query()->get(['status']);

        return view('buses.index', [
            'buses' => $buses,
            'statuses' => BusStatus::options(),
            'serviceTypes' => ServiceType::options(),
            'counts' => $all->countBy(fn ($b) => $b->status->value),
            'total' => $all->count(),
        ]);
    }

    public function show(Bus $bus, FuelEfficiencyCalculator $fuel): View
    {
        $bus->load([
            'maintenanceRecords' => fn ($q) => $q->latest('scheduled_for')->limit(10),
            'fuelLogs' => fn ($q) => $q->with('driver')->latest('filled_on')->limit(10),
        ]);

        $month = ReportPeriod::lastDays(30);
        $trips = $bus->trips()->whereBetween('trip_date', $month->dateRange())->get();

        return view('buses.show', [
            'bus' => $bus,
            'kmPerLitre' => $fuel->forBus($bus, ReportPeriod::lastDays(90)),
            'fleetAverage' => $fuel->fleetAverage(ReportPeriod::lastDays(90)),
            'tripsLastMonth' => $trips->where('status', TripStatus::Completed)->count(),
            'kmLastMonth' => $trips->sum(fn ($t) => $t->distanceDriven() ?? 0),
            'upcoming' => $bus->trips()->with(['route', 'driver'])->open()
                ->where('scheduled_departure', '>=', now()->subHours(2))
                ->orderBy('scheduled_departure')->limit(8)->get(),
            'schedules' => $bus->schedules()->active()->with(['route', 'driver'])->orderBy('departure_time')->get(),
        ]);
    }

    public function create(): View
    {
        return view('buses.form', [
            'bus' => new Bus(['status' => BusStatus::Active, 'service_type' => ServiceType::Normal, 'fuel_type' => 'diesel', 'service_interval_km' => 10000, 'seating_capacity' => 54]),
            'statuses' => BusStatus::options(),
            'serviceTypes' => ServiceType::options(),
        ]);
    }

    public function store(BusRequest $request): RedirectResponse
    {
        $bus = Bus::create($request->validated());

        return redirect()->route('buses.show', $bus)->with('success', "Bus {$bus->registration_no} added to the fleet.");
    }

    public function edit(Bus $bus): View
    {
        return view('buses.form', [
            'bus' => $bus,
            'statuses' => BusStatus::options(),
            'serviceTypes' => ServiceType::options(),
        ]);
    }

    public function update(BusRequest $request, Bus $bus): RedirectResponse
    {
        $bus->update($request->validated());

        return redirect()->route('buses.show', $bus)->with('success', 'Bus details saved.');
    }

    public function destroy(Bus $bus): RedirectResponse
    {
        if ($bus->schedules()->active()->exists()) {
            return back()->with('error', "{$bus->registration_no} is on active timetables. Reassign them before removing the bus.");
        }

        $bus->delete();

        return redirect()->route('buses.index')->with('success', "Bus {$bus->registration_no} removed from the fleet. Its history is kept.");
    }
}
