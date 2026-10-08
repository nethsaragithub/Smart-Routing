@php $editing = $depot->exists; @endphp
<x-layouts.app :title="$editing ? 'Edit '.$depot->name : 'Add depot'">
    <x-page-header :title="$editing ? 'Edit '.$depot->name : 'Add depot'" :back="route('depots.index')" />

    <form method="POST" action="{{ $editing ? route('depots.update', $depot) : route('depots.store') }}" class="panel">
        @csrf
        @if ($editing) @method('PUT') @endif
        <x-form.section title="Depot">
            <x-form.input name="code" label="Depot code" :value="$depot->code" required maxlength="10" placeholder="e.g. MHR" />
            <x-form.input name="name" label="Name" :value="$depot->name" required placeholder="e.g. Maharagama Depot" />
            <x-form.input name="location" label="Location" :value="$depot->location" required />
            <x-form.input name="phone" label="Phone" type="tel" :value="$depot->phone" />
            <x-form.input name="latitude" label="Latitude" type="number" step="0.0000001" :value="$depot->latitude" />
            <x-form.input name="longitude" label="Longitude" type="number" step="0.0000001" :value="$depot->longitude" />
        </x-form.section>
        <x-form.actions :cancel="route('depots.index')" :submit="$editing ? 'Save depot' : 'Add depot'" />
    </form>
</x-layouts.app>
