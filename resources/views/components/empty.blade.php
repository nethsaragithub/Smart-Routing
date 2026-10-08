@props(['icon' => 'info', 'title', 'text' => null])
<div {{ $attributes->merge(['class' => 'flex flex-col items-center px-6 py-12 text-center']) }}>
    <span class="mb-3 flex h-11 w-11 items-center justify-center rounded-full bg-paper text-muted"><x-icon :name="$icon" size="22" /></span>
    <p class="font-semibold">{{ $title }}</p>
    @if ($text)
        <p class="mt-1 max-w-md text-sm text-muted">{{ $text }}</p>
    @endif
    @if (trim($slot) !== '')
        <div class="mt-4">{{ $slot }}</div>
    @endif
</div>
