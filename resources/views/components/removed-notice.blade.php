@props(['model', 'what'])
{{-- Shown on the detail page of a soft-deleted bus, driver or route. --}}
@if ($model->trashed())
    <div class="mb-6 flex items-center gap-2 rounded-lg border border-amber-300 dark:border-amber-400/30 bg-amber-50 dark:bg-amber-400/10 p-4 text-sm text-amber-900 dark:text-amber-200" role="status">
        <x-icon name="alert" size="18" />
        {{ $what }} was removed on {{ $model->deleted_at->format('j M Y') }}. Its trips, fuel and maintenance records are kept for history and reports.
    </div>
@endif
