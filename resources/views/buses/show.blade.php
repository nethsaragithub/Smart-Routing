@php $km = $bus->kmToNextService(); @endphp
<x-layouts.app :title="'Bus '.$bus->registration_no">
    <x-page-header :title="$bus->registration_no" :subtitle="$bus->make.' '.$bus->model.($bus->year_of_manufacture ? ', '.$bus->year_of_manufacture : '').($bus->fleet_no ? ' · Fleet no. '.$bus->fleet_no : '')" :back="route('buses.index')">
        <x-slot:meta>
            <div class="mt-3 flex flex-wrap gap-2">
                <x-badge :value="$bus->status" />
                <x-badge :value="$bus->service_type" />
            </div>
        </x-slot:meta>
        <x-slot:actions>
            @can('log-fuel-maintenance')
                <a href="{{ route('fuel.create', ['bus' => $bus->id]) }}" class="btn btn-secondary"><x-icon name="fuel" size="16" /> Log fuel</a>
                <a href="{{ route('maintenance.create', ['bus' => $bus->id]) }}" class="btn btn-secondary"><x-icon name="wrench" size="16" /> Log maintenance</a>
            @endcan
            @can('manage-fleet')
                <a href="{{ route('buses.edit', $bus) }}" class="btn btn-primary"><x-icon name="edit" size="16" /> Edit</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <section class="panel mb-6 grid grid-cols-2 gap-px overflow-hidden bg-line lg:grid-cols-5">
        <x-stat label="Odometer" :value="number_format($bus->current_mileage)" hint="km" />
        <x-stat label="Seating" :value="$bus->seating_capacity" hint="passengers" />
        <x-stat label="Next service" :value="$km < 0 ? 'Overdue' : number_format($km)" :hint="$km < 0 ? number_format(-$km).' km over' : 'km to go'" />
        <x-stat label="Fuel economy" :value="$kmPerLitre ? number_format($kmPerLitre, 2) : '–'" :hint="'km/L · depot average '.($fleetAverage ? number_format($fleetAverage, 2) : '–')" />
        <x-stat label="Trips, last 30 days" :value="$tripsLastMonth" :hint="number_format($kmLastMonth).' km driven'" />
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="panel overflow-hidden">
            <div class="panel-head"><h2 class="panel-title">Assigned timetables</h2></div>
            @if ($schedules->isEmpty())
                <x-empty icon="calendar" title="Not on any timetable" />
            @else
                <ul class="divide-y divide-line">
                    @foreach ($schedules as $schedule)
                        <li><a href="{{ route('schedules.show', $schedule) }}" class="flex items-center gap-3 px-5 py-3 hover:bg-paper/50">
                            <x-route-no :no="$schedule->route->route_no" size="sm" />
                            <span class="font-semibold tabular-nums">{{ $schedule->departureLabel() }}</span>
                            <span class="min-w-0 flex-1 truncate text-sm text-muted">{{ $schedule->rule()->describe() }} · {{ $schedule->driver->shortName() }}</span>
                        </a></li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="panel overflow-hidden">
            <div class="panel-head"><h2 class="panel-title">Upcoming trips</h2></div>
            @if ($upcoming->isEmpty())
                <x-empty icon="clock" title="No upcoming trips" />
            @else
                <ul class="divide-y divide-line">
                    @foreach ($upcoming as $trip)
                        <li><a href="{{ route('trips.show', $trip) }}" class="flex items-center gap-3 px-5 py-3 hover:bg-paper/50">
                            <span class="w-24 text-sm text-muted">{{ $trip->trip_date->isToday() ? 'Today' : $trip->trip_date->format('D j M') }}</span>
                            <span class="font-semibold tabular-nums">{{ $trip->scheduled_departure->format('H:i') }}</span>
                            <x-route-no :no="$trip->route->route_no" size="sm" />
                            <span class="flex-1 truncate text-sm">{{ $trip->route->destination }}</span>
                            <x-badge :value="$trip->status" />
                        </a></li>
                    @endforeach
                </ul>
            @endif
        </section>

        <section class="panel overflow-hidden">
            <div class="panel-head">
                <h2 class="panel-title">Maintenance history</h2>
                <a href="{{ route('maintenance.index', ['bus' => $bus->id]) }}" class="text-sm font-medium text-signal hover:underline">All jobs</a>
            </div>
            @if ($bus->maintenanceRecords->isEmpty())
                <x-empty icon="wrench" title="No maintenance recorded" />
            @else
                <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Date</th><th>Work</th><th>Status</th><th class="num">Cost</th></tr></thead>
                    <tbody>
                    @foreach ($bus->maintenanceRecords as $job)
                        <tr>
                            <td class="whitespace-nowrap">{{ $job->scheduled_for->format('j M Y') }}</td>
                            <td>{{ $job->title }} <span class="block text-[12.5px] text-muted">{{ $job->type->label() }} · {{ $job->category->label() }}</span></td>
                            <td><x-badge :value="$job->status" /></td>
                            <td class="num whitespace-nowrap">{{ $job->cost ? 'Rs '.number_format($job->cost) : '–' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                </div>
            @endif
        </section>

        <section class="panel overflow-hidden">
            <div class="panel-head">
                <h2 class="panel-title">Recent fuel</h2>
                <a href="{{ route('fuel.index', ['bus' => $bus->id]) }}" class="text-sm font-medium text-signal hover:underline">Fuel log</a>
            </div>
            @if ($bus->fuelLogs->isEmpty())
                <x-empty icon="fuel" title="No fuel recorded" />
            @else
                <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Date</th><th class="num">Odometer</th><th class="num">Litres</th><th class="num">Cost</th></tr></thead>
                    <tbody>
                    @foreach ($bus->fuelLogs as $log)
                        <tr>
                            <td>{{ $log->filled_on->format('j M Y') }}</td>
                            <td class="num">{{ number_format($log->odometer) }}</td>
                            <td class="num">{{ number_format($log->litres, 1) }}</td>
                            <td class="num">Rs {{ number_format($log->total_cost) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                </div>
            @endif
        </section>
    </div>

    @can('manage-fleet')
        <div class="mt-6">
            <x-delete-button :action="route('buses.destroy', $bus)" label="Remove bus" confirm="Remove this bus from the fleet? Its history is kept for reports." />
        </div>
    @endcan
</x-layouts.app>
