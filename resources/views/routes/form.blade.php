@php $editing = $route->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit route '.$route->route_no : 'New route'">
    <x-page-header :title="$editing ? 'Edit route '.$route->route_no : 'New route'"
                   subtitle="Click the map or search for a town to add stops in travel order. Drag a marker to fine-tune its position."
                   :back="$editing ? route('routes.show', $route) : route('routes.index')" />

    <form method="POST" action="{{ $editing ? route('routes.update', $route) : route('routes.store') }}"
          x-data="routeEditor(@js($stops))" class="space-y-6">
        @csrf
        @if ($editing) @method('PUT') @endif
        <input type="hidden" name="stops" :value="json">

        {{-- Stop editor --}}
        <section class="panel overflow-hidden">
            <div class="grid lg:grid-cols-[1fr_360px]">
                <div class="relative lg:min-h-[600px]">
                    <div x-ref="map" class="h-[460px] w-full bg-paper lg:absolute lg:inset-0 lg:h-full" aria-label="Map: click to add a stop"></div>
                    <div x-show="routing" x-cloak class="absolute left-3 top-3 z-[500] rounded-md bg-white/95 px-3 py-1.5 text-sm shadow">Finding road path…</div>
                </div>

                <div class="flex flex-col border-t border-line lg:border-l lg:border-t-0">
                    <div class="border-b border-line p-4">
                        <label for="place-search" class="field-label">Find a place</label>
                        <div class="flex gap-2">
                            <input id="place-search" type="search" x-model="query" @keydown.enter.prevent="search()" placeholder="e.g. Kadawatha" class="control py-1.5 text-sm">
                            <button type="button" class="btn btn-secondary btn-sm" @click="search()" :disabled="searching">
                                <x-icon name="search" size="16" /><span class="sr-only">Search</span>
                            </button>
                        </div>
                        <ul x-show="results.length" x-cloak class="mt-2 max-h-48 overflow-auto rounded-md border border-line">
                            <template x-for="place in results" :key="place.lat + ',' + place.lng">
                                <li>
                                    <button type="button" @click="pick(place)" class="block w-full px-3 py-2 text-left text-sm hover:bg-paper">
                                        <span class="font-medium" x-text="place.name"></span>
                                        <span class="block text-[12.5px] text-muted" x-text="place.detail"></span>
                                    </button>
                                </li>
                            </template>
                        </ul>
                    </div>

                    <div class="flex items-center justify-between px-4 pt-3">
                        <h2 class="font-display text-lg font-semibold tracking-wide">Stops <span class="text-muted font-sans text-sm font-normal" x-text="'(' + stops.length + ')'"></span></h2>
                        <button type="button" class="btn btn-ghost btn-sm" @click="reverse()" x-show="stops.length > 1"><x-icon name="swap" size="16" /> Reverse</button>
                    </div>

                    <ol class="flex-1 space-y-1.5 overflow-auto p-4 lg:max-h-[380px]">
                        <template x-for="(stop, i) in stops" :key="i">
                            <li class="flex items-center gap-2 rounded-md border border-line bg-white p-1.5">
                                <button type="button" @click="focus(i)" class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full border-[3px] border-board text-[11px] font-bold"
                                        :class="(i === 0 || i === stops.length - 1) ? 'bg-board text-board-amber' : 'bg-white'" x-text="i + 1" :aria-label="'Show stop ' + (i + 1) + ' on map'"></button>
                                <input type="text" x-model="stop.name" class="min-w-0 flex-1 rounded border-transparent px-2 py-1 text-sm focus:border-line-strong focus:ring-0" :aria-label="'Name of stop ' + (i + 1)">
                                <button type="button" @click="move(i, -1)" :disabled="i === 0" class="p-1 text-muted hover:text-ink disabled:opacity-30" aria-label="Move up"><x-icon name="arrow-up" size="15" /></button>
                                <button type="button" @click="move(i, 1)" :disabled="i === stops.length - 1" class="p-1 text-muted hover:text-ink disabled:opacity-30" aria-label="Move down"><x-icon name="arrow-down" size="15" /></button>
                                <button type="button" @click="remove(i)" class="p-1 text-muted hover:text-signal" aria-label="Remove stop"><x-icon name="x" size="15" /></button>
                            </li>
                        </template>
                        <li x-show="stops.length === 0" class="rounded-md border border-dashed border-line-strong p-4 text-center text-sm text-muted">
                            Click on the map to place the first stop.
                        </li>
                    </ol>

                    @error('stops') <p class="px-4 pb-2 text-[13px] text-signal">{{ $message }}</p> @enderror

                    <div x-show="road" x-cloak class="border-t border-line bg-paper/60 p-4 text-sm">
                        <p>By road: <strong class="tabular-nums" x-text="road?.distanceKm + ' km'"></strong>, about <strong x-text="road?.durationMin + ' min'"></strong> by car.</p>
                        <button type="button" @click="useRoadFigures()" class="mt-2 btn btn-secondary btn-sm">Use for distance and running time</button>
                    </div>
                </div>
            </div>
        </section>

        {{-- Details --}}
        <section class="panel">
            <x-form.section title="Route details" description="The route number appears on the bus destination board.">
                <x-form.input name="route_no" label="Route number" :value="$route->route_no" required maxlength="10" placeholder="e.g. 138" />
                <x-form.input name="name" label="Route name" :value="$route->name" required placeholder="e.g. Pettah – Kottawa via Nugegoda" />
                <x-form.input name="origin" label="Origin" :value="$route->origin" required />
                <x-form.input name="destination" label="Destination" :value="$route->destination" required />
            </x-form.section>

            <x-form.section title="Service" description="Used to match suitable buses when building timetables.">
                <x-form.input name="distance_km" label="Distance" type="number" step="0.1" min="0.5" :value="$route->distance_km" suffix="km" required x-ref="distance" />
                <x-form.input name="estimated_duration_minutes" label="Running time" type="number" min="5" :value="$route->estimated_duration_minutes" suffix="min" required x-ref="duration" />
                <x-form.select name="service_type" label="Service type" :options="$serviceTypes" :value="$route->service_type" required />
                <x-form.input name="min_capacity" label="Minimum seats needed" type="number" min="0" :value="$route->min_capacity" hint="Leave at 0 if any bus size will do." />
                <x-form.textarea name="description" label="Notes" :value="$route->description" class="sm:col-span-2" hint="Diversions, restrictions or anything drivers should know." />
                <x-form.checkbox name="is_active" label="Route is in operation" :checked="$route->is_active ?? true" class="sm:col-span-2" hint="Inactive routes cannot be added to new timetables." />
            </x-form.section>

            <x-form.actions :cancel="$editing ? route('routes.show', $route) : route('routes.index')" :submit="$editing ? 'Save route' : 'Create route'" />
        </section>
    </form>
</x-layouts.app>
