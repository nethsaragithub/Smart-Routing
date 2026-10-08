@props(['value' => null, 'tone' => null, 'label' => null])
@php
    $tone = $tone ?? ($value instanceof \App\Contracts\HasBadge ? $value->tone() : 'slate');
    $label = $label ?? ($value instanceof \App\Contracts\HasBadge ? $value->label() : (string) $value);
    $styles = [
        'green' => 'bg-emerald-50 text-emerald-800 ring-emerald-600/20',
        'amber' => 'bg-amber-50 text-amber-900 ring-amber-600/25',
        'red' => 'bg-red-50 text-red-800 ring-red-600/20',
        'blue' => 'bg-sky-50 text-sky-800 ring-sky-600/20',
        'violet' => 'bg-violet-50 text-violet-800 ring-violet-600/20',
        'slate' => 'bg-slate-100 text-slate-700 ring-slate-500/20',
    ];
    $dots = [
        'green' => 'bg-emerald-500', 'amber' => 'bg-amber-500', 'red' => 'bg-red-500',
        'blue' => 'bg-sky-500', 'violet' => 'bg-violet-500', 'slate' => 'bg-slate-400',
    ];
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 whitespace-nowrap rounded-full px-2 py-0.5 text-[12.5px] font-medium ring-1 ring-inset '.($styles[$tone] ?? $styles['slate'])]) }}>
    <span class="h-1.5 w-1.5 rounded-full {{ $dots[$tone] ?? $dots['slate'] }}" aria-hidden="true"></span>
    {{ $label }}
</span>
