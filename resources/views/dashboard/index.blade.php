<x-layouts.app title="Dashboard">
    <x-page-header title="Depot dashboard" :subtitle="'Operations for '.$today->format('l j F').' at '.($currentDepot?->name ?? 'your depot').'.'">
        <x-slot:actions>
            @can('operate-trips')
                <a href="{{ route('trips.index') }}" class="btn btn-secondary"><x-icon name="clock" size="16" /> Today's trips</a>
            @endcan
            @can('manage-schedules')
                <a href="{{ route('schedules.create') }}" class="btn btn-primary"><x-icon name="plus" size="16" /> New timetable</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- Key figures --}}
    <section class="panel mb-6 grid grid-cols-2 gap-px overflow-hidden bg-line md:grid-cols-3 xl:grid-cols-6" aria-label="Key figures">
        <x-stat label="Active routes" :value="$stats['routes_active']" :hint="'of '.$stats['routes_total'].' routes'" :href="route('routes.index')" />
        <x-stat label="Buses available now" :value="$stats['buses_available']" :hint="$stats['buses_on_road'].' on the road · '.$stats['buses_in_maintenance'].' in workshop'" :href="route('buses.index')" />
        <x-stat label="Drivers on duty today" :value="$stats['drivers_on_duty']" :hint="'of '.$stats['drivers_active'].' active drivers'" :href="route('drivers.index')" />
        <x-stat label="Trips today" :value="$stats['trips_total']" :hint="$stats['trips_by_status']['completed'].' completed so far'" :href="route('trips.index')" />
        <x-stat label="On-time departures" :value="$stats['on_time_rate'] !== null ? $stats['on_time_rate'].'%' : '–'" hint="Within 5 minutes of schedule" />
        <x-stat label="Fleet utilisation" :value="$stats['utilisation_rate'] !== null ? $stats['utilisation_rate'].'%' : '–'" hint="Active buses used today" />
    </section>

    <div class="grid gap-6 xl:grid-cols-[1fr_360px]">
        {{-- Live trip board --}}
        <section class="panel overflow-hidden" x-data="liveBoard(@js(route('dashboard.board')))" aria-labelledby="board-title">
            <div class="panel-head">
                <div>
                    <h2 id="board-title" class="panel-title">Trip board</h2>
                    <p class="text-[13px] text-muted">Refreshes every minute · last update <span x-text="updatedLabel">{{ now()->format('H:i') }}</span></p>
                </div>
                <button type="button" class="btn btn-ghost btn-sm" @click="refresh()"><x-icon name="refresh" size="16" /> Refresh</button>
            </div>
            <div x-ref="board">
                @include('dashboard.partials.board')
            </div>
        </section>

        <div class="space-y-6">
            {{-- Needs attention --}}
            <section class="panel" aria-labelledby="alerts-title">
                <div class="panel-head">
                    <h2 id="alerts-title" class="panel-title">Needs attention</h2>
                    @if (count($alerts))
                        <span class="rounded-full bg-signal-tint px-2 py-0.5 text-[12.5px] font-semibold text-signal">{{ count($alerts) }}</span>
                    @endif
                </div>
                @forelse ($alerts as $alert)
                    <a href="{{ $alert['url'] }}" class="flex gap-3 border-b border-line px-5 py-3 last:border-b-0 hover:bg-paper/60">
                        <span @class(['mt-1 h-2 w-2 shrink-0 rounded-full', 'bg-red-500' => $alert['tone'] === 'red', 'bg-amber-500' => $alert['tone'] === 'amber'])></span>
                        <span class="min-w-0">
                            <span class="block text-sm font-medium">{{ $alert['title'] }}</span>
                            <span class="block text-[13px] text-muted">{{ $alert['detail'] }}</span>
                        </span>
                    </a>
                @empty
                    <x-empty icon="check-circle" title="All clear" text="No expiring licences, overdue services or trips without a bus." />
                @endforelse
            </section>

            {{-- Week trend --}}
            <section class="panel" aria-labelledby="trend-title">
                <div class="panel-head">
                    <h2 id="trend-title" class="panel-title">Last 7 days</h2>
                    @can('view-reports')
                        <a href="{{ route('reports.show', 'trip-completion') }}" class="text-sm font-medium text-signal hover:underline">Full report</a>
                    @endcan
                </div>
                <div class="p-4">
                    <x-chart label="Completed and cancelled trips over the last 7 days" :height="220" :definition="[
                        'type' => 'bar', 'stacked' => true, 'unit' => 'trips', 'labels' => $trend['labels'],
                        'datasets' => [
                            ['label' => 'On time', 'data' => $trend['on_time'], 'color' => \App\Services\Reports\ChartPalette::ON_TIME],
                            ['label' => 'Late', 'data' => $trend['late'], 'color' => \App\Services\Reports\ChartPalette::LATE],
                            ['label' => 'Cancelled', 'data' => $trend['cancelled'], 'color' => \App\Services\Reports\ChartPalette::CANCELLED],
                        ],
                    ]" />
                </div>
            </section>
        </div>
    </div>
</x-layouts.app>
