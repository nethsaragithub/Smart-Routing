@props(['name', 'label', 'options' => [], 'all' => 'All'])
<div>
    <label for="filter-{{ $name }}" class="sr-only">{{ $label }}</label>
    <select id="filter-{{ $name }}" name="{{ $name }}" class="control py-1.5 pr-8 text-sm">
        <option value="">{{ $label }}: {{ $all }}</option>
        @foreach ($options as $value => $text)
            <option value="{{ $value }}" @selected((string) request($name) === (string) $value)>{{ $text }}</option>
        @endforeach
    </select>
</div>
