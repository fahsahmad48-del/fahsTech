<x-layout>
    <div class="container" style="padding-top:30px;" id="phoneSearch"
        data-endpoint="{{ route('search.products') }}"
        data-product-url="{{ url('/products') }}"
        data-image-url="{{ asset('storage') }}">
        <h1 style="margin-bottom:18px;">Browse phones</h1>

        <div class="search-bar reveal reveal-left">
            <input type="text" id="searchInput" maxlength="120"
                aria-label="Search by name or brand" placeholder="Search by name or brand...">
            <select id="categorySelect" aria-label="Filter by category">
                <option value="">All categories</option>
                @foreach ($categories as $category)
                    <option value="{{ $category['id'] }}">{{ $category['name'] }}</option>
                @endforeach
            </select>
        </div>

        <div id="searchLoading" class="search-loading" role="status" hidden>
            <span class="loading-spinner" aria-hidden="true"></span>
            <span>Loading phones...</span>
        </div>
        <p class="result-count" id="resultCount" role="status" aria-live="polite"></p>
        <div class="grid" id="resultsGrid" aria-busy="true"></div>
        <nav id="searchPagination" class="pagination" aria-label="Search pages" hidden></nav>
        <noscript><p>Please enable JavaScript to search the phone catalog.</p></noscript>
    </div>
    <script src="{{ asset('js/search.js') }}" defer></script>
</x-layout>
