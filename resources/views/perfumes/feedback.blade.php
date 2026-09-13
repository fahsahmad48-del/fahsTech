<div data-loading class="search-loading" role="status" hidden>
    <span class="loading-spinner" aria-hidden="true"></span> Loading perfumes...
</div>
<div data-error class="note" role="alert" hidden>
    <span></span>
    <button type="button" class="btn btn-outline btn-sm" data-retry>Try again</button>
</div>
<p data-count class="result-count" role="status" aria-live="polite">{{ $perfumes->total() }} {{ $perfumes->total() === 1 ? 'perfume' : 'perfumes' }} found</p>
