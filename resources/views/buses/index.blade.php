<x-layouts.app title="Buses">
    <x-page-header title="Buses" :subtitle="$total.' vehicles in this depot.'">
        <x-slot:actions>
            @can('manage-fleet')
                <a href="{{ route('buses.create') }}" class="btn btn-primary"><x-icon name="plus" size="16" /> Add bus</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-4 flex flex-wrap gap-2" aria-label="Fleet status">
        @foreach (\App\Enums\BusStatus::cases() as $status)
            <a href="{{ route('buses.index', ['status' => $status->value]) }}" class="panel flex items-center gap-3 px-4 py-2.5 hover:bg-paper/60">
                <x-badge :value="$status" />
                <span class="font-display text-xl font-semibold tabular-nums">{{ $counts[$status->value] ?? 0 }}</span>
            </a>
        @endforeach
    </div>

    <div class="panel overflow-hidden">
        <x-filter-bar :action="route('buses.index')">
            <x-search-input placeholder="Search registration, fleet no. or make" />
            <x-filter-select name="status" label="Status" :options="$statuses" />
            <x-filter-select name="service" label="Service" :options="$serviceTypes" />
        </x-filter-bar>

        @if ($buses->isEmpty())
            <x-empty icon="bus" title="No buses found" :text="request()->query() ? 'Try a different search.' : 'Add the first bus to start building timetables.'" />
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead>
                    <tr><th>Registration</th><th>Vehicle</th><th>Service</th><th class="num">Seats</th><th class="num">Odometer</th><th>Next service</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    @foreach ($buses as $bus)
                        @php $km = $bus->kmToNextService(); @endphp
                        <tr>
                            <td>
                                <a href="{{ route('buses.show', $bus) }}" class="font-semibold tabular-nums text-signal hover:underline">{{ $bus->registration_no }}</a>
                                @if ($bus->fleet_no)<span class="block text-[12.5px] text-muted">Fleet {{ $bus->fleet_no }}</span>@endif
                            </td>
                            <td class="whitespace-nowrap">{{ $bus->make }} {{ $bus->model }} <span class="text-muted">{{ $bus->year_of_manufacture }}</span></td>
                            <td><x-badge :value="$bus->service_type" /></td>
                            <td class="num">{{ $bus->seating_capacity }}</td>
                            <td class="num">{{ number_format($bus->current_mileage) }} km</td>
                            <td @class(['whitespace-nowrap tabular-nums', 'text-signal font-medium' => $km < 0, 'text-amber-800' => $km >= 0 && $km <= 500])>
                                {{ $km < 0 ? number_format(-$km).' km overdue' : 'in '.number_format($km).' km' }}
                            </td>
                            <td><x-badge :value="$bus->status" /></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $buses->links() }}
        @endif
    </div>
</x-layouts.app>
