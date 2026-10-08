@props(['name', 'label', 'options' => [], 'value' => null, 'placeholder' => null, 'hint' => null, 'required' => false])
@php
    $id = $attributes->get('id', $name);
    $error = $errors->first($name);
    $current = old($name, $value instanceof \BackedEnum ? $value->value : $value);
@endphp
<div {{ $attributes->only('class') }}>
    <label for="{{ $id }}" class="field-label">{{ $label }}@if ($required)<span class="text-signal" aria-hidden="true"> *</span>@endif</label>
    <select id="{{ $id }}" name="{{ $name }}" @required($required)
            @if ($error) aria-invalid="true" aria-describedby="{{ $id }}-error" @endif
            {{ $attributes->except(['class', 'id'])->merge(['class' => 'control'.($error ? ' control-error' : '')]) }}>
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $optionValue => $optionLabel)
            <option value="{{ $optionValue }}" @selected((string) $current === (string) $optionValue)>{{ $optionLabel }}</option>
        @endforeach
        {{ $slot }}
    </select>
    @if ($error)
        <p id="{{ $id }}-error" class="mt-1.5 text-[13px] text-signal">{{ $error }}</p>
    @elseif ($hint)
        <p class="mt-1.5 text-[13px] text-muted">{{ $hint }}</p>
    @endif
</div>
