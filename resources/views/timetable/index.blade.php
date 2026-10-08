<x-layouts.app title="Weekly timetable">
    <x-page-header title="Weekly timetable" :subtitle="'Departures for the week of '.$weekStart->format('j F Y').'.'">
        <x-slot:actions>
            <a href="{{ route('timetable', array_filter(['week' => $weekStart->subWeek()->toDateString(), 'route' => request('route')])) }}" class="btn btn-secondary"><x-icon name="chevron-left" size="16" /> Previous week</a>
            <a href="{{ route('timetable', array_filter(['route' => request('route')])) }}" class="btn btn-secondary">This week</a>
            <a href="{{ route('timetable', array_filter(['week' => $weekStart->addWeek()->toDateString(), 'route' => request('route')])) }}" class="btn btn-secondary">Next week <x-icon name="chevron-right" size="16" /></a>
            <button type="button" onclick="window.print()" class="btn btn-ghost"><x-icon name="printer" size="16" /> Print</button>
        </x-slot:actions>
    </x-page-header>

    <div class="panel overflow-hidden">
        <x-filter-bar :action="route('timetable')">
            <input type="hidden" name="week" value="{{ $weekStart->toDateString() }}">
            <x-filter-select name="route" label="Route" :options="$allRoutes->mapWithKeys(fn ($r) => [$r->id => $r->route_no.' '.$r->destination])" />
        </x-filter-bar>

        @if ($rows->isEmpty())
            <x-empty icon="calendar" title="No departures this week" text="Active timetables that run this week will appear here." />
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[900px] border-collapse text-sm">
                    <thead>
                    <tr class="bg-paper/60">
                        <th scope="col" class="sticky left-0 z-[1] w-56 border-b border-line bg-paper px-4 py-2.5 text-left text-[13px] font-semibold text-muted">Route</th>
                        @foreach ($days as $day)
                            <th scope="col" @class(['border-b border-l border-line px-3 py-2.5 text-left text-[13px] font-semibold', 'text-signal' => $day->isToday(), 'text-muted' => ! $day->isToday()])>
                                {{ $day->format('D') }} <span class="font-normal">{{ $day->format('j M') }}</span>
                            </th>
                        @endforeach
                    </tr>
                    </thead>
                    <tbody>
                    @foreach ($rows as $row)
                        <tr class="align-top">
                            <th scope="row" class="sticky left-0 z-[1] border-b border-line bg-panel px-4 py-3 text-left font-normal">
                                <a href="{{ route('routes.show', $row['route']) }}" class="flex items-center gap-2.5">
                                    <x-route-no :no="$row['route']->route_no" size="sm" />
                                    <span class="leading-tight">{{ $row['route']->destination }}<span class="block text-[12.5px] text-muted">from {{ $row['route']->origin }}</span></span>
                                </a>
                            </th>
                            @foreach ($row['days'] as $i => $departures)
                                <td @class(['border-b border-l border-line px-2 py-2', 'bg-signal-tint/40' => $days[$i]->isToday()])>
                                    <div class="flex flex-wrap gap-1">
                                        @forelse ($departures as $schedule)
                                            <a href="{{ route('schedules.show', $schedule) }}"
                                               title="{{ $schedule->departureLabel() }}–{{ $schedule->arrivalLabel() }} · {{ $schedule->bus->registration_no }} · {{ $schedule->driver->full_name }}"
                                               class="rounded border border-line bg-white px-1.5 py-0.5 tabular-nums hover:border-ink">{{ $schedule->departureLabel() }}</a>
                                        @empty
                                            <span class="px-1.5 text-muted">–</span>
                                        @endforelse
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <p class="border-t border-line px-4 py-3 text-[13px] text-muted">Hover a time to see the bus and driver. Select it to open the timetable.</p>
        @endif
    </div>
</x-layouts.app>
