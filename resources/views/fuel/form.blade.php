@php $editing = $log->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit fuel entry' : 'Log fill-up'">
    <x-page-header :title="$editing ? 'Edit fuel entry' : 'Log fill-up'" subtitle="Record every fill so fuel economy can be calculated per bus, route and driver." :back="route('fuel.index')" />

    <form method="POST" action="{{ $editing ? route('fuel.update', $log) : route('fuel.store') }}" class="panel"
          x-data="{ litres: @js(old('litres', $log->litres)), price: @js(old('price_per_litre', $log->price_per_litre)) }">
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-form.section title="Vehicle">
            <x-form.select name="bus_id" label="Bus" :options="$buses->mapWithKeys(fn ($b) => [$b->id => $b->registration_no.($b->trashed() ? ' (removed)' : '')])" :value="$log->bus_id" placeholder="Choose a bus" required />
            <x-form.input name="filled_on" label="Date" type="date" :value="$log->filled_on" required max="{{ today()->toDateString() }}" />
            <x-form.select name="driver_id" label="Driver" :options="$drivers->mapWithKeys(fn ($d) => [$d->id => $d->full_name.($d->trashed() ? ' (removed)' : '')])" :value="$log->driver_id" placeholder="Not recorded" hint="Needed for driver fuel-economy comparisons." />
            <x-form.select name="bus_route_id" label="Route operated" :options="$routes->mapWithKeys(fn ($r) => [$r->id => $r->route_no.' · '.$r->destination.($r->trashed() ? ' (removed)' : '')])" :value="$log->bus_route_id" placeholder="Not recorded" hint="Needed to find high-usage routes." />
        </x-form.section>

        <x-form.section title="Fill-up">
            <x-form.input name="odometer" label="Odometer reading" type="number" min="0" :value="$log->odometer" suffix="km" required />
            <x-form.input name="station" label="Filling station" :value="$log->station" placeholder="e.g. Depot pump" />
            <x-form.input name="litres" label="Litres" type="number" step="0.01" min="1" suffix="L" required x-model.number="litres" />
            <x-form.input name="price_per_litre" label="Price per litre" type="number" step="0.01" min="1" suffix="Rs" required x-model.number="price" />
            <div class="sm:col-span-2 rounded-md bg-paper px-4 py-3 text-sm">
                Total cost <strong class="ml-1 tabular-nums" x-text="'Rs ' + ((litres || 0) * (price || 0)).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></strong>
            </div>
            <x-form.checkbox name="full_tank" label="Filled to a full tank" :checked="$log->full_tank ?? true" class="sm:col-span-2" hint="Fuel economy is measured between full-tank fills." />
            <x-form.textarea name="notes" label="Notes" :value="$log->notes" class="sm:col-span-2" rows="2" />
        </x-form.section>

        <x-form.actions :cancel="route('fuel.index')" :submit="$editing ? 'Save entry' : 'Record fill-up'">
            @if ($editing && auth()->user()->can('manage-fuel-maintenance'))
                <button type="submit" form="delete-fuel" class="btn btn-danger sm:mr-auto"><x-icon name="trash" size="16" /> Delete entry</button>
            @endif
        </x-form.actions>
    </form>

    @if ($editing && auth()->user()->can('manage-fuel-maintenance'))
        <form id="delete-fuel" method="POST" action="{{ route('fuel.destroy', $log) }}" onsubmit="return confirm('Delete this fuel entry?')">
            @csrf @method('DELETE')
        </form>
    @endif
</x-layouts.app>
