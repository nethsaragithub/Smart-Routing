@php use App\Enums\TripStatus; @endphp
<x-layouts.app :title="'Trip '.$trip->route->route_no.' '.$trip->scheduled_departure->format('H:i')">
    <x-page-header :title="$trip->scheduled_departure->format('H:i').' to '.$trip->route->destination"
                   :subtitle="$trip->trip_date->format('l j F Y').' · '.$trip->route->origin.' – '.$trip->route->destination"
                   :back="route('trips.index', ['date' => $trip->trip_date->toDateString()])">
        <x-slot:meta>
            <div class="mt-3 flex flex-wrap items-center gap-2">
                <x-route-no :no="$trip->route->route_no" />
                <x-badge :value="$trip->status" :label="$trip->status === TripStatus::Delayed ? 'Delayed '.$trip->delay_minutes.' min' : null" />
            </div>
        </x-slot:meta>
    </x-page-header>

    <div class="grid gap-6 xl:grid-cols-[1fr_400px]">
        <div class="space-y-6">
            {{-- Progress --}}
            <section class="panel">
                <div class="panel-head"><h2 class="panel-title">Journey</h2></div>
                <ol class="grid gap-px bg-line sm:grid-cols-3">
                    <li class="bg-panel p-5">
                        <div class="text-sm text-muted">Scheduled</div>
                        <div class="mt-1 font-display text-2xl font-semibold tabular-nums">{{ $trip->scheduled_departure->format('H:i') }} – {{ $trip->scheduled_arrival->format('H:i') }}</div>
                    </li>
                    <li class="bg-panel p-5">
                        <div class="text-sm text-muted">Departed</div>
                        <div class="mt-1 font-display text-2xl font-semibold tabular-nums">{{ $trip->actual_departure?->format('H:i') ?? 'Not yet' }}</div>
                        @if ($trip->delay_minutes)
                            <div class="text-[13px] text-amber-800">{{ $trip->delay_minutes }} min late</div>
                        @endif
                    </li>
                    <li class="bg-panel p-5">
                        <div class="text-sm text-muted">Arrived</div>
                        <div class="mt-1 font-display text-2xl font-semibold tabular-nums">{{ $trip->actual_arrival?->format('H:i') ?? 'Not yet' }}</div>
                        @if ($trip->passenger_count !== null)
                            <div class="text-[13px] text-muted">{{ $trip->passenger_count }} passengers</div>
                        @endif
                    </li>
                </ol>
                <dl class="grid gap-x-8 gap-y-3 border-t border-line p-5 text-sm sm:grid-cols-2">
                    <div><dt class="text-muted">Bus</dt><dd><a href="{{ route('buses.show', $trip->bus) }}" class="font-medium text-signal hover:underline">{{ $trip->bus->registration_no }}</a> · {{ $trip->bus->make }} {{ $trip->bus->model }} <x-badge :value="$trip->bus->status" class="ml-1" /></dd></div>
                    <div><dt class="text-muted">Driver</dt><dd><a href="{{ route('drivers.show', $trip->driver) }}" class="font-medium text-signal hover:underline">{{ $trip->driver->full_name }}</a> · {{ $trip->driver->phone }}</dd></div>
                    <div><dt class="text-muted">Odometer</dt><dd class="tabular-nums">
                        @if ($trip->odometer_start)
                            {{ number_format($trip->odometer_start) }} → {{ $trip->odometer_end ? number_format($trip->odometer_end).' km' : '…' }}
                            @if ($trip->distanceDriven()) <span class="text-muted">({{ $trip->distanceDriven() }} km)</span> @endif
                        @else
                            <span class="text-muted">Recorded at departure</span>
                        @endif
                    </dd></div>
                    <div><dt class="text-muted">Timetable</dt><dd>@if ($trip->schedule)<a href="{{ route('schedules.show', $trip->schedule) }}" class="text-signal hover:underline">{{ $trip->schedule->rule()->describe() }}</a>@else Extra trip @endif</dd></div>
                </dl>
            </section>

            {{-- Map --}}
            <section class="panel overflow-hidden" x-data="routeMap(@js($trip->route->stopsForMap()))">
                <div class="panel-head"><h2 class="panel-title">Route</h2><span class="text-sm text-muted">{{ number_format($trip->route->distance_km, 1) }} km · {{ $trip->route->stops->count() }} stops</span></div>
                <div x-ref="map" class="h-[340px] bg-paper"></div>
            </section>

            {{-- Audit log --}}
            <section class="panel">
                <div class="panel-head"><h2 class="panel-title">Activity</h2></div>
                @if ($trip->adjustments->isEmpty())
                    <x-empty icon="list" title="No changes yet" text="Departures, delays and reassignments are recorded here." />
                @else
                    <ul class="divide-y divide-line">
                        @foreach ($trip->adjustments as $adjustment)
                            <li class="flex gap-4 px-5 py-3 text-sm">
                                <span class="w-14 shrink-0 text-muted tabular-nums">{{ $adjustment->created_at->format('H:i') }}</span>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <x-badge :value="$adjustment->type" />
                                        <span class="font-medium">{{ $adjustment->details }}</span>
                                    </div>
                                    <div class="mt-0.5 text-[13px] text-muted">
                                        {{ $adjustment->reason?->label() }}{{ $adjustment->reason && $adjustment->note ? ': ' : '' }}{{ $adjustment->note }}
                                        <span class="whitespace-nowrap">by {{ $adjustment->user?->name ?? 'system' }}</span>
                                    </div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>

        {{-- Actions --}}
        @can('operate-trips')
            <aside class="space-y-4 xl:sticky xl:top-24 xl:self-start">
                @if ($trip->status->isOpen())
                    @if (! $trip->hasDeparted() && $trip->trip_date->isAfter(today()))
                        <section class="panel p-5 text-sm text-muted">
                            Departure and arrival can be recorded on {{ $trip->trip_date->format('l j F') }}. You can still adjust or reassign the trip now.
                        </section>
                    @elseif (! $trip->hasDeparted())
                        <section class="panel">
                            <div class="panel-head"><h2 class="panel-title">Record departure</h2></div>
                            <form method="POST" action="{{ route('trips.depart', $trip) }}" class="space-y-4 p-5">
                                @csrf
                                <div class="grid grid-cols-2 gap-3">
                                    <x-form.input name="time" label="Time" type="time" :value="now()->format('H:i')" hint="Defaults to now" />
                                    <x-form.input name="odometer" label="Odometer" type="number" :value="$trip->bus->current_mileage" suffix="km" />
                                </div>
                                <button class="btn btn-primary w-full"><x-icon name="play" size="16" /> Record departure</button>
                            </form>
                        </section>
                    @else
                        <section class="panel">
                            <div class="panel-head"><h2 class="panel-title">Record arrival</h2></div>
                            <form method="POST" action="{{ route('trips.arrive', $trip) }}" class="space-y-4 p-5">
                                @csrf
                                <div class="grid grid-cols-2 gap-3">
                                    <x-form.input name="time" label="Time" type="time" :value="now()->format('H:i')" />
                                    <x-form.input name="odometer" label="Odometer" type="number" :min="$trip->odometer_start" :value="$trip->odometer_start ? $trip->odometer_start + (int) round($trip->route->distance_km) : null" suffix="km" />
                                </div>
                                <x-form.input name="passengers" label="Passengers carried" type="number" min="0" />
                                <button class="btn btn-primary w-full"><x-icon name="flag" size="16" /> Mark as completed</button>
                            </form>
                        </section>
                    @endif

                    <section class="panel" x-data="{ tab: 'delay' }">
                        <div class="panel-head"><h2 class="panel-title">Adjust this trip</h2></div>
                        <div class="flex border-b border-line px-5 text-sm" role="tablist">
                            @foreach (['delay' => 'Delay', 'reassign' => 'Swap bus/driver', 'cancel' => 'Cancel'] as $key => $label)
                                <button type="button" role="tab" @click="tab = '{{ $key }}'" :aria-selected="tab === '{{ $key }}'"
                                        class="-mb-px border-b-2 px-3 py-2.5 font-medium" :class="tab === '{{ $key }}' ? 'border-signal text-ink' : 'border-transparent text-muted hover:text-ink'">{{ $label }}</button>
                            @endforeach
                        </div>

                        <form x-show="tab === 'delay'" method="POST" action="{{ route('trips.delay', $trip) }}" class="space-y-4 p-5">
                            @csrf
                            <x-form.input name="minutes" label="Expected delay" type="number" min="1" max="600" :value="$trip->delay_minutes ?: 10" suffix="min" required />
                            <x-form.select name="reason" id="delay-reason" label="Reason" :options="$reasons" value="traffic" required />
                            <x-form.input name="note" id="delay-note" label="Note" placeholder="Optional" />
                            <button class="btn btn-secondary w-full"><x-icon name="timer" size="16" /> Report delay</button>
                        </form>

                        <form x-show="tab === 'reassign'" x-cloak method="POST" action="{{ route('trips.reassign', $trip) }}" class="space-y-4 p-5">
                            @csrf
                            <p class="text-[13px] text-muted">Use when a bus breaks down or a driver is unavailable. Busy buses and drivers are marked.</p>
                            <div>
                                <label for="reassign-bus" class="field-label">Replacement bus</label>
                                <select id="reassign-bus" name="bus_id" class="control">
                                    <option value="">Keep {{ $trip->bus->registration_no }}</option>
                                    @foreach ($buses as ['model' => $bus, 'busy' => $busy])
                                        @continue($bus->id === $trip->bus_id)
                                        <option value="{{ $bus->id }}" @disabled(! $bus->status->isOperational())>
                                            {{ $bus->registration_no }} · {{ $bus->seating_capacity }} seats{{ $busy ? ' — busy' : '' }}{{ ! $bus->status->isOperational() ? ' — '.$bus->status->label() : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label for="reassign-driver" class="field-label">Replacement driver</label>
                                <select id="reassign-driver" name="driver_id" class="control">
                                    <option value="">Keep {{ $trip->driver->full_name }}</option>
                                    @foreach ($drivers as ['model' => $driver, 'busy' => $busy])
                                        @continue($driver->id === $trip->driver_id)
                                        <option value="{{ $driver->id }}">{{ $driver->full_name }}{{ $busy ? ' — busy' : '' }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <x-form.select name="reason" id="reassign-reason" label="Reason" :options="$reasons" value="breakdown" required />
                            <x-form.input name="note" id="reassign-note" label="Note" placeholder="Optional" />
                            <button class="btn btn-secondary w-full"><x-icon name="swap" size="16" /> Swap</button>
                        </form>

                        <form x-show="tab === 'cancel'" x-cloak method="POST" action="{{ route('trips.cancel', $trip) }}" class="space-y-4 p-5"
                              @submit="if (! confirm('Cancel this trip? This cannot be undone.')) $event.preventDefault()">
                            @csrf
                            <x-form.select name="reason" id="cancel-reason" label="Reason" :options="$reasons" value="breakdown" required />
                            <x-form.input name="note" id="cancel-note" label="Note" placeholder="Optional" />
                            <button class="btn btn-danger w-full"><x-icon name="ban" size="16" /> Cancel trip</button>
                        </form>
                    </section>
                @else
                    <section class="panel p-5 text-sm text-muted">
                        This trip is {{ mb_strtolower($trip->status->label()) }} and can no longer be changed.
                    </section>
                @endif
            </aside>
        @endcan
    </div>
</x-layouts.app>
