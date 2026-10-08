<x-layouts.app title="Users">
    <x-page-header title="Users" subtitle="Who can sign in, and what they are allowed to do.">
        <x-slot:actions>
            <a href="{{ route('users.create') }}" class="btn btn-primary"><x-icon name="plus" size="16" /> Add user</a>
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid gap-3 md:grid-cols-3">
        @foreach (\App\Enums\UserRole::cases() as $role)
            <div class="panel p-4 text-sm">
                <x-badge :value="$role" />
                <p class="mt-2 text-muted">
                    @switch($role)
                        @case(\App\Enums\UserRole::Admin) Full access, including users and depots. Can switch between depots. @break
                        @case(\App\Enums\UserRole::Supervisor) Manages routes, timetables, buses, drivers and reports for one depot. @break
                        @default Records trip departures and arrivals, fuel fills and maintenance.
                    @endswitch
                </p>
            </div>
        @endforeach
    </div>

    <div class="panel overflow-hidden">
        <x-filter-bar :action="route('users.index')">
            <x-search-input placeholder="Search name or email" />
            <x-filter-select name="role" label="Role" :options="$roles" />
        </x-filter-bar>
        <div class="overflow-x-auto">
            <table class="data-table">
                <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Depot</th><th>Last sign-in</th><th>Account</th><th></th></tr></thead>
                <tbody>
                @foreach ($users as $user)
                    <tr>
                        <td class="font-medium">{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td><x-badge :value="$user->role" /></td>
                        <td>{{ $user->depot?->name ?? 'All depots' }}</td>
                        <td class="text-muted whitespace-nowrap">{{ $user->last_login_at?->diffForHumans() ?? 'Never' }}</td>
                        <td>@if ($user->is_active)<x-badge tone="green" label="Active" />@else<x-badge tone="slate" label="Deactivated" />@endif</td>
                        <td class="text-right"><a href="{{ route('users.edit', $user) }}" class="btn btn-ghost btn-sm">Edit</a></td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        {{ $users->links() }}
    </div>
</x-layouts.app>
