<x-layouts.app title="My profile">
    <x-page-header title="My profile" :subtitle="$user->role->label().' · '.($user->depot?->name ?? 'All depots')" />

    <form method="POST" action="{{ route('profile.update') }}" class="panel max-w-4xl">
        @csrf @method('PUT')
        <x-form.section title="Details">
            <x-form.input name="name" label="Full name" :value="$user->name" required />
            <x-form.input name="phone" label="Phone" type="tel" :value="$user->phone" />
            <div class="sm:col-span-2 text-sm text-muted">Signed in as <strong class="text-ink">{{ $user->email }}</strong>. Ask an administrator to change your email or role.</div>
        </x-form.section>
        <x-form.section title="Change password" description="Leave empty to keep your current password.">
            <x-form.input name="current_password" label="Current password" type="password" autocomplete="current-password" class="sm:col-span-2" />
            <x-form.input name="password" label="New password" type="password" autocomplete="new-password" />
            <x-form.input name="password_confirmation" label="Confirm new password" type="password" autocomplete="new-password" />
        </x-form.section>
        <x-form.actions submit="Save profile" />
    </form>
</x-layouts.app>
