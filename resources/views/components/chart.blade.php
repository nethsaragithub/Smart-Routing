@props(['definition', 'height' => 280, 'label' => 'Chart'])
{{-- Canvas chart with an accessible table fallback for screen readers. --}}
<div {{ $attributes }}>
    <div x-data="chart(@js($definition))" style="height: {{ $height }}px" class="relative">
        <canvas x-ref="canvas" role="img" aria-label="{{ $label }}"></canvas>
    </div>
    {{-- Wrapped in a div: tables ignore the 1px width that sr-only relies on. --}}
    <div class="sr-only">
    <table>
        <caption>{{ $label }}</caption>
        <thead><tr><th>Category</th>@foreach ($definition['datasets'] as $set)<th>{{ $set['label'] }}</th>@endforeach</tr></thead>
        <tbody>
        @foreach ($definition['labels'] as $i => $category)
            <tr><td>{{ $category }}</td>@foreach ($definition['datasets'] as $set)<td>{{ $set['data'][$i] ?? '' }}</td>@endforeach</tr>
        @endforeach
        </tbody>
    </table>
    </div>
</div>
