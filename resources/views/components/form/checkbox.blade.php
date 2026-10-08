@props(['name', 'label', 'checked' => false, 'hint' => null])
@php $id = $attributes->get('id', $name); @endphp
<div {{ $attributes->only('class') }}>
    <input type="hidden" name="{{ $name }}" value="0">
    <label for="{{ $id }}" class="flex items-start gap-3 cursor-pointer">
        <input id="{{ $id }}" type="checkbox" name="{{ $name }}" value="1" @checked(old($name, $checked))
               {{ $attributes->except(['class', 'id'])->merge(['class' => 'mt-0.5 h-4.5 w-4.5 rounded border-line-strong text-signal focus:ring-signal/30']) }}>
        <span>
            <span class="block text-sm font-medium">{{ $label }}</span>
            @if ($hint)
                <span class="block text-[13px] text-muted">{{ $hint }}</span>
            @endif
        </span>
    </label>
</div>
