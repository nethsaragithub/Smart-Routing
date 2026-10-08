<x-layouts.app title="Depots">
    <x-page-header title="Depots" subtitle="Each depot keeps its own routes, fleet, drivers and timetables.">
        <x-slot:actions>
            <a href="{{ route('depots.create') }}" class="btn btn-primary"><x-icon name="plus" size="16" /> Add depot</a>
        </x-slot:actions>
    </x-page-header>

    <div class="panel overflow-hidden">
        <table class="data-table">
            <thead><tr><th>Code</th><th>Depot</th><th>Location</th><th class="num">Routes</th><th class="num">Buses</th><th class="num">Drivers</th><th class="num">Users</th><th></th></tr></thead>
            <tbody>
            @foreach ($depots as $depot)
                <tr>
                    <td><span class="route-board h-6 px-1.5 text-[15px]">{{ $depot->code }}</span></td>
                    <td class="font-medium">{{ $depot->name }} @if ($depot->id === app(\App\Support\DepotContext::class)->id())<x-badge tone="blue" label="Current" class="ml-1" />@endif</td>
                    <td>{{ $depot->location }}</td>
                    <td class="num">{{ $depot->routes_count }}</td>
                    <td class="num">{{ $depot->buses_count }}</td>
                    <td class="num">{{ $depot->drivers_count }}</td>
                    <td class="num">{{ $depot->users_count }}</td>
                    <td class="text-right"><a href="{{ route('depots.edit', $depot) }}" class="btn btn-ghost btn-sm">Edit</a></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</x-layouts.app>
