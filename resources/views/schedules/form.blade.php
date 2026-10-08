@php
    $editing = $schedule->exists;
    $conflicts = session('conflicts');
    $days = [1 => 'Mon', 2 => 'Tue', 3 => 'Wed', 4 => 'Thu', 5 => 'Fri', 6 => 'Sat', 7 => 'Sun'];
    $toOption = fn ($label, $model) => ['id' => $model->id, 'label' => $label, 'suitable' => true, 'notes' => []];
    $config = [
        'routeId' => old('bus_route_id', $schedule->bus_route_id),
        'busId' => old('bus_id', $schedule->bus_id),
        'driverId' => old('driver_id', $schedule->driver_id),
        'departure' => old('departure_time', $schedule->departure_time ? substr($schedule->departure_time, 0, 5) : ''),
        'arrival' => old('arrival_time', $schedule->arrival_time ? substr($schedule->arrival_time, 0, 5) : ''),
        'recurrence' => old('recurrence', $schedule->recurrence?->value ?? 'daily'),
        'weekdays' => old('weekdays', $schedule->weekdays ?? []),
        'monthDays' => old('month_days', $schedule->month_days ?? []),
        'startDate' => old('start_date', $schedule->start_date?->toDateString()),
        'endDate' => old('end_date', $schedule->end_date?->toDateString()),
        'scheduleId' => $schedule->id,
        'optionsUrl' => route('schedules.options'),
        'checkUrl' => route('schedules.check'),
        'initialReport' => $conflicts,
        'buses' => $buses->map(fn ($b) => $toOption($b->registration_no.' · '.$b->seating_capacity.' seats', $b))->values(),
        'drivers' => $drivers->map(fn ($d) => $toOption($d->full_name.' ('.$d->employee_no.')', $d))->values(),
    ];
@endphp
<x-layouts.app :title="$editing ? 'Edit timetable' : 'New timetable'">
    <x-page-header :title="$editing ? 'Edit timetable' : 'New timetable'"
                   subtitle="Choose a route and time first. Buses and drivers that fit are listed first, and clashes are checked as you type."
                   :back="$editing ? route('schedules.show', $schedule) : route('schedules.index')" />

    <form method="POST" action="{{ $editing ? route('schedules.update', $schedule) : route('schedules.store') }}"
          x-data="scheduleForm(@js($config))" class="grid gap-6 xl:grid-cols-[1fr_380px]">
        @csrf
        @if ($editing) @method('PUT') @endif

        <section class="panel self-start">
            <x-form.section title="Route and time" description="Arrival is filled in from the route's running time; you can change it.">
                <div class="sm:col-span-2">
                    <label for="bus_route_id" class="field-label">Route <span class="text-signal" aria-hidden="true">*</span></label>
                    <select id="bus_route_id" name="bus_route_id" x-model="routeId" required class="control @error('bus_route_id') control-error @enderror">
                        <option value="">Choose a route</option>
                        @foreach ($routes as $route)
                            <option value="{{ $route->id }}">{{ $route->route_no }} · {{ $route->origin }} – {{ $route->destination }} ({{ $route->durationLabel() }})</option>
                        @endforeach
                    </select>
                    @error('bus_route_id') <p class="mt-1.5 text-[13px] text-signal">{{ $message }}</p> @enderror
                    <p x-show="routeInfo" x-cloak class="mt-1.5 text-[13px] text-muted">
                        <span x-text="routeInfo?.service_type"></span> service<span x-show="routeInfo?.min_capacity > 0" x-text="', at least ' + routeInfo?.min_capacity + ' seats'"></span>.
                    </p>
                </div>
                <div>
                    <label for="departure_time" class="field-label">Departure <span class="text-signal" aria-hidden="true">*</span></label>
                    <input id="departure_time" name="departure_time" type="time" x-model="departure" required class="control @error('departure_time') control-error @enderror">
                    @error('departure_time') <p class="mt-1.5 text-[13px] text-signal">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="arrival_time" class="field-label">Arrival <span class="text-signal" aria-hidden="true">*</span></label>
                    <input id="arrival_time" name="arrival_time" type="time" x-model="arrival" @input="arrivalTouched = true" required class="control @error('arrival_time') control-error @enderror">
                    @error('arrival_time') <p class="mt-1.5 text-[13px] text-signal">{{ $message }}</p> @enderror
                </div>
            </x-form.section>

            <x-form.section title="Bus and driver" description="Options marked with a note may cause a clash or a warning.">
                <div class="sm:col-span-2">
                    <label for="bus_id" class="field-label">Bus <span class="text-signal" aria-hidden="true">*</span></label>
                    <select id="bus_id" name="bus_id" x-model="busId" required class="control @error('bus_id') control-error @enderror">
                        <option value="">Choose a bus</option>
                        <template x-for="bus in buses" :key="bus.id">
                            <option :value="String(bus.id)" :selected="String(bus.id) === busId" x-text="bus.label + (bus.notes.length ? '  —  ' + bus.notes.join(', ') : '  ✓')"></option>
                        </template>
                    </select>
                    @error('bus_id') <p class="mt-1.5 text-[13px] text-signal">{{ $message }}</p> @enderror
                    <p x-show="noteFor(buses, busId)" x-cloak class="mt-1.5 text-[13px] text-amber-800" x-text="noteFor(buses, busId)"></p>
                </div>
                <div class="sm:col-span-2">
                    <label for="driver_id" class="field-label">Driver <span class="text-signal" aria-hidden="true">*</span></label>
                    <select id="driver_id" name="driver_id" x-model="driverId" required class="control @error('driver_id') control-error @enderror">
                        <option value="">Choose a driver</option>
                        <template x-for="driver in drivers" :key="driver.id">
                            <option :value="String(driver.id)" :selected="String(driver.id) === driverId" x-text="driver.label + (driver.notes.length ? '  —  ' + driver.notes.join(', ') : '  ✓')"></option>
                        </template>
                    </select>
                    @error('driver_id') <p class="mt-1.5 text-[13px] text-signal">{{ $message }}</p> @enderror
                    <p x-show="noteFor(drivers, driverId)" x-cloak class="mt-1.5 text-[13px] text-amber-800" x-text="noteFor(drivers, driverId)"></p>
                </div>
            </x-form.section>

            <x-form.section title="When it runs" description="Daily, on chosen weekdays, or on chosen dates each month.">
                <fieldset class="sm:col-span-2">
                    <legend class="field-label">Repeats</legend>
                    <div class="inline-flex rounded-md border border-line-strong p-0.5" role="radiogroup">
                        @foreach ($recurrences as $recurrence)
                            <label class="cursor-pointer">
                                <input type="radio" name="recurrence" value="{{ $recurrence->value }}" x-model="recurrence" class="peer sr-only">
                                <span class="block rounded px-4 py-1.5 text-sm font-medium text-ink-soft peer-checked:bg-ink peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-signal">{{ $recurrence->label() }}</span>
                            </label>
                        @endforeach
                    </div>
                </fieldset>

                <fieldset class="sm:col-span-2" x-show="recurrence === 'weekly'" x-cloak>
                    <legend class="field-label">On these days</legend>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($days as $num => $day)
                            <label class="cursor-pointer">
                                <input type="checkbox" name="weekdays[]" value="{{ $num }}" x-model="weekdays" class="peer sr-only" :disabled="recurrence !== 'weekly'">
                                <span class="flex h-10 w-12 items-center justify-center rounded-md border border-line-strong text-sm font-medium peer-checked:border-ink peer-checked:bg-ink peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-signal">{{ $day }}</span>
                            </label>
                        @endforeach
                    </div>
                    @error('weekdays') <p class="mt-1.5 text-[13px] text-signal">{{ $message }}</p> @enderror
                </fieldset>

                <fieldset class="sm:col-span-2" x-show="recurrence === 'monthly'" x-cloak>
                    <legend class="field-label">On these dates</legend>
                    <div class="grid max-w-sm grid-cols-7 gap-1.5">
                        @for ($d = 1; $d <= 31; $d++)
                            <label class="cursor-pointer">
                                <input type="checkbox" name="month_days[]" value="{{ $d }}" x-model="monthDays" class="peer sr-only" :disabled="recurrence !== 'monthly'">
                                <span class="flex h-9 items-center justify-center rounded border border-line-strong text-sm tabular-nums peer-checked:border-ink peer-checked:bg-ink peer-checked:text-white peer-focus-visible:ring-2 peer-focus-visible:ring-signal">{{ $d }}</span>
                            </label>
                        @endfor
                    </div>
                    @error('month_days') <p class="mt-1.5 text-[13px] text-signal">{{ $message }}</p> @enderror
                </fieldset>

                <div>
                    <label for="start_date" class="field-label">Starts on <span class="text-signal" aria-hidden="true">*</span></label>
                    <input id="start_date" name="start_date" type="date" x-model="startDate" required class="control @error('start_date') control-error @enderror">
                    @error('start_date') <p class="mt-1.5 text-[13px] text-signal">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="end_date" class="field-label">Ends on</label>
                    <input id="end_date" name="end_date" type="date" x-model="endDate" class="control @error('end_date') control-error @enderror">
                    @error('end_date') <p class="mt-1.5 text-[13px] text-signal">{{ $message }}</p> @else <p class="mt-1.5 text-[13px] text-muted">Leave empty to run until further notice.</p> @enderror
                </div>
                <x-form.textarea name="notes" label="Notes" :value="$schedule->notes" class="sm:col-span-2" rows="2" />
            </x-form.section>
        </section>

        {{-- Live conflict panel --}}
        <aside class="space-y-4 xl:sticky xl:top-24 xl:self-start" aria-live="polite">
            <section class="panel">
                <div class="panel-head">
                    <h2 class="panel-title">Clash check</h2>
                    <span x-show="checking" x-cloak class="text-[13px] text-muted">Checking…</span>
                </div>
                <div class="p-5 text-sm">
                    <p x-show="!report" class="text-muted">Choose a route, times, bus and driver to check for clashes.</p>

                    <template x-if="report && report.clear">
                        <div class="flex gap-3 rounded-md bg-emerald-50 p-3 text-emerald-900">
                            <x-icon name="check-circle" class="mt-0.5" />
                            <p><strong>No clashes.</strong> The bus, driver and route are free at this time.</p>
                        </div>
                    </template>

                    <template x-if="report && report.errors.length">
                        <div class="mb-3">
                            <p class="mb-2 font-semibold text-signal">Must be fixed before saving</p>
                            <ul class="space-y-2">
                                <template x-for="item in report.errors">
                                    <li class="flex gap-2 rounded-md bg-signal-tint p-3 text-signal-dark"><x-icon name="ban" size="16" class="mt-0.5" /><span x-text="item.message"></span></li>
                                </template>
                            </ul>
                        </div>
                    </template>

                    <template x-if="report && report.warnings.length">
                        <div>
                            <p class="mb-2 font-semibold text-amber-800">Warnings</p>
                            <ul class="space-y-2">
                                <template x-for="item in report.warnings">
                                    <li class="flex gap-2 rounded-md bg-amber-50 p-3 text-amber-900"><x-icon name="alert" size="16" class="mt-0.5" /><span x-text="item.message"></span></li>
                                </template>
                            </ul>
                        </div>
                    </template>
                </div>

                <div x-show="hasWarnings && !hasErrors" x-cloak class="border-t border-line px-5 py-4">
                    <x-form.checkbox name="acknowledge_warnings" label="I have reviewed the warnings" hint="Save the timetable anyway." />
                </div>

                <div class="flex flex-col gap-2 border-t border-line px-5 py-4">
                    <button type="submit" class="btn btn-primary" :disabled="hasErrors">
                        <x-icon name="check" size="16" /> {{ $editing ? 'Save timetable' : 'Create timetable' }}
                    </button>
                    <a href="{{ $editing ? route('schedules.show', $schedule) : route('schedules.index') }}" class="btn btn-secondary">Cancel</a>
                </div>
            </section>
            <p class="px-1 text-[13px] text-muted">
                Rules checked: bus turnaround {{ config('srmss.bus_turnaround_minutes') }} min, driver rest {{ config('srmss.driver_rest_minutes') }} min,
                {{ config('srmss.route_headway_minutes') }} min between departures on a route, licence validity, bus status and service type, weekly hours.
            </p>
        </aside>
    </form>
</x-layouts.app>
