@php
    $counts = $stats['trips_by_status'];
@endphp
<div class="flex flex-wrap gap-x-5 gap-y-2 border-b border-line px-5 py-3 text-sm">
    @foreach (\App\Enums\TripStatus::cases() as $status)
        <span class="inline-flex items-center gap-2">
            <x-badge :value="$status" />
            <span class="font-semibold tabular-nums">{{ $counts[$status->value] }}</span>
        </span>
    @endforeach
</div>

@if ($trips->isEmpty())
    <x-empty icon="calendar" title="No trips for today yet"
             text="Trips are created from the timetables. Generate them for today to start tracking departures.">
        @can('assign-trips')
            <form method="POST" action="{{ route('trips.generate') }}">
                @csrf
                <input type="hidden" name="from" value="{{ today()->toDateString() }}">
                <input type="hidden" name="to" value="{{ today()->toDateString() }}">
                <button class="btn btn-primary"><x-icon name="refresh" size="16" /> Generate today's trips</button>
            </form>
        @endcan
    </x-empty>
@else
    <div class="max-h-[560px] overflow-auto">
        <table class="data-table">
            <thead class="sticky top-0 z-[1]">
            <tr>
                <th scope="col">Departs</th>
                <th scope="col">Route</th>
                <th scope="col" class="hidden md:table-cell">Bus</th>
                <th scope="col" class="hidden lg:table-cell">Driver</th>
                <th scope="col">Status</th>
                <th scope="col" class="num">Arrives</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($trips as $trip)
                <tr class="cursor-pointer" onclick="window.location='{{ route('trips.show', $trip) }}'">
                    <td class="whitespace-nowrap">
                        <span class="font-semibold tabular-nums">{{ $trip->scheduled_departure->format('H:i') }}</span>
                        @if ($trip->delay_minutes > 0 && $trip->status->isOpen())
                            <span class="block text-[12.5px] text-amber-700 dark:text-amber-300 tabular-nums">exp. {{ $trip->expectedDeparture()->format('H:i') }}</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('trips.show', $trip) }}" class="flex items-center gap-2.5 min-w-0">
                            <x-route-no :no="$trip->route->route_no" size="sm" />
                            <span class="truncate">{{ $trip->route->destination }}</span>
                        </a>
                    </td>
                    <td class="hidden md:table-cell whitespace-nowrap tabular-nums">{{ $trip->bus->registration_no }}</td>
                    <td class="hidden lg:table-cell">{{ $trip->driver->shortName() }}</td>
                    <td>
                        <x-badge :value="$trip->status" :label="$trip->status === \App\Enums\TripStatus::Delayed ? 'Delayed '.$trip->delay_minutes.' min' : null" />
                    </td>
                    <td class="num whitespace-nowrap">{{ ($trip->actual_arrival ?? $trip->scheduled_arrival)->format('H:i') }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif
