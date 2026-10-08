<x-layouts.app title="Schedules">
    <x-page-header title="Schedules" subtitle="Recurring timetables. Each one creates a trip on every day it runs.">
        <x-slot:actions>
            <a href="{{ route('timetable') }}" class="btn btn-secondary"><x-icon name="calendar" size="16" /> Weekly view</a>
            @can('manage-schedules')
                <a href="{{ route('schedules.create') }}" class="btn btn-primary"><x-icon name="plus" size="16" /> New timetable</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="panel overflow-hidden">
        <x-filter-bar :action="route('schedules.index')">
            <x-search-input placeholder="Search bus or driver" />
            <x-filter-select name="route" label="Route" :options="$routes->mapWithKeys(fn ($r) => [$r->id => $r->route_no.' '.$r->destination])" />
            <x-filter-select name="recurrence" label="Runs" :options="$recurrences" />
            <x-filter-select name="status" label="Status" :options="$statuses" />
        </x-filter-bar>

        @if ($schedules->isEmpty())
            <x-empty icon="calendar" title="No timetables found" text="Timetables assign a bus and a driver to a route at a set time.">
                @can('manage-schedules')
                    <a href="{{ route('schedules.create') }}" class="btn btn-primary"><x-icon name="plus" size="16" /> New timetable</a>
                @endcan
            </x-empty>
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                    <tr>
                        <th>Route</th><th>Departs</th><th>Arrives</th><th>Runs</th><th>Bus</th><th>Driver</th><th>Valid</th><th>Status</th>
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($schedules as $schedule)
                        <tr>
                            <td>
                                <a href="{{ route('schedules.show', $schedule) }}" class="flex items-center gap-2.5">
                                    <x-route-no :no="$schedule->route->route_no" size="sm" />
                                    <span class="whitespace-nowrap">{{ $schedule->route->destination }}</span>
                                </a>
                            </td>
                            <td><a href="{{ route('schedules.show', $schedule) }}" class="font-semibold tabular-nums hover:text-signal">{{ $schedule->departureLabel() }}</a></td>
                            <td class="tabular-nums">{{ $schedule->arrivalLabel() }}</td>
                            <td>{{ $schedule->rule()->describe() }}</td>
                            <td class="tabular-nums whitespace-nowrap">{{ $schedule->bus->registration_no }}</td>
                            <td class="whitespace-nowrap">{{ $schedule->driver->shortName() }}</td>
                            <td class="whitespace-nowrap text-[13px] text-muted">
                                {{ $schedule->start_date->format('j M Y') }}{{ $schedule->end_date ? ' to '.$schedule->end_date->format('j M Y') : ' onwards' }}
                            </td>
                            <td><x-badge :value="$schedule->status" /></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $schedules->links() }}
        @endif
    </div>
</x-layouts.app>
