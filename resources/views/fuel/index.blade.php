<x-layouts.app title="Fuel log">
    <x-page-header title="Fuel log" :subtitle="'Fill-ups for '.$period->label().'.'">
        <x-slot:actions>
            <a href="{{ route('fuel.index', $period->previous()->toQuery() + request()->only('bus')) }}" class="btn btn-secondary" aria-label="Previous month"><x-icon name="chevron-left" size="16" /></a>
            <a href="{{ route('fuel.index', $period->next()->toQuery() + request()->only('bus')) }}" class="btn btn-secondary" aria-label="Next month"><x-icon name="chevron-right" size="16" /></a>
            @can('view-reports')
                <a href="{{ route('reports.show', ['report' => 'fuel-consumption'] + $period->toQuery()) }}" class="btn btn-secondary"><x-icon name="chart" size="16" /> Fuel report</a>
            @endcan
            @can('log-fuel-maintenance')
                <a href="{{ route('fuel.create') }}" class="btn btn-primary"><x-icon name="plus" size="16" /> Log fill-up</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <section class="panel mb-6 grid grid-cols-2 gap-px overflow-hidden bg-line lg:grid-cols-4">
        <x-stat label="Fuel used" :value="number_format($totals->litres, 0)" hint="litres" />
        <x-stat label="Fuel cost" :value="'Rs '.number_format($totals->cost / 1000, 0).'k'" :hint="'Rs '.number_format($totals->cost, 2)" />
        <x-stat label="Fill-ups" :value="$totals->fills" />
        <x-stat label="Depot average" :value="$fleetAverage ? number_format($fleetAverage, 2) : '–'" hint="km per litre" />
    </section>

    <div class="grid gap-6 xl:grid-cols-[1fr_340px]">
        <div class="panel overflow-hidden">
            <x-filter-bar :action="route('fuel.index')">
                @foreach ($period->toQuery() as $key => $value)
                    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                @endforeach
                <x-filter-select name="bus" label="Bus" :options="$buses->pluck('registration_no', 'id')" />
            </x-filter-bar>

            @if ($logs->isEmpty())
                <x-empty icon="fuel" title="No fill-ups recorded for this period">
                    @can('log-fuel-maintenance')
                        <a href="{{ route('fuel.create') }}" class="btn btn-primary"><x-icon name="plus" size="16" /> Log fill-up</a>
                    @endcan
                </x-empty>
            @else
                <div class="overflow-x-auto">
                    <table class="data-table">
                        <thead><tr><th>Date</th><th>Bus</th><th>Driver</th><th>Route</th><th class="num">Odometer</th><th class="num">Litres</th><th class="num">Cost</th><th></th></tr></thead>
                        <tbody>
                        @foreach ($logs as $log)
                            <tr>
                                <td class="whitespace-nowrap">{{ $log->filled_on->format('D j M') }}</td>
                                <td class="tabular-nums whitespace-nowrap"><a href="{{ route('buses.show', $log->bus) }}" class="text-signal hover:underline">{{ $log->bus->registration_no }}</a></td>
                                <td class="whitespace-nowrap">{{ $log->driver?->shortName() ?? '–' }}</td>
                                <td>@if ($log->route)<x-route-no :no="$log->route->route_no" size="sm" />@else – @endif</td>
                                <td class="num">{{ number_format($log->odometer) }}</td>
                                <td class="num">{{ number_format($log->litres, 1) }}@unless ($log->full_tank)<span class="text-muted" title="Partial fill">*</span>@endunless</td>
                                <td class="num whitespace-nowrap">Rs {{ number_format($log->total_cost, 2) }}</td>
                                <td class="text-right">
                                    @can('log-fuel-maintenance')
                                        <a href="{{ route('fuel.edit', $log) }}" class="btn btn-ghost btn-sm" aria-label="Edit entry"><x-icon name="edit" size="15" /></a>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                {{ $logs->links() }}
            @endif
        </div>

        <section class="panel self-start">
            <div class="panel-head"><h2 class="panel-title">Lowest economy</h2></div>
            <p class="px-5 pt-3 text-[13px] text-muted">Buses using the most fuel per kilometre this period. Check tyres, injectors and idling.</p>
            @if ($byBus->isEmpty())
                <x-empty icon="fuel" title="Not enough data yet" text="Two or more fill-ups per bus are needed." />
            @else
                <ul class="divide-y divide-line">
                    @foreach ($byBus as $row)
                        <li class="flex items-center justify-between px-5 py-3 text-sm">
                            <a href="{{ route('buses.show', $row->bus) }}" class="font-medium tabular-nums hover:text-signal">{{ $row->bus->registration_no }}</a>
                            <span class="tabular-nums">{{ $row->km_per_litre ? number_format($row->km_per_litre, 2).' km/L' : '–' }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</x-layouts.app>
