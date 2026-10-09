<x-layouts.app :title="'Route '.$route->route_no">
    <x-page-header :title="$route->origin.' – '.$route->destination" :subtitle="$route->name" :back="auth()->user()->can('view-depot-records') ? route('routes.index') : url()->previous()">
        <x-slot:meta>
            <div class="mt-3 flex flex-wrap items-center gap-3">
                <x-route-no :no="$route->route_no" size="lg" />
                <x-badge :value="$route->service_type" />
                @if ($route->is_active)
                    <x-badge tone="green" label="Active" />
                @else
                    <x-badge tone="slate" label="Inactive" />
                @endif
            </div>
        </x-slot:meta>
        <x-slot:actions>
            @if (! $route->trashed())
                @can('manage-schedules')
                    <a href="{{ route('schedules.create', ['route' => $route->id]) }}" class="btn btn-secondary"><x-icon name="plus" size="16" /> Add departure</a>
                @endcan
                @can('manage-routes')
                    <a href="{{ route('routes.edit', $route) }}" class="btn btn-primary"><x-icon name="edit" size="16" /> Edit route</a>
                @endcan
            @endif
        </x-slot:actions>
    </x-page-header>

    <x-removed-notice :model="$route" what="This route" />

    <div class="grid gap-6 xl:grid-cols-[1fr_380px]">
        <div class="space-y-6">
            {{-- Map --}}
            <section class="panel overflow-hidden" x-data="routeMap(@js($route->stopsForMap()))" aria-label="Route map">
                <div x-ref="map" class="h-[420px] w-full bg-paper"></div>
                <div class="flex flex-wrap gap-x-6 gap-y-1 border-t border-line px-5 py-3 text-sm text-muted">
                    <span>Planned distance <strong class="text-ink tabular-nums">{{ number_format($route->distance_km, 1) }} km</strong></span>
                    <span>Running time <strong class="text-ink">{{ $route->durationLabel() }}</strong></span>
                    <span x-show="roadInfo" x-cloak>Road distance by map <strong class="text-ink tabular-nums" x-text="roadInfo?.distanceKm + ' km'"></strong></span>
                </div>
            </section>

            {{-- Timetables --}}
            <section class="panel overflow-hidden">
                <div class="panel-head">
                    <h2 class="panel-title">Departures</h2>
                    @can('view-depot-records')
                        <a href="{{ route('timetable', ['route' => $route->id]) }}" class="text-sm font-medium text-signal hover:underline">Weekly timetable</a>
                    @endcan
                </div>
                @if ($route->schedules->isEmpty())
                    <x-empty icon="calendar" title="No departures yet" text="Add a timetable to assign a bus and a driver to this route." />
                @else
                    <div class="overflow-x-auto">
                        <table class="data-table">
                            <thead><tr><th>Departs</th><th>Arrives</th><th>Runs</th><th>Bus</th><th>Driver</th><th>Status</th></tr></thead>
                            <tbody>
                            @foreach ($route->schedules as $schedule)
                                <tr>
                                    <td><a href="{{ route('schedules.show', $schedule) }}" class="font-semibold tabular-nums text-signal hover:underline">{{ $schedule->departureLabel() }}</a></td>
                                    <td class="tabular-nums">{{ $schedule->arrivalLabel() }}</td>
                                    <td>{{ $schedule->rule()->describe() }}</td>
                                    <td class="tabular-nums whitespace-nowrap">{{ $schedule->bus->registration_no }}</td>
                                    <td>{{ $schedule->driver->full_name }}</td>
                                    <td><x-badge :value="$schedule->status" /></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>
        </div>

        <div class="space-y-6">
            <section class="panel">
                <div class="panel-head"><h2 class="panel-title">Last 30 days</h2></div>
                <dl class="grid grid-cols-2 gap-px overflow-hidden rounded-b-[10px] bg-line">
                    <x-stat label="Trips" :value="$performance['trips']" />
                    <x-stat label="Completed on time" :value="$performance['completed'] ? round($performance['on_time'] / $performance['completed'] * 100).'%' : '–'" />
                    <x-stat label="Cancelled" :value="$performance['cancelled']" />
                    <x-stat label="Passengers" :value="number_format($performance['passengers'])" />
                </dl>
            </section>

            <section class="panel">
                <div class="panel-head">
                    <h2 class="panel-title">Stops</h2>
                    <span class="text-sm text-muted">{{ $route->stops->count() }}</span>
                </div>
                <ol class="relative px-5 py-4">
                    @foreach ($route->stops as $stop)
                        <li class="relative flex gap-4 pb-4 last:pb-0">
                            @unless ($loop->last)
                                <span class="absolute left-[11px] top-6 h-full w-0.5 bg-line-strong" aria-hidden="true"></span>
                            @endunless
                            <span @class([
                                'relative z-[1] flex h-6 w-6 shrink-0 items-center justify-center rounded-full border-[3px] text-[11px] font-bold',
                                'border-board bg-board text-board-amber' => $loop->first || $loop->last,
                                'border-board bg-panel' => ! ($loop->first || $loop->last),
                            ])>{{ $stop->sequence }}</span>
                            <div class="flex-1 min-w-0 flex items-baseline justify-between gap-3">
                                <span class="font-medium truncate">{{ $stop->name }}</span>
                                <span class="text-[13px] text-muted tabular-nums whitespace-nowrap">
                                    {{ number_format($stop->distance_from_start_km, 1) }} km · +{{ $stop->minutes_from_start }} min
                                </span>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </section>

            @if ($route->description)
                <section class="panel p-5">
                    <h2 class="panel-title mb-2">Notes</h2>
                    <p class="text-sm text-ink-soft whitespace-pre-line">{{ $route->description }}</p>
                </section>
            @endif

            @if (! $route->trashed() && auth()->user()->can('manage-routes'))
                <x-delete-button :action="route('routes.destroy', $route)" label="Delete route"
                                 confirm="Delete this route? Its history stays in reports, but it can no longer be scheduled." />
            @endif
        </div>
    </div>
</x-layouts.app>
