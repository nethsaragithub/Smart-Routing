@php $editing = $bus->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit '.$bus->registration_no : 'Add bus'">
    <x-page-header :title="$editing ? 'Edit '.$bus->registration_no : 'Add bus'" :back="$editing ? route('buses.show', $bus) : route('buses.index')" />

    <form method="POST" action="{{ $editing ? route('buses.update', $bus) : route('buses.store') }}" class="panel">
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-form.section title="Registration" description="As shown on the revenue licence.">
            <x-form.input name="registration_no" label="Registration number" :value="$bus->registration_no" required placeholder="NB-1234" hint="Format: NB-1234 or WP NB-1234" />
            <x-form.input name="fleet_no" label="Fleet number" :value="$bus->fleet_no" placeholder="Optional" />
            <x-form.input name="make" label="Make" :value="$bus->make" required placeholder="e.g. Ashok Leyland" />
            <x-form.input name="model" label="Model" :value="$bus->model" required placeholder="e.g. Viking" />
            <x-form.input name="year_of_manufacture" label="Year of manufacture" type="number" :value="$bus->year_of_manufacture" />
            <x-form.select name="fuel_type" label="Fuel" :options="['diesel' => 'Diesel', 'hybrid' => 'Hybrid', 'electric' => 'Electric']" :value="$bus->fuel_type" required />
        </x-form.section>

        <x-form.section title="Service" description="Used to match the bus to suitable routes.">
            <x-form.select name="service_type" label="Service type" :options="$serviceTypes" :value="$bus->service_type" required />
            <x-form.input name="seating_capacity" label="Seats" type="number" min="10" max="120" :value="$bus->seating_capacity" required />
            <x-form.select name="status" label="Status" :options="$statuses" :value="$bus->status" required hint="Buses under maintenance or out of service cannot be scheduled." />
        </x-form.section>

        <x-form.section title="Mileage and servicing" description="Service reminders appear on the dashboard when a bus is within 500 km of its next service.">
            <x-form.input name="current_mileage" label="Current odometer" type="number" min="0" :value="$bus->current_mileage ?? 0" suffix="km" required />
            <x-form.input name="service_interval_km" label="Service every" type="number" min="1000" :value="$bus->service_interval_km" suffix="km" required />
            <x-form.input name="last_service_mileage" label="Odometer at last service" type="number" min="0" :value="$bus->last_service_mileage" suffix="km" />
            <x-form.input name="last_service_date" label="Last service date" type="date" :value="$bus->last_service_date" />
            <x-form.textarea name="notes" label="Notes" :value="$bus->notes" class="sm:col-span-2" />
        </x-form.section>

        <x-form.actions :cancel="$editing ? route('buses.show', $bus) : route('buses.index')" :submit="$editing ? 'Save bus' : 'Add bus'" />
    </form>
</x-layouts.app>
