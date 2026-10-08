@php $editing = $record->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit maintenance job' : 'New maintenance job'">
    <x-page-header :title="$editing ? 'Edit maintenance job' : 'New maintenance job'" :back="route('maintenance.index')" />

    <form method="POST" action="{{ $editing ? route('maintenance.update', $record) : route('maintenance.store') }}" class="panel">
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-form.section title="Job" description="Routine: planned servicing. Corrective: repairs after a fault or breakdown.">
            <x-form.select name="bus_id" label="Bus" :options="$buses->pluck('registration_no', 'id')" :value="$record->bus_id" placeholder="Choose a bus" required />
            <x-form.input name="scheduled_for" label="Planned date" type="date" :value="$record->scheduled_for" required />
            <x-form.select name="type" label="Type" :options="$types" :value="$record->type" required />
            <x-form.select name="category" label="Category" :options="$categories" :value="$record->category" required />
            <x-form.input name="title" label="Work to be done" :value="$record->title" required class="sm:col-span-2" placeholder="e.g. 10,000 km service, brake pads replaced" />
            <x-form.textarea name="description" label="Details" :value="$record->description" class="sm:col-span-2" />
        </x-form.section>

        <x-form.section title="Workshop and cost" description="Cost can be added now or when the job is completed.">
            <x-form.input name="workshop" label="Workshop" :value="$record->workshop" placeholder="e.g. Depot workshop" />
            <x-form.input name="odometer" label="Odometer" type="number" min="0" :value="$record->odometer" suffix="km" />
            <x-form.input name="cost" label="Cost" type="number" step="0.01" min="0" :value="$record->cost" suffix="Rs" />
            @unless ($editing)
                <x-form.checkbox name="start_now" label="The bus is in the workshop now" hint="Takes the bus off the road immediately." class="sm:col-span-2" />
            @endunless
        </x-form.section>

        <x-form.actions :cancel="route('maintenance.index')" :submit="$editing ? 'Save job' : 'Create job'">
            @if ($editing && $record->status !== \App\Enums\MaintenanceStatus::InProgress)
                <button type="submit" form="delete-job" class="btn btn-danger sm:mr-auto"><x-icon name="trash" size="16" /> Delete job</button>
            @endif
        </x-form.actions>
    </form>

    @if ($editing)
        <form id="delete-job" method="POST" action="{{ route('maintenance.destroy', $record) }}" onsubmit="return confirm('Delete this maintenance job?')">
            @csrf @method('DELETE')
        </form>
    @endif
</x-layouts.app>
