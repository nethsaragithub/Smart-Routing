@php
    $editing = $user->exists;
    $self = $editing && $user->is(auth()->user());
@endphp
<x-layouts.app :title="$editing ? 'Edit '.$user->name : 'Add user'">
    <x-page-header :title="$editing ? 'Edit '.$user->name : 'Add user'" :back="route('users.index')" />

    <form method="POST" action="{{ $editing ? route('users.update', $user) : route('users.store') }}" class="panel"
          x-data="{ role: @js(old('role', $user->role?->value)) }">
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-form.section title="Account">
            <x-form.input name="name" label="Full name" :value="$user->name" required />
            <x-form.input name="email" label="Email" type="email" :value="$user->email" required />
            <x-form.input name="phone" label="Phone" type="tel" :value="$user->phone" />
        </x-form.section>

        <x-form.section title="Access" description="Supervisors and staff only see their own depot.">
            <div>
                <label for="role" class="field-label">Role <span class="text-signal">*</span></label>
                <select id="role" name="role" x-model="role" class="control" @disabled($self)>
                    @foreach ($roles as $role)
                        <option value="{{ $role->value }}">{{ $role->label() }}</option>
                    @endforeach
                </select>
                @if ($self)<input type="hidden" name="role" value="{{ $user->role->value }}"><p class="mt-1.5 text-[13px] text-muted">You cannot change your own role.</p>@endif
                @error('role') <p class="mt-1.5 text-[13px] text-signal">{{ $message }}</p> @enderror
            </div>
            <x-form.select name="depot_id" label="Depot" :options="$depots" :value="$user->depot_id" placeholder="All depots (administrators only)" />
            @unless ($self)
                <x-form.checkbox name="is_active" label="Account is active" :checked="$user->is_active" hint="Deactivated users cannot sign in." class="sm:col-span-2" />
            @else
                <input type="hidden" name="is_active" value="1">
            @endunless
        </x-form.section>

        <x-form.section :title="$editing ? 'Reset password' : 'Password'" :description="$editing ? 'Leave empty to keep the current password.' : 'At least 8 characters with letters and numbers.'">
            <x-form.input name="password" label="Password" type="password" autocomplete="new-password" :required="! $editing" />
            <x-form.input name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" :required="! $editing" />
        </x-form.section>

        <x-form.actions :cancel="route('users.index')" :submit="$editing ? 'Save user' : 'Create user'" />
    </form>
</x-layouts.app>
