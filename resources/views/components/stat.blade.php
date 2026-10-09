@props(['label', 'value', 'hint' => null, 'href' => null])
@php $tag = $href ? 'a' : 'div'; @endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'stat-tile block bg-panel px-5 py-4 transition-colors'.($href ? ' hover:bg-paper' : '')]) }}>
    <div class="text-sm text-muted">{{ $label }}</div>
    <div data-count class="mt-1 font-display text-[2.1rem] font-semibold leading-none tabular-nums">{{ $value }}</div>
    @if ($hint)
        <div class="mt-1.5 text-[13px] text-muted">{{ $hint }}</div>
    @endif
    {{ $slot }}
</{{ $tag }}>
