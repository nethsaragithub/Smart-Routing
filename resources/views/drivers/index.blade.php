<x-layouts.app title="Drivers">
    <x-page-header title="Drivers" subtitle="Driver records, licence validity and assigned routes.">
        <x-slot:actions>
            @can('manage-fleet')
                <a href="{{ route('drivers.create') }}" class="btn btn-primary"><x-icon name="plus" size="16" /> Add driver</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="panel overflow-hidden">
        <x-filter-bar :action="route('drivers.index')">
            <x-search-input placeholder="Search name, employee no., NIC or licence" />
            <x-filter-select name="status" label="Status" :options="$statuses" />
            <x-filter-select name="licence" label="Licence" :options="$licenceFilters" />
        </x-filter-bar>

        @if ($drivers->isEmpty())
            <x-empty icon="user" title="No drivers found" :text="request()->query() ? 'Try a different search.' : 'Add drivers so they can be rostered on timetables.'" />
        @else
            <div class="overflow-x-auto">
                <table class="data-table">
                    <thead><tr><th>Driver</th><th>Employee no.</th><th>Phone</th><th>Licence</th><th>Expires</th><th>Status</th></tr></thead>
                    <tbody>
                    @foreach ($drivers as $driver)
                        <tr>
                            <td><a href="{{ route('drivers.show', $driver) }}" class="font-semibold text-signal hover:underline">{{ $driver->full_name }}</a></td>
                            <td class="tabular-nums">{{ $driver->employee_no }}</td>
                            <td class="tabular-nums whitespace-nowrap">{{ $driver->phone }}</td>
                            <td class="whitespace-nowrap">{{ $driver->license_no }} <span class="text-muted">· {{ $driver->license_class }}</span></td>
                            <td class="whitespace-nowrap">
                                <x-badge :value="$driver->licenseStatus()" :label="$driver->license_expiry->format('j M Y')" />
                            </td>
                            <td><x-badge :value="$driver->status" /></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            {{ $drivers->links() }}
        @endif
    </div>
</x-layouts.app>
