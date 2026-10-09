@props(['name', 'title', 'maxWidth' => 'max-w-lg'])
{{-- Open with: $dispatch('open-modal', 'name') --}}
<div x-data="{ open: false }"
     x-on:open-modal.window="if ($event.detail === '{{ $name }}') { open = true; $nextTick(() => $refs.panel.querySelector('input,select,textarea,button')?.focus()) }"
     x-on:keydown.escape.window="open = false"
     x-show="open" x-cloak class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center" role="dialog" aria-modal="true" aria-labelledby="modal-{{ $name }}-title">
    <div class="absolute inset-0 bg-ink/50 dark:bg-black/65" @click="open = false" x-show="open" x-transition.opacity></div>
    <div x-ref="panel" x-show="open" x-transition class="panel relative w-full {{ $maxWidth }} shadow-xl">
        <div class="panel-head">
            <h2 id="modal-{{ $name }}-title" class="panel-title">{{ $title }}</h2>
            <button type="button" class="btn btn-ghost btn-sm" @click="open = false" aria-label="Close"><x-icon name="x" /></button>
        </div>
        <div class="p-5">{{ $slot }}</div>
    </div>
</div>
