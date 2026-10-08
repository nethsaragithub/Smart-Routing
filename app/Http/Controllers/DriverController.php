<?php

namespace App\Http\Controllers;

use App\Enums\DriverStatus;
use App\Enums\LicenseStatus as Licence;
use App\Http\Requests\DriverRequest;
use App\Models\Driver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DriverController extends Controller
{
    public function index(Request $request): View
    {
        $licence = $request->query('licence');

        $drivers = Driver::query()
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('full_name', 'like', "%{$term}%")
                ->orWhere('employee_no', 'like', "%{$term}%")
                ->orWhere('license_no', 'like', "%{$term}%")
                ->orWhere('nic', 'like', "%{$term}%")))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($licence === Licence::Expired->value, fn ($q) => $q->whereDate('license_expiry', '<', today()))
            ->when($licence === Licence::ExpiringSoon->value, fn ($q) => $q->whereDate('license_expiry', '>=', today())
                ->whereDate('license_expiry', '<=', today()->addDays(Licence::WARNING_DAYS)))
            ->orderBy('full_name')
            ->paginate(15)
            ->withQueryString();

        return view('drivers.index', [
            'drivers' => $drivers,
            'statuses' => DriverStatus::options(),
            'licenceFilters' => [Licence::ExpiringSoon->value => 'Expiring soon', Licence::Expired->value => 'Expired'],
        ]);
    }

    public function show(Driver $driver): View
    {
        $weekStart = today()->startOfWeek();

        return view('drivers.show', [
            'driver' => $driver,
            'hoursThisWeek' => $driver->hoursBetween($weekStart, $weekStart->copy()->endOfWeek()),
            'hoursThisMonth' => $driver->hoursBetween(today()->startOfMonth(), today()->endOfMonth()),
            'schedules' => $driver->schedules()->active()->with(['route', 'bus'])->orderBy('departure_time')->get(),
            'routes' => $driver->schedules()->active()->with('route')->get()->pluck('route')->unique('id'),
            'recentTrips' => $driver->trips()->with(['route', 'bus'])->latest('scheduled_departure')->limit(10)->get(),
        ]);
    }

    public function create(): View
    {
        return view('drivers.form', [
            'driver' => new Driver(['status' => DriverStatus::Active, 'license_class' => 'D', 'max_weekly_hours' => 60]),
            'statuses' => DriverStatus::options(),
        ]);
    }

    public function store(DriverRequest $request): RedirectResponse
    {
        $driver = Driver::create($request->validated());

        return redirect()->route('drivers.show', $driver)->with('success', "{$driver->full_name} added.");
    }

    public function edit(Driver $driver): View
    {
        return view('drivers.form', ['driver' => $driver, 'statuses' => DriverStatus::options()]);
    }

    public function update(DriverRequest $request, Driver $driver): RedirectResponse
    {
        $driver->update($request->validated());

        return redirect()->route('drivers.show', $driver)->with('success', 'Driver details saved.');
    }

    public function destroy(Driver $driver): RedirectResponse
    {
        if ($driver->schedules()->active()->exists()) {
            return back()->with('error', "{$driver->full_name} is on active timetables. Reassign them first.");
        }

        $driver->delete();

        return redirect()->route('drivers.index')->with('success', "{$driver->full_name} removed. Their trip history is kept.");
    }
}
