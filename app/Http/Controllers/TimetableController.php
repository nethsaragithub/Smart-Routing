<?php

namespace App\Http\Controllers;

use App\Models\BusRoute;
use App\Models\Schedule;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Printable weekly timetable: departures per route for each day of a week.
 */
class TimetableController extends Controller
{
    public function index(Request $request): View
    {
        $weekStart = CarbonImmutable::parse($request->query('week', today()->toDateString()))->startOfWeek();
        $days = collect(range(0, 6))->map(fn ($i) => $weekStart->addDays($i));
        $weekEnd = $days->last();

        $schedules = Schedule::query()
            ->active()
            ->with(['bus', 'driver'])
            ->whereDate('start_date', '<=', $weekEnd)
            ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', $weekStart))
            ->when($request->query('route'), fn ($q, $id) => $q->where('bus_route_id', $id))
            ->orderBy('departure_time')
            ->get()
            ->groupBy('bus_route_id');

        $routes = BusRoute::query()
            ->whereIn('id', $schedules->keys())
            ->orderByRaw('LENGTH(route_no), route_no')
            ->get()
            ->map(fn (BusRoute $route) => [
                'route' => $route,
                // For each day, the departures that run on that date.
                'days' => $days->map(fn ($day) => $schedules[$route->id]->filter(fn (Schedule $s) => $s->occursOn($day))->values()),
            ]);

        return view('timetable.index', [
            'weekStart' => $weekStart,
            'days' => $days,
            'rows' => $routes,
            'allRoutes' => BusRoute::query()->orderByRaw('LENGTH(route_no), route_no')->get(),
        ]);
    }
}
