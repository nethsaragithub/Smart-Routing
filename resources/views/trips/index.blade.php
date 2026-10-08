<x-layouts.app title="Trips">
    <x-page-header title="Trips" :subtitle="$date->isToday() ? 'Today, '.$date->format('l j F').'. Record departures and arrivals as buses leave and return.' : $date->format('l j F Y')">
        <x-slot:actions>
            <a href="{{ route('trips.index', ['date' => $date->subDay()->toDateString()]) }}" class="btn btn-secondary" aria-label="Previous day"><x-icon name="chevron-left" size="16" /></a>
            <form method="GET" action="{{ route('trips.index') }}">
                <label for="trip-date" class="sr-only">Date</label>
                <input id="trip-date" type="date" name="date" value="{{ $date->toDateString() }}" onchange="this.form.submit()" class="control py-1.5 text-sm">
            </form>
            <a href="{{ route('trips.index', ['date' => $date->addDay()->toDateString()]) }}" class="btn btn-secondary" aria-label="Next day"><x-icon name="chevron-right" size="16" /></a>
            @can('operate-trips')
                <button type="button" class="btn btn-primary" @click="$dispatch('open-modal', 'generate')"><x-icon name="refresh" size="16" /> Generate trips</button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    {{-- Status tabs --}}
    <nav class="mb-4 flex flex-wrap gap-2" aria-label="Filter by status">
        <a href="{{ route('trips.index', ['date' => $date->toDateString()]) }}"
           @class(['rounded-full px-3 py-1 text-sm font-medium border', 'bg-ink text-white border-ink' => ! request('status'), 'bg-white border-line-strong hover:bg-paper' => request('status')])>
            All <span class="tabular-nums opacity-75">{{ $counts->sum() }}</span>
        </a>
        @foreach ($statuses as $status)
            <a href="{{ route('trips.index', ['date' => $date->toDateString(), 'status' => $status->value]) }}"
               @class(['rounded-full px-3 py-1 text-sm font-medium border', 'bg-ink text-white border-ink' => request('status') === $status->value, 'bg-white border-line-strong hover:bg-paper' => request('status') !== $status->value])>
                {{ $status->label() }} <span class="tabular-nums opacity-75">{{ $counts[$status->value] ?? 0 }}</span>
            </a>
        @endforeach
    </nav>

    <div class="panel overflow-hidden">
        @if ($trips->isEmpty())
            <x-empty icon="clock" title="No trips for this day"
                     :text="$counts->sum() ? 'No trips match this filter.' : 'Trips are created from the timetables. Generate them to start recording departures.'">
                @can('operate-trips')
                    @if (! $counts->sum())
                        <form method="POST" action="{{ route('trips.generate') }}">
                            @csrf
                            <input type="hidden" name="from" value="{{ $date->toDateString() }}">
                            <input type="hidden" name="to" value="{{ $date->toDateString() }}">
                            <button class="btn btn-primary"><x-icon name="refresh" size="16" /> Generate trips for {{ $date->format('j M') }}</button>
                        </form>
                    @endif
                @endcan
            </x-empty>
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                    <tr><th>Departs</th><th>Route</th><th>Bus</th><th>Driver</th><th>Status</th><th>Actual</th><th class="text-right">Action</th></tr>
                    </thead>
                    <tbody>
                    @foreach ($trips as $trip)
                        <tr>
                            <td class="whitespace-nowrap">
                                <a href="{{ route('trips.show', $trip) }}" class="font-semibold tabular-nums hover:text-signal">{{ $trip->scheduled_departure->format('H:i') }}</a>
                                <span class="block text-[12.5px] text-muted tabular-nums">arr. {{ $trip->scheduled_arrival->format('H:i') }}</span>
                            </td>
                            <td>
                                <a href="{{ route('trips.show', $trip) }}" class="flex items-center gap-2.5">
                                    <x-route-no :no="$trip->route->route_no" size="sm" />
                                    <span class="whitespace-nowrap">{{ $trip->route->destination }}</span>
                                </a>
                            </td>
                            <td class="whitespace-nowrap tabular-nums">
                                {{ $trip->bus->registration_no }}
                                @unless ($trip->bus->status->isOperational())
                                    <span class="block text-[12.5px] text-signal">{{ $trip->bus->status->label() }}</span>
                                @endunless
                            </td>
                            <td class="whitespace-nowrap">{{ $trip->driver->shortName() }}</td>
                            <td><x-badge :value="$trip->status" :label="$trip->status === \App\Enums\TripStatus::Delayed ? 'Delayed '.$trip->delay_minutes.' min' : null" /></td>
                            <td class="whitespace-nowrap text-[13px] tabular-nums text-muted">
                                {{ $trip->actual_departure?->format('H:i') ?? '–' }} → {{ $trip->actual_arrival?->format('H:i') ?? '–' }}
                            </td>
                            <td class="text-right whitespace-nowrap">
                                @can('operate-trips')
                                    @if ($trip->status->isOpen() && ! $trip->hasDeparted() && ! $trip->trip_date->isAfter(today()))
                                        <form method="POST" action="{{ route('trips.depart', $trip) }}" class="inline">
                                            @csrf
                                            <button class="btn btn-secondary btn-sm"><x-icon name="play" size="14" /> Departed now</button>
                                        </form>
                                    @elseif ($trip->isRunning())
                                        <form method="POST" action="{{ route('trips.arrive', $trip) }}" class="inline">
                                            @csrf
                                            <button class="btn btn-secondary btn-sm"><x-icon name="flag" size="14" /> Arrived now</button>
                                        </form>
                                    @endif
                                @endcan
                                <a href="{{ route('trips.show', $trip) }}" class="btn btn-ghost btn-sm">Details</a>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    @can('operate-trips')
        <x-modal name="generate" title="Generate trips">
            <form method="POST" action="{{ route('trips.generate') }}" class="space-y-4">
                @csrf
                <p class="text-sm text-muted">Creates a trip for every active timetable on each day in the range. Days that already have trips are skipped.</p>
                <div class="grid grid-cols-2 gap-4">
                    <x-form.input name="from" label="From" type="date" :value="$date->toDateString()" required />
                    <x-form.input name="to" label="To" type="date" :value="$date->addDays(6)->toDateString()" required />
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" class="btn btn-secondary" @click="open = false">Cancel</button>
                    <button class="btn btn-primary">Generate trips</button>
                </div>
            </form>
        </x-modal>
    @endcan
</x-layouts.app>
