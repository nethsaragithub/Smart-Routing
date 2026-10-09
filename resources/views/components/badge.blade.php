@props(['value' => null, 'tone' => null, 'label' => null])
@php
    $tone = $tone ?? ($value instanceof \App\Contracts\HasBadge ? $value->tone() : 'slate');
    $label = $label ?? ($value instanceof \App\Contracts\HasBadge ? $value->label() : (string) $value);
    $styles = [
        'green' => 'bg-emerald-50 dark:bg-emerald-400/10 text-emerald-800 dark:text-emerald-300 ring-emerald-600/20',
        'amber' => 'bg-amber-50 dark:bg-amber-400/10 text-amber-900 dark:text-amber-200 ring-amber-600/25',
        'red' => 'bg-red-50 dark:bg-red-400/10 text-red-800 dark:text-red-300 ring-red-600/20',
        'blue' => 'bg-sky-50 dark:bg-sky-400/10 text-sky-800 dark:text-sky-300 ring-sky-600/20',
        'violet' => 'bg-violet-50 dark:bg-violet-400/10 text-violet-800 dark:text-violet-300 ring-violet-600/20',
        'slate' => 'bg-slate-100 dark:bg-slate-400/10 text-slate-700 dark:text-slate-300 ring-slate-500/20',
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
