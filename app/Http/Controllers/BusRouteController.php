<?php

namespace App\Http\Controllers;

use App\Enums\ServiceType;
use App\Enums\TripStatus;
use App\Http\Requests\BusRouteRequest;
use App\Models\BusRoute;
use App\Services\Routes\RoutePlanner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BusRouteController extends Controller
{
    public function __construct(private readonly RoutePlanner $planner)
    {
    }

    public function index(Request $request): View
    {
        $routes = BusRoute::query()
            ->withCount(['stops', 'schedules' => fn ($q) => $q->active()])
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($w) => $w
                ->where('route_no', 'like', "%{$term}%")
                ->orWhere('name', 'like', "%{$term}%")
                ->orWhere('origin', 'like', "%{$term}%")
                ->orWhere('destination', 'like', "%{$term}%")))
            ->when($request->query('service'), fn ($q, $type) => $q->where('service_type', $type))
            ->when($request->query('status') === 'inactive', fn ($q) => $q->where('is_active', false))
            ->when($request->query('status') === 'active', fn ($q) => $q->where('is_active', true))
            ->orderByRaw('LENGTH(route_no), route_no')
            ->paginate(12)
            ->withQueryString();

        return view('routes.index', [
            'routes' => $routes,
            'serviceTypes' => ServiceType::options(),
        ]);
    }

    public function show(BusRoute $route): View
    {
        $route->load(['stops', 'schedules' => fn ($q) => $q->with(['bus', 'driver'])->orderBy('departure_time')]);

        $recent = $route->trips()->where('trip_date', '>=', today()->subDays(30))->get();
        $completed = $recent->where('status', TripStatus::Completed);

        return view('routes.show', [
            'route' => $route,
            'performance' => [
                'trips' => $recent->count(),
                'completed' => $completed->count(),
                'on_time' => $completed->filter->wasOnTime()->count(),
                'cancelled' => $recent->where('status', TripStatus::Cancelled)->count(),
                'passengers' => $completed->sum('passenger_count'),
                'avg_delay' => $completed->avg('delay_minutes'),
            ],
        ]);
    }

    public function create(): View
    {
        return $this->form(new BusRoute(['service_type' => ServiceType::Normal, 'is_active' => true]));
    }

    public function store(BusRouteRequest $request): RedirectResponse
    {
        $route = $this->planner->save(new BusRoute(), $request->routeAttributes(), $request->stops());

        return redirect()->route('routes.show', $route)->with('success', "Route {$route->route_no} created.");
    }

    public function edit(BusRoute $route): View
    {
        return $this->form($route->load('stops'));
    }

    public function update(BusRouteRequest $request, BusRoute $route): RedirectResponse
    {
        $this->planner->save($route, $request->routeAttributes(), $request->stops());

        return redirect()->route('routes.show', $route)->with('success', "Route {$route->route_no} saved.");
    }

    public function destroy(BusRoute $route): RedirectResponse
    {
        if ($route->schedules()->active()->exists()) {
            return back()->with('error', 'This route still has active timetables. Suspend or delete them first, or mark the route inactive.');
        }

        $route->delete();

        return redirect()->route('routes.index')->with('success', "Route {$route->route_no} deleted.");
    }

    /** Stops re-submitted after a validation error (array or JSON string). */
    private function oldStops(): ?array
    {
        $old = old('stops');

        return is_string($old) ? json_decode($old, true) : $old;
    }

    private function form(BusRoute $route): View
    {
        return view('routes.form', [
            'route' => $route,
            'serviceTypes' => ServiceType::options(),
            'stops' => $this->oldStops() ?? $route->stopsForMap(),
        ]);
    }
}
