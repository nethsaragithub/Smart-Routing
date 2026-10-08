@php $licence = $driver->licenseStatus(); @endphp
<x-layouts.app :title="$driver->full_name">
    <x-page-header :title="$driver->full_name" :subtitle="'Employee '.$driver->employee_no.' · '.$driver->phone" :back="route('drivers.index')">
        <x-slot:meta>
            <div class="mt-3 flex flex-wrap gap-2">
                <x-badge :value="$driver->status" />
                <x-badge :value="$licence" :label="'Licence '.mb_strtolower($licence->label())" />
            </div>
        </x-slot:meta>
        <x-slot:actions>
            @can('manage-fleet')
                <a href="{{ route('drivers.edit', $driver) }}" class="btn btn-primary"><x-icon name="edit" size="16" /> Edit</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <section class="panel mb-6 grid grid-cols-2 gap-px overflow-hidden bg-line lg:grid-cols-4">
        <x-stat label="Hours this week" :value="number_format($hoursThisWeek, 1)" :hint="'limit '.$driver->max_weekly_hours.' h'" />
        <x-stat label="Hours this month" :value="number_format($hoursThisMonth, 1)" />
        <x-stat label="Timetables" :value="$schedules->count()" :hint="$routes->count().' '.Str::plural('route', $routes->count())" />
        <x-stat label="Licence expires" :value="$driver->license_expiry->format('j M Y')" :hint="$driver->license_expiry->isPast() ? 'Expired '.$driver->license_expiry->diffForHumans() : $driver->license_expiry->diffForHumans()" />
    </section>

    @if ($hoursThisWeek > $driver->max_weekly_hours)
        <div class="mb-6 flex items-center gap-2 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900">
            <x-icon name="alert" /> {{ $driver->shortName() }} is over the weekly working-hour limit. Consider moving a trip to another driver.
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-[360px_1fr]">
        <section class="panel">
            <div class="panel-head"><h2 class="panel-title">Personal details</h2></div>
            <dl class="space-y-3 p-5 text-sm">
                <div><dt class="text-muted">NIC</dt><dd class="font-medium tabular-nums">{{ $driver->nic }}</dd></div>
                <div><dt class="text-muted">Date of birth</dt><dd class="font-medium">{{ $driver->date_of_birth?->format('j M Y') ?? '–' }}</dd></div>
                <div><dt class="text-muted">Address</dt><dd class="font-medium">{{ $driver->address ?? '–' }}</dd></div>
                <div><dt class="text-muted">Licence</dt><dd class="font-medium">{{ $driver->license_no }} · class {{ $driver->license_class }}</dd></div>
                <div><dt class="text-muted">Joined</dt><dd class="font-medium">{{ $driver->joined_on?->format('j M Y') ?? '–' }}</dd></div>
                <div><dt class="text-muted">Assigned routes</dt>
                    <dd class="mt-1 flex flex-wrap gap-1.5">
                        @forelse ($routes as $route)
                            <a href="{{ route('routes.show', $route) }}"><x-route-no :no="$route->route_no" size="sm" /></a>
                        @empty
                            <span>–</span>
                        @endforelse
                    </dd>
                </div>
                @if ($driver->notes)
                    <div><dt class="text-muted">Notes</dt><dd class="whitespace-pre-line">{{ $driver->notes }}</dd></div>
                @endif
            </dl>
        </section>

        <div class="space-y-6">
            <section class="panel overflow-hidden">
                <div class="panel-head"><h2 class="panel-title">Roster</h2></div>
                @if ($schedules->isEmpty())
                    <x-empty icon="calendar" title="Not on any timetable" />
                @else
                    <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead><tr><th>Route</th><th>Time</th><th>Runs</th><th>Bus</th></tr></thead>
                        <tbody>
                        @foreach ($schedules as $schedule)
                            <tr>
                                <td><a href="{{ route('schedules.show', $schedule) }}" class="flex items-center gap-2"><x-route-no :no="$schedule->route->route_no" size="sm" /> {{ $schedule->route->destination }}</a></td>
                                <td class="tabular-nums whitespace-nowrap">{{ $schedule->departureLabel() }}–{{ $schedule->arrivalLabel() }}</td>
                                <td>{{ $schedule->rule()->describe() }}</td>
                                <td class="tabular-nums">{{ $schedule->bus->registration_no }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    </div>
                @endif
            </section>

            <section class="panel overflow-hidden">
                <div class="panel-head"><h2 class="panel-title">Recent trips</h2></div>
                @if ($recentTrips->isEmpty())
                    <x-empty icon="clock" title="No trips yet" />
                @else
                    <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead><tr><th>Date</th><th>Route</th><th>Bus</th><th class="num">Delay</th><th>Status</th></tr></thead>
                        <tbody>
                        @foreach ($recentTrips as $trip)
                            <tr>
                                <td class="whitespace-nowrap"><a href="{{ route('trips.show', $trip) }}" class="text-signal hover:underline">{{ $trip->scheduled_departure->format('D j M, H:i') }}</a></td>
                                <td><x-route-no :no="$trip->route->route_no" size="sm" /></td>
                                <td class="tabular-nums">{{ $trip->bus->registration_no }}</td>
                                <td class="num">{{ $trip->delay_minutes ? $trip->delay_minutes.' min' : '–' }}</td>
                                <td><x-badge :value="$trip->status" /></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                    </div>
                @endif
            </section>
        </div>
    </div>

    @can('manage-fleet')
        <div class="mt-6">
            <x-delete-button :action="route('drivers.destroy', $driver)" label="Remove driver" confirm="Remove this driver? Their trip history is kept." />
        </div>
    @endcan
</x-layouts.app>
