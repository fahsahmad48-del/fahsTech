@if ($paginator->hasPages())
    <nav class="pagination" aria-label="{{ $label ?? 'Product pages' }}">
        @if ($paginator->onFirstPage())
            <span class="btn btn-outline" aria-disabled="true">Previous</span>
        @else
            <a class="btn btn-outline" href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>
        @endif
        <span>Page {{ $paginator->currentPage() }} of {{ $paginator->lastPage() }}</span>
        @if ($paginator->hasMorePages())
            <a class="btn btn-outline" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
        @else
            <span class="btn btn-outline" aria-disabled="true">Next</span>
        @endif
    </nav>
@endif
