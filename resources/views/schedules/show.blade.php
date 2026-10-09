<x-layouts.app :title="'Timetable '.$schedule->route->route_no.' '.$schedule->departureLabel()">
    <x-page-header :title="$schedule->departureLabel().' to '.$schedule->route->destination"
                   :subtitle="$schedule->rule()->describe().' · '.$schedule->departureLabel().'–'.$schedule->arrivalLabel()"
                   :back="auth()->user()->can('view-depot-records') ? route('schedules.index') : url()->previous()">
        <x-slot:meta>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <a href="{{ route('routes.show', $schedule->route) }}"><x-route-no :no="$schedule->route->route_no" /></a>
                <x-badge :value="$schedule->status" />
                <x-badge :value="$schedule->recurrence" />
            </div>
        </x-slot:meta>
        <x-slot:actions>
            @can('manage-schedules')
                <form method="POST" action="{{ route('schedules.status', $schedule) }}">
                    @csrf @method('PATCH')
                    @if ($schedule->status === \App\Enums\ScheduleStatus::Active)
                        <button class="btn btn-secondary"><x-icon name="pause" size="16" /> Suspend</button>
                    @else
                        <button class="btn btn-secondary"><x-icon name="play" size="16" /> Resume</button>
                    @endif
                </form>
                <a href="{{ route('schedules.edit', $schedule) }}" class="btn btn-primary"><x-icon name="edit" size="16" /> Edit</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if (! $report->isClear() && $schedule->status === \App\Enums\ScheduleStatus::Active)
        <div class="mb-6 rounded-lg border border-amber-300 dark:border-amber-400/30 bg-amber-50 dark:bg-amber-400/10 p-4 text-sm text-amber-900 dark:text-amber-200">
            <p class="flex items-center gap-2 font-semibold"><x-icon name="alert" /> This timetable needs attention</p>
            <ul class="mt-1.5 list-disc space-y-0.5 pl-9">
                @foreach ($report->all() as $conflict)
                    <li>{{ $conflict->message }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="panel lg:col-span-2">
            <div class="panel-head"><h2 class="panel-title">Details</h2></div>
            <dl class="grid gap-x-8 gap-y-4 p-5 sm:grid-cols-2">
                <div><dt class="text-sm text-muted">Route</dt><dd class="font-medium">{{ $schedule->route->route_no }} · {{ $schedule->route->origin }} – {{ $schedule->route->destination }}</dd></div>
                <div><dt class="text-sm text-muted">Journey time</dt><dd class="font-medium">{{ intdiv($schedule->durationMinutes(), 60) }}h {{ str_pad($schedule->durationMinutes() % 60, 2, '0', STR_PAD_LEFT) }}m</dd></div>
                <div><dt class="text-sm text-muted">Bus</dt><dd><a href="{{ route('buses.show', $schedule->bus) }}" class="font-medium text-signal hover:underline">{{ $schedule->bus->registration_no }}</a> <span class="text-muted">· {{ $schedule->bus->seating_capacity }} seats · {{ $schedule->bus->service_type->label() }}</span></dd></div>
                <div><dt class="text-sm text-muted">Driver</dt><dd><a href="{{ route('drivers.show', $schedule->driver) }}" class="font-medium text-signal hover:underline">{{ $schedule->driver->full_name }}</a> <span class="text-muted">· {{ $schedule->driver->phone }}</span></dd></div>
                <div><dt class="text-sm text-muted">Valid</dt><dd class="font-medium">{{ $schedule->start_date->format('j M Y') }} {{ $schedule->end_date ? 'to '.$schedule->end_date->format('j M Y') : 'until further notice' }}</dd></div>
                <div><dt class="text-sm text-muted">Created by</dt><dd class="font-medium">{{ $schedule->creator?->name ?? '–' }} <span class="text-muted">· {{ $schedule->created_at->format('j M Y') }}</span></dd></div>
                @if ($schedule->notes)
                    <div class="sm:col-span-2"><dt class="text-sm text-muted">Notes</dt><dd class="whitespace-pre-line">{{ $schedule->notes }}</dd></div>
                @endif
            </dl>
        </section>

        <section class="panel">
            <div class="panel-head"><h2 class="panel-title">Next two weeks</h2></div>
            @if ($schedule->status !== \App\Enums\ScheduleStatus::Active)
                <x-empty icon="pause" title="Suspended" text="This timetable creates no trips until it is resumed." />
            @elseif (empty($upcoming))
                <x-empty icon="calendar" title="No runs in the next two weeks" />
            @else
                <ul class="grid grid-cols-2 gap-px bg-line">
                    @foreach ($upcoming as $date)
                        <li class="bg-panel px-4 py-2 text-sm {{ $date->isToday() ? 'font-semibold' : '' }}">{{ $date->format('D j M') }}{{ $date->isToday() ? ' (today)' : '' }}</li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    <section class="panel mt-6 overflow-hidden">
        <div class="panel-head"><h2 class="panel-title">Recent trips</h2></div>
        @if ($recentTrips->isEmpty())
            <x-empty icon="clock" title="No trips yet" text="Trips appear here once they are generated for a date." />
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Date</th><th>Bus</th><th>Driver</th><th>Departed</th><th>Arrived</th><th>Status</th></tr></thead>
                    <tbody>
                    @foreach ($recentTrips as $trip)
                        <tr>
                            <td><a href="{{ route('trips.show', $trip) }}" class="font-medium text-signal hover:underline">{{ $trip->trip_date->format('D j M') }}</a></td>
                            <td class="tabular-nums">{{ $trip->bus->registration_no }}</td>
                            <td>{{ $trip->driver->shortName() }}</td>
                            <td class="tabular-nums">{{ $trip->actual_departure?->format('H:i') ?? '–' }}</td>
                            <td class="tabular-nums">{{ $trip->actual_arrival?->format('H:i') ?? '–' }}</td>
                            <td><x-badge :value="$trip->status" /></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    @can('manage-schedules')
        <div class="mt-6">
            <x-delete-button :action="route('schedules.destroy', $schedule)" label="Delete timetable"
                             confirm="Delete this timetable? Upcoming trips that have not started are removed; completed trips are kept." />
        </div>
    @endcan
</x-layouts.app>
