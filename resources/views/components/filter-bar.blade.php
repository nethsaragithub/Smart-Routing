@props(['action'])
{{-- One row of filters above a list. Fields auto-submit on change. --}}
<form method="GET" action="{{ $action }}" x-data
      @change="if ($event.target.tagName === 'SELECT' || $event.target.type === 'date') $el.requestSubmit()"
      {{ $attributes->merge(['class' => 'flex flex-wrap items-end gap-3 border-b border-line px-4 py-3']) }}>
    {{ $slot }}
    @if (request()->query())
        <a href="{{ $action }}" class="btn btn-ghost btn-sm">Clear filters</a>
    @endif
</form>
