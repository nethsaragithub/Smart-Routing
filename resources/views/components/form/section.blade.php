@props(['title', 'description' => null])
{{-- A titled group of fields inside a form panel. --}}
<div {{ $attributes->merge(['class' => 'grid gap-5 border-b border-line px-5 py-6 last:border-b-0 lg:grid-cols-[220px_1fr] lg:gap-8']) }}>
    <div>
        <h2 class="font-display text-lg font-semibold tracking-wide">{{ $title }}</h2>
        @if ($description)
            <p class="mt-1 text-[13px] text-muted">{{ $description }}</p>
        @endif
    </div>
    <div class="grid gap-4 sm:grid-cols-2">{{ $slot }}</div>
</div>
