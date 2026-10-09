<?php

namespace App\Http\Controllers;

use App\Enums\Recurrence;
use App\Enums\ScheduleStatus;
use App\Http\Requests\ScheduleRequest;
use App\Models\Bus;
use App\Models\BusRoute;
use App\Models\Driver;
use App\Models\Schedule;
use App\Services\Scheduling\AssignmentAdvisor;
use App\Services\Scheduling\ScheduleManager;
use App\Services\Scheduling\ScheduleProposal;
use App\Services\Scheduling\ScheduleConflictDetector;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;

class ScheduleController extends Controller
{
    public function __construct(private readonly ScheduleManager $manager)
    {
    }

    public function index(Request $request): View
    {
        $schedules = Schedule::query()
            ->with(['route', 'bus', 'driver'])
            ->when($request->query('route'), fn ($q, $id) => $q->where('bus_route_id', $id))
            ->when($request->query('recurrence'), fn ($q, $r) => $q->where('recurrence', $r))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($w) => $w
                ->whereHas('bus', fn ($b) => $b->where('registration_no', 'like', "%{$term}%"))
                ->orWhereHas('driver', fn ($d) => $d->where('full_name', 'like', "%{$term}%"))))
            ->join('bus_routes', 'bus_routes.id', '=', 'schedules.bus_route_id')
            ->orderByRaw('LENGTH(bus_routes.route_no), bus_routes.route_no')
            ->orderBy('departure_time')
            ->select('schedules.*')
            ->paginate(20)
            ->withQueryString();

        return view('schedules.index', [
            'schedules' => $schedules,
            'routes' => BusRoute::query()->orderBy('route_no')->get(),
            'recurrences' => Recurrence::options(),
            'statuses' => ScheduleStatus::options(),
        ]);
    }

    public function show(Schedule $schedule, ScheduleConflictDetector $detector): View
    {
        $schedule->load(['route.stops', 'bus', 'driver', 'creator']);

        return view('schedules.show', [
            'schedule' => $schedule,
            'upcoming' => $schedule->rule()->occurrencesBetween(today(), today()->addDays(13)),
            'recentTrips' => $schedule->trips()->with(['bus', 'driver'])->latest('trip_date')->limit(10)->get(),
            'report' => $detector->detect(ScheduleProposal::fromSchedule($schedule)),
        ]);
    }

    public function create(Request $request): View
    {
        return $this->form(new Schedule([
            'bus_route_id' => $request->integer('route') ?: null,
            'recurrence' => Recurrence::Daily,
            'start_date' => today(),
            'weekdays' => [1, 2, 3, 4, 5],
        ]));
    }

    public function store(ScheduleRequest $request): RedirectResponse
    {
        $data = $request->scheduleData();

        if ($blocked = $this->blockedByConflicts($request, $data)) {
            return $blocked;
        }

        $schedule = $this->manager->create($data, $request->user());

        return redirect()->route('schedules.show', $schedule)->with('success', 'Timetable saved and trips for the coming week generated.');
    }

    public function edit(Schedule $schedule): View
    {
        return $this->form($schedule);
    }

    public function update(ScheduleRequest $request, Schedule $schedule): RedirectResponse
    {
        $data = $request->scheduleData();

        if ($blocked = $this->blockedByConflicts($request, $data, $schedule)) {
            return $blocked;
        }

        $this->manager->update($schedule, $data);

        return redirect()->route('schedules.show', $schedule)->with('success', 'Timetable updated. Upcoming trips were refreshed.');
    }

    public function destroy(Schedule $schedule): RedirectResponse
    {
        $this->manager->delete($schedule);

        return redirect()->route('schedules.index')->with('success', 'Timetable deleted. Completed trips stay in the history.');
    }

    /** Suspend or resume a timetable (e.g. during road works or a festival). */
    public function toggleStatus(Schedule $schedule): RedirectResponse
    {
        if ($schedule->status === ScheduleStatus::Active) {
            $this->manager->suspend($schedule);

            return back()->with('success', 'Timetable suspended. Its upcoming trips were removed from the board.');
        }

        $removed = collect(['route' => $schedule->route, 'bus' => $schedule->bus, 'driver' => $schedule->driver])
            ->filter(fn ($model) => $model->trashed())->keys();

        if ($removed->isNotEmpty()) {
            return back()->with('error', 'This timetable cannot resume: its '.$removed->join(', ', ' and ').' has been removed. Edit it to choose a replacement first.');
        }

        $report = $this->manager->check($this->asArray($schedule), $schedule);

        if ($report->hasErrors()) {
            return back()->with('error', 'This timetable cannot resume: '.$report->errors()->first()->message);
        }

        $this->manager->resume($schedule);

        return back()->with('success', 'Timetable resumed and upcoming trips generated.');
    }

    /** Live conflict check used by the schedule form (JSON). */
    public function check(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bus_route_id' => ['required', 'integer'],
            'bus_id' => ['required', 'integer'],
            'driver_id' => ['required', 'integer'],
            'departure_time' => ['required', 'date_format:H:i'],
            'arrival_time' => ['required', 'date_format:H:i'],
            'recurrence' => ['required', 'string'],
            'start_date' => ['required', 'date'],
        ]);

        if ($validator->fails()) {
            return response()->json(['incomplete' => true]);
        }

        $existing = $request->integer('schedule_id') ? Schedule::find($request->integer('schedule_id')) : null;

        return response()->json($this->manager->check($request->all(), $existing));
    }

    /** Ranked bus and driver options for the chosen route and times (JSON). */
    public function options(Request $request, AssignmentAdvisor $advisor): JsonResponse
    {
        $route = BusRoute::findOrFail($request->integer('route'));
        $departure = $request->query('departure');
        $arrival = $request->query('arrival');
        $ignore = $request->integer('schedule_id') ?: null;

        return response()->json([
            'route' => [
                'duration' => $route->estimated_duration_minutes,
                'service_type' => $route->service_type->label(),
                'min_capacity' => $route->min_capacity,
            ],
            'buses' => $advisor->busesFor($route, $departure, $arrival, $ignore),
            'drivers' => $advisor->driversFor($departure, $arrival, $ignore),
        ]);
    }

    /**
     * Errors always block saving. Warnings block until the user ticks
     * "save anyway" on the form.
     */
    private function blockedByConflicts(ScheduleRequest $request, array $data, ?Schedule $existing = null): ?RedirectResponse
    {
        $report = $this->manager->check($data, $existing);

        if ($report->hasErrors() || ($report->hasWarnings() && ! $request->boolean('acknowledge_warnings'))) {
            return back()->withInput()->with('conflicts', $report->jsonSerialize());
        }

        return null;
    }

    private function asArray(Schedule $schedule): array
    {
        return [
            'bus_route_id' => $schedule->bus_route_id,
            'bus_id' => $schedule->bus_id,
            'driver_id' => $schedule->driver_id,
            'departure_time' => $schedule->departureLabel(),
            'arrival_time' => $schedule->arrivalLabel(),
            'recurrence' => $schedule->recurrence->value,
            'weekdays' => $schedule->weekdays,
            'month_days' => $schedule->month_days,
            'start_date' => $schedule->start_date->toDateString(),
            'end_date' => $schedule->end_date?->toDateString(),
        ];
    }

    private function form(Schedule $schedule): View
    {
        return view('schedules.form', [
            'schedule' => $schedule,
            'routes' => BusRoute::query()->active()->orderByRaw('LENGTH(route_no), route_no')->get(),
            'buses' => Bus::query()->orderBy('registration_no')->get(),
            'drivers' => Driver::query()->orderBy('full_name')->get(),
            'recurrences' => Recurrence::cases(),
        ]);
    }
}
