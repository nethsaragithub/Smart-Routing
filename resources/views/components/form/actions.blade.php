@props(['cancel' => null, 'submit' => 'Save'])
<div {{ $attributes->merge(['class' => 'flex flex-col-reverse gap-2 border-t border-line px-5 py-4 sm:flex-row sm:items-center sm:justify-end']) }}>
    {{ $slot }}
    @if ($cancel)
        <a href="{{ $cancel }}" class="btn btn-secondary">Cancel</a>
    @endif
    <button type="submit" class="btn btn-primary"><x-icon name="check" size="16" /> {{ $submit }}</button>
</div>
