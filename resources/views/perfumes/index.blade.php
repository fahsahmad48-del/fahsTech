<x-layout>
    <div class="container perfume-page" id="perfumeCatalog"
        data-filter-url="{{ route('perfumes.filter') }}" data-index-url="{{ route('perfumes.index') }}"
        data-page="{{ $perfumes->currentPage() }}">
        <h1>Perfumes</h1>
        <p class="product-desc">Find your signature scent.</p>
        <form class="perfume-search-panel" data-search-form action="{{ route('perfumes.index') }}" method="GET" role="search" aria-labelledby="perfumeSearchTitle">
            <svg class="perfume-search-decoration" viewBox="0 0 120 60" fill="none" aria-hidden="true" focusable="false"><path d="m84 8 3 9 9 3-9 3-3 9-3-9-9-3 9-3ZM30 30l2 6 6 2-6 2-2 6-2-6-6-2 6-2Z" fill="currentColor"/><path d="M44 56c0-16 12-28 28-28" stroke="currentColor" stroke-width="1.5"/></svg>
            <div class="perfume-search-header">
                <span class="perfume-search-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M9 3h6v4H9zM8 7h8v3H8zM8 10h8a3 3 0 0 1 3 3v6a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2v-6a3 3 0 0 1 3-3Z"/><path d="M9 14h6v3H9z"/></svg>
                </span>
                <div class="perfume-search-copy">
                    <h2 id="perfumeSearchTitle">Find Your Scent</h2>
                    <p>Search by name and filter by collection.</p>
                </div>
            </div>
            <div class="perfume-search-row">
                <div class="perfume-search-field">
                    <label for="perfumeSearch">Perfume name</label>
                    <div class="perfume-search-input-wrap">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true"><circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 4 4"/></svg>
                        <input class="perfume-search-input" id="perfumeSearch" data-search-input type="search" name="q" value="{{ $search }}" maxlength="120" placeholder="Search perfumes by name..." autocomplete="off">
                    </div>
                </div>
                <div class="perfume-filter-field">
                    <label for="perfumeCategoryFilter">Collection</label>
                    <select class="perfume-filter-select" id="perfumeCategoryFilter" name="category" data-category-select>
                        <option value="">All Categories</option>
                        @foreach (\App\Models\Perfume::CATEGORIES as $value => $label)
                            <option value="{{ $value }}" @selected($selectedCategory === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <noscript><button class="btn btn-primary" type="submit">Find perfumes</button></noscript>
        </form>
        <div class="perfume-results-meta">@include('perfumes.feedback')</div>
        <div data-results aria-busy="false">
            <div class="grid" data-items>@include('perfumes.cards')</div>
            <div data-pagination>{{ $perfumes->links('components.pagination', ['label' => 'Perfume pages']) }}</div>
        </div>
    </div>
    <script src="{{ asset('js/perfumes.js') }}" defer></script>
</x-layout>
