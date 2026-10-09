@php
    $messages = array_filter([
        'success' => session('success'),
        'error' => session('error'),
        'status' => session('status'),
    ]);
@endphp
@foreach ($messages as $type => $message)
    <div x-data="{ show: true }" x-show="show" x-transition.opacity role="{{ $type === 'error' ? 'alert' : 'status' }}"
         @class([
             'mb-5 flex items-start gap-3 rounded-lg border px-4 py-3 text-sm',
             'border-emerald-200 dark:border-emerald-400/30 bg-emerald-50 dark:bg-emerald-400/10 text-emerald-900 dark:text-emerald-200' => $type !== 'error',
             'border-signal/30 bg-signal-tint text-signal-dark dark:text-red-200' => $type === 'error',
         ])>
        <x-icon :name="$type === 'error' ? 'alert' : 'check-circle'" class="mt-0.5" />
        <p class="flex-1">{{ $message }}</p>
        <button type="button" @click="show = false" class="opacity-60 hover:opacity-100" aria-label="Dismiss"><x-icon name="x" size="16" /></button>
    </div>
@endforeach
@if ($errors->any() && ! isset($hideErrorSummary))
    <div class="mb-5 rounded-lg border border-signal/30 bg-signal-tint px-4 py-3 text-sm text-signal-dark dark:text-red-200" role="alert">
        <div class="flex items-center gap-2 font-semibold"><x-icon name="alert" /> Please correct the highlighted fields.</div>
        @if ($errors->count() > 1)
            <ul class="mt-1.5 list-disc pl-9 space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        @endif
    </div>
@endif
