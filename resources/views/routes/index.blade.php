<x-layouts.app title="Routes">
    <x-page-header title="Routes" subtitle="Every route this depot operates, with its stops and timetables.">
        <x-slot:actions>
            @can('manage-routes')
                <a href="{{ route('routes.create') }}" class="btn btn-primary"><x-icon name="plus" size="16" /> New route</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="panel overflow-hidden">
        <x-filter-bar :action="route('routes.index')">
            <x-search-input placeholder="Search number, town or name" />
            <x-filter-select name="service" label="Service" :options="$serviceTypes" />
            <x-filter-select name="status" label="Status" :options="['active' => 'Active', 'inactive' => 'Inactive']" />
        </x-filter-bar>

        @if ($routes->isEmpty())
            <x-empty icon="route" title="No routes found"
                     :text="request()->query() ? 'Try a different search or clear the filters.' : 'Create the first route by placing its stops on the map.'">
                @can('manage-routes')
                    @unless (request()->query())
                        <a href="{{ route('routes.create') }}" class="btn btn-primary"><x-icon name="plus" size="16" /> New route</a>
                    @endunless
                @endcan
            </x-empty>
        @else
            <ul class="divide-y divide-line">
                @foreach ($routes as $route)
                    <li>
                        <a href="{{ route('routes.show', $route) }}" class="flex flex-wrap items-center gap-x-5 gap-y-2 px-5 py-4 hover:bg-paper/50">
                            <x-route-no :no="$route->route_no" />
                            <div class="min-w-0 flex-1">
                                <div class="font-semibold">{{ $route->origin }} – {{ $route->destination }}</div>
                                <div class="text-[13px] text-muted">{{ $route->name }}</div>
                            </div>
                            <dl class="flex flex-wrap gap-x-6 gap-y-1 text-sm">
                                <div><dt class="sr-only">Distance</dt><dd class="tabular-nums">{{ number_format($route->distance_km, 1) }} km</dd></div>
                                <div><dt class="sr-only">Running time</dt><dd class="tabular-nums">{{ $route->durationLabel() }}</dd></div>
                                <div><dt class="sr-only">Stops</dt><dd>{{ $route->stops_count }} stops</dd></div>
                                <div><dt class="sr-only">Daily departures</dt><dd>{{ $route->schedules_count }} {{ Str::plural('timetable', $route->schedules_count) }}</dd></div>
                            </dl>
                            <div class="flex items-center gap-2">
                                <x-badge :value="$route->service_type" />
                                @unless ($route->is_active)
                                    <x-badge tone="slate" label="Inactive" />
                                @endunless
                            </div>
                            <x-icon name="chevron-right" class="text-muted hidden sm:block" />
                        </a>
                    </li>
                @endforeach
            </ul>
            {{ $routes->links() }}
        @endif
    </div>
</x-layouts.app>
