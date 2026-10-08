@props(['title', 'subtitle' => null, 'back' => null])
<div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
    <div class="min-w-0">
        @if ($back)
            <a href="{{ $back }}" class="mb-1.5 inline-flex items-center gap-1 text-sm text-muted hover:text-ink">
                <x-icon name="chevron-left" size="16" /> Back
            </a>
        @endif
        <h1 class="font-display text-[2rem] font-semibold leading-none tracking-wide">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-2 max-w-2xl text-[15px] text-muted">{{ $subtitle }}</p>
        @endif
        {{ $meta ?? '' }}
    </div>
    @if (isset($actions))
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endif
</div>
