<?php

namespace App\Http\Controllers;

use App\Http\Requests\FuelLogRequest;
use App\Models\Bus;
use App\Models\BusRoute;
use App\Models\Driver;
use App\Models\FuelLog;
use App\Services\Fleet\FuelEfficiencyCalculator;
use App\Support\ReportPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FuelLogController extends Controller
{
    public function index(Request $request, FuelEfficiencyCalculator $calculator): View
    {
        $period = ReportPeriod::fromInput($request->query() + ['period' => 'monthly']);

        $logs = FuelLog::query()
            ->with(['bus', 'driver', 'route'])
            ->whereBetween('filled_on', $period->dateRange())
            ->when($request->query('bus'), fn ($q, $id) => $q->where('bus_id', $id))
            ->latest('filled_on')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $totals = FuelLog::query()->whereBetween('filled_on', $period->dateRange())
            ->selectRaw('COALESCE(SUM(litres),0) as litres, COALESCE(SUM(total_cost),0) as cost, COUNT(*) as fills')
            ->first();

        return view('fuel.index', [
            'logs' => $logs,
            'period' => $period,
            'totals' => $totals,
            'fleetAverage' => $calculator->fleetAverage($period),
            'byBus' => $calculator->byBus($period)->take(5),
            'buses' => Bus::query()->orderBy('registration_no')->get(),
        ]);
    }

    public function create(Request $request): View
    {
        return $this->form(new FuelLog([
            'filled_on' => today(),
            'full_tank' => true,
            'price_per_litre' => config('srmss.default_fuel_price'),
            'bus_id' => $request->integer('bus') ?: null,
        ]));
    }

    public function store(FuelLogRequest $request): RedirectResponse
    {
        $log = FuelLog::create([...$request->logData(), 'recorded_by' => $request->user()->id]);
        $log->bus->recordMileage($log->odometer);

        return redirect()->route('fuel.index')->with('success', sprintf('Fill-up of %.1f L for %s recorded.', $log->litres, $log->bus->registration_no));
    }

    public function edit(FuelLog $fuelLog): View
    {
        return $this->form($fuelLog);
    }

    public function update(FuelLogRequest $request, FuelLog $fuelLog): RedirectResponse
    {
        $fuelLog->update($request->logData());

        return redirect()->route('fuel.index')->with('success', 'Fuel entry saved.');
    }

    public function destroy(FuelLog $fuelLog): RedirectResponse
    {
        $fuelLog->delete();

        return redirect()->route('fuel.index')->with('success', 'Fuel entry deleted.');
    }

    private function form(FuelLog $log): View
    {
        return view('fuel.form', [
            'log' => $log,
            'buses' => Bus::query()->orderBy('registration_no')->get(),
            'drivers' => Driver::query()->active()->orderBy('full_name')->get(),
            'routes' => BusRoute::query()->orderByRaw('LENGTH(route_no), route_no')->get(),
        ]);
    }
}
