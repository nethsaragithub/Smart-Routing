@php use App\Enums\MaintenanceStatus; @endphp
<x-layouts.app title="Maintenance">
    <x-page-header title="Maintenance" subtitle="Routine services and repairs. Starting a job takes the bus off the road until it is completed.">
        <x-slot:actions>
            @can('view-reports')
                <a href="{{ route('reports.show', 'maintenance') }}" class="btn btn-secondary"><x-icon name="chart" size="16" /> Summary report</a>
            @endcan
            @can('log-fuel-maintenance')
                <a href="{{ route('maintenance.create') }}" class="btn btn-primary"><x-icon name="plus" size="16" /> New job</a>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <section class="panel mb-6 grid grid-cols-2 gap-px overflow-hidden bg-line lg:grid-cols-3">
        <x-stat label="In the workshop now" :value="$inWorkshop" hint="buses" />
        <x-stat label="Due for service" :value="$dueForService->count()" hint="within 500 km" />
        <x-stat label="Spent this month" :value="'Rs '.number_format($monthCost)" class="max-lg:col-span-2" />
    </section>

    <div class="grid gap-6 xl:grid-cols-[1fr_320px]">
        <div class="panel overflow-hidden">
            <x-filter-bar :action="route('maintenance.index')">
                <x-filter-select name="status" label="Status" :options="$statuses" />
                <x-filter-select name="type" label="Type" :options="$types" />
                <x-filter-select name="bus" label="Bus" :options="$buses->pluck('registration_no', 'id')" />
            </x-filter-bar>

            @if ($records->isEmpty())
                <x-empty icon="wrench" title="No maintenance jobs found" />
            @else
                <ul class="divide-y divide-line">
                    @foreach ($records as $job)
                        <li class="flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-4" x-data>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <a href="{{ route('buses.show', $job->bus) }}" class="font-semibold tabular-nums hover:text-signal">{{ $job->bus->registration_no }}</a>
                                    <span class="font-medium">{{ $job->title }}</span>
                                </div>
                                <div class="mt-0.5 text-[13px] text-muted">
                                    {{ $job->type->label() }} · {{ $job->category->label() }} ·
                                    @if ($job->status === MaintenanceStatus::Completed)
                                        completed {{ $job->completed_on?->format('j M Y') }}
                                    @elseif ($job->status === MaintenanceStatus::InProgress)
                                        in workshop since {{ $job->started_on?->format('j M') }} ({{ $job->downtimeDays() }} {{ Str::plural('day', $job->downtimeDays()) }})
                                    @else
                                        planned for {{ $job->scheduled_for->format('j M Y') }}
                                    @endif
                                    {{ $job->workshop ? '· '.$job->workshop : '' }}
                                </div>
                            </div>
                            <span class="text-sm tabular-nums">{{ $job->cost ? 'Rs '.number_format($job->cost) : '' }}</span>
                            <x-badge :value="$job->status" />
                            @can('log-fuel-maintenance')
                                <div class="flex gap-1.5">
                                    @if ($job->status === MaintenanceStatus::Scheduled)
                                        <form method="POST" action="{{ route('maintenance.start', $job) }}">
                                            @csrf
                                            <button class="btn btn-secondary btn-sm"><x-icon name="play" size="14" /> Start</button>
                                        </form>
                                    @endif
                                    @if ($job->status !== MaintenanceStatus::Completed)
                                        <button type="button" class="btn btn-secondary btn-sm" @click="$dispatch('open-modal', 'complete-{{ $job->id }}')"><x-icon name="check" size="14" /> Complete</button>
                                    @endif
                                    <a href="{{ route('maintenance.edit', $job) }}" class="btn btn-ghost btn-sm" aria-label="Edit job"><x-icon name="edit" size="15" /></a>
                                </div>

                                @if ($job->status !== MaintenanceStatus::Completed)
                                    <x-modal :name="'complete-'.$job->id" :title="'Complete: '.$job->title">
                                        <form method="POST" action="{{ route('maintenance.complete', $job) }}" class="space-y-4">
                                            @csrf
                                            <p class="text-sm text-muted">{{ $job->bus->registration_no }} returns to service when the job is completed.</p>
                                            <div class="grid grid-cols-2 gap-4">
                                                <x-form.input name="completed_on" :id="'completed_on_'.$job->id" label="Completed on" type="date" :value="today()->toDateString()" required />
                                                <x-form.input name="cost" :id="'cost_'.$job->id" label="Final cost" type="number" step="0.01" min="0" :value="$job->cost" suffix="Rs" />
                                                <x-form.input name="odometer" :id="'odometer_'.$job->id" label="Odometer" type="number" min="0" :value="$job->odometer ?? $job->bus->current_mileage" suffix="km" class="col-span-2" />
                                            </div>
                                            <div class="flex justify-end gap-2">
                                                <button type="button" class="btn btn-secondary" @click="open = false">Cancel</button>
                                                <button class="btn btn-primary">Mark completed</button>
                                            </div>
                                        </form>
                                    </x-modal>
                                @endif
                            @endcan
                        </li>
                    @endforeach
                </ul>
                {{ $records->links() }}
            @endif
        </div>

        <section class="panel self-start">
            <div class="panel-head"><h2 class="panel-title">Due for service</h2></div>
            @if ($dueForService->isEmpty())
                <x-empty icon="check-circle" title="No services due" />
            @else
                <ul class="divide-y divide-line">
                    @foreach ($dueForService as $bus)
                        @php $km = $bus->kmToNextService(); @endphp
                        <li class="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                            <div>
                                <a href="{{ route('buses.show', $bus) }}" class="font-medium tabular-nums hover:text-signal">{{ $bus->registration_no }}</a>
                                <span @class(['block text-[13px]', 'text-signal' => $km < 0, 'text-muted' => $km >= 0])>{{ $km < 0 ? number_format(-$km).' km overdue' : number_format($km).' km to go' }}</span>
                            </div>
                            @can('log-fuel-maintenance')
                                <a href="{{ route('maintenance.create', ['bus' => $bus->id]) }}" class="btn btn-secondary btn-sm">Book</a>
                            @endcan
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>
</x-layouts.app>
