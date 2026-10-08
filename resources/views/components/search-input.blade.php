@props(['placeholder' => 'Search'])
<div class="relative min-w-56 flex-1 sm:max-w-sm">
    <label for="q" class="sr-only">{{ $placeholder }}</label>
    <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-muted"><x-icon name="search" size="16" /></span>
    <input id="q" type="search" name="q" value="{{ request('q') }}" placeholder="{{ $placeholder }}" class="control py-1.5 pl-9 text-sm">
</div>
