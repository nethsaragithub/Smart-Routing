@props(['name', 'label', 'type' => 'text', 'value' => null, 'hint' => null, 'required' => false, 'suffix' => null])
@php
    $id = $attributes->get('id', str_replace(['[', ']'], ['_', ''], $name));
    $error = $errors->first(rtrim(str_replace(['[', ']'], ['.', ''], $name), '.'));
    $current = old(str_replace(['[', ']'], ['.', ''], $name), $value);
    if ($current instanceof \Carbon\CarbonInterface) {
        $current = $type === 'date' ? $current->toDateString() : $current->format('H:i');
    }
@endphp
<div {{ $attributes->only('class') }}>
    <label for="{{ $id }}" class="field-label">{{ $label }}@if ($required)<span class="text-signal" aria-hidden="true"> *</span>@endif</label>
    <div class="relative">
        <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" value="{{ $current }}" @required($required)
               @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @elseif ($hint) aria-describedby="{{ $id }}-hint" @endif
               {{ $attributes->except(['class', 'id'])->merge(['class' => 'control'.($error ? ' control-error' : '').($suffix ? ' pr-14' : '')]) }}>
        @if ($suffix)
            <span class="pointer-events-none absolute inset-y-0 right-3 flex items-center text-sm text-muted">{{ $suffix }}</span>
        @endif
    </div>
    @if ($error)
        <p id="{{ $id }}-error" class="mt-1.5 text-[13px] text-signal">{{ $error }}</p>
    @elseif ($hint)
        <p id="{{ $id }}-hint" class="mt-1.5 text-[13px] text-muted">{{ $hint }}</p>
    @endif
</div>
