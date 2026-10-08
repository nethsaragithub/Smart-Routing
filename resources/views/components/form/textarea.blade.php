@props(['name', 'label', 'value' => null, 'rows' => 3, 'hint' => null])
@php
    $id = $attributes->get('id', $name);
    $error = $errors->first($name);
@endphp
<div {{ $attributes->only('class') }}>
    <label for="{{ $id }}" class="field-label">{{ $label }}</label>
    <textarea id="{{ $id }}" name="{{ $name }}" rows="{{ $rows }}"
              {{ $attributes->except(['class', 'id'])->merge(['class' => 'control'.($error ? ' control-error' : '')]) }}>{{ old($name, $value) }}</textarea>
    @if ($error)
        <p class="mt-1.5 text-[13px] text-signal">{{ $error }}</p>
    @elseif ($hint)
        <p class="mt-1.5 text-[13px] text-muted">{{ $hint }}</p>
    @endif
</div>
