@if ($paginator->hasPages())
    <nav class="flex items-center justify-between gap-3 border-t border-line px-4 py-3 text-sm" aria-label="Pagination">
        <p class="text-muted">
            Showing {{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} of {{ $paginator->total() }}
        </p>
        <div class="flex items-center gap-1">
            @if ($paginator->onFirstPage())
                <span class="btn btn-secondary btn-sm opacity-40" aria-disabled="true"><x-icon name="chevron-left" size="16" /> Previous</span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}" class="btn btn-secondary btn-sm" rel="prev"><x-icon name="chevron-left" size="16" /> Previous</a>
            @endif

            <span class="hidden px-2 text-muted sm:inline">Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>

            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}" class="btn btn-secondary btn-sm" rel="next">Next <x-icon name="chevron-right" size="16" /></a>
            @else
                <span class="btn btn-secondary btn-sm opacity-40" aria-disabled="true">Next <x-icon name="chevron-right" size="16" /></span>
            @endif
        </div>
    </nav>
@endif
