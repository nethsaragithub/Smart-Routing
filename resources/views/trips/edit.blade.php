<x-layouts.app :title="'Edit trip '.$trip->route->route_no.' '.$trip->scheduled_departure->format('H:i')">
    <x-page-header :title="'Edit trip · '.$trip->scheduled_departure->format('H:i').' to '.$trip->route->destination"
                   :subtitle="$trip->trip_date->format('l j F Y').'. Corrections apply to finished trips too and are written to the activity log.'"
                   :back="route('trips.show', $trip)" />

    <form method="POST" action="{{ route('trips.update', $trip) }}" class="panel">
        @csrf
        @method('PUT')

        <x-form.section title="Assignment" description="Change the bus or driver on record. Clash checks are not applied to corrections.">
            <x-form.select name="bus_id" label="Bus" :value="$trip->bus_id" required
                           :options="$buses->mapWithKeys(fn ($b) => [$b->id => $b->registration_no.($b->trashed() ? ' (removed)' : '')])" />
            <x-form.select name="driver_id" label="Driver" :value="$trip->driver_id" required
                           :options="$drivers->mapWithKeys(fn ($d) => [$d->id => $d->full_name.($d->trashed() ? ' (removed)' : '')])" />
        </x-form.section>

        <x-form.section title="Running" description="Times are for the trip day. A completed trip needs both departure and arrival.">
            <x-form.select name="status" label="Status" :options="$statuses" :value="$trip->status" required />
            <x-form.input name="delay_minutes" label="Delay" type="number" min="0" max="1440" :value="$trip->delay_minutes" suffix="min" required />
            <x-form.input name="actual_departure" label="Departed" type="datetime-local" :value="$trip->actual_departure?->format('Y-m-d\TH:i')" />
            <x-form.input name="actual_arrival" label="Arrived" type="datetime-local" :value="$trip->actual_arrival?->format('Y-m-d\TH:i')" />
        </x-form.section>

        <x-form.section title="Readings" description="The bus odometer is raised to the closing reading if it is higher.">
            <x-form.input name="odometer_start" label="Opening odometer" type="number" min="0" :value="$trip->odometer_start" suffix="km" />
            <x-form.input name="odometer_end" label="Closing odometer" type="number" min="0" :value="$trip->odometer_end" suffix="km" />
            <x-form.input name="passenger_count" label="Passengers carried" type="number" min="0" max="300" :value="$trip->passenger_count" />
            <x-form.textarea name="remarks" label="Remarks" :value="$trip->remarks" class="sm:col-span-2" />
        </x-form.section>

        <x-form.section title="Reason" description="Saved with the correction in the activity log.">
            <x-form.input name="note" label="Why is this being corrected?" placeholder="e.g. Arrival entered an hour late" class="sm:col-span-2" />
        </x-form.section>

        <x-form.actions :cancel="route('trips.show', $trip)" submit="Save trip" />
    </form>
</x-layouts.app>
