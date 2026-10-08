@php $editing = $driver->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit '.$driver->full_name : 'Add driver'">
    <x-page-header :title="$editing ? 'Edit '.$driver->full_name : 'Add driver'" :back="$editing ? route('drivers.show', $driver) : route('drivers.index')" />

    <form method="POST" action="{{ $editing ? route('drivers.update', $driver) : route('drivers.store') }}" class="panel">
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-form.section title="Personal details">
            <x-form.input name="full_name" label="Full name" :value="$driver->full_name" required class="sm:col-span-2" placeholder="e.g. K. A. Sunil Perera" />
            <x-form.input name="employee_no" label="Employee number" :value="$driver->employee_no" required />
            <x-form.input name="nic" label="NIC number" :value="$driver->nic" required hint="9 digits + V/X, or 12 digits" />
            <x-form.input name="date_of_birth" label="Date of birth" type="date" :value="$driver->date_of_birth" />
            <x-form.input name="phone" label="Mobile number" type="tel" :value="$driver->phone" required placeholder="07XXXXXXXX" />
            <x-form.input name="address" label="Address" :value="$driver->address" class="sm:col-span-2" />
        </x-form.section>

        <x-form.section title="Driving licence" description="Drivers with an expired licence cannot be rostered.">
            <x-form.input name="license_no" label="Licence number" :value="$driver->license_no" required />
            <x-form.input name="license_class" label="Vehicle class" :value="$driver->license_class" required hint="Heavy passenger vehicles: D or D1" />
            <x-form.input name="license_expiry" label="Expiry date" type="date" :value="$driver->license_expiry" required />
        </x-form.section>

        <x-form.section title="Employment">
            <x-form.input name="joined_on" label="Joined the depot" type="date" :value="$driver->joined_on" />
            <x-form.select name="status" label="Status" :options="$statuses" :value="$driver->status" required />
            <x-form.input name="max_weekly_hours" label="Maximum hours per week" type="number" min="10" max="84" :value="$driver->max_weekly_hours" suffix="hours" required />
            <x-form.textarea name="notes" label="Notes" :value="$driver->notes" class="sm:col-span-2" />
        </x-form.section>

        <x-form.actions :cancel="$editing ? route('drivers.show', $driver) : route('drivers.index')" :submit="$editing ? 'Save driver' : 'Add driver'" />
    </form>
</x-layouts.app>
