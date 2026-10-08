@props(['no', 'size' => 'md'])
@php
    $sizes = [
        'sm' => 'h-6 min-w-9 px-1.5 text-[15px]',
        'md' => 'h-8 min-w-12 px-2 text-xl',
        'lg' => 'h-12 min-w-16 px-3 text-[2rem]',
    ];
@endphp
<span {{ $attributes->merge(['class' => 'route-board '.$sizes[$size]]) }} title="Route {{ $no }}">{{ $no }}</span>
