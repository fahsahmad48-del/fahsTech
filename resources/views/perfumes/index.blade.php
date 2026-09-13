<x-layout>
    <div class="container perfume-page" id="perfumeCatalog"
        data-filter-url="{{ route('perfumes.filter') }}" data-index-url="{{ route('perfumes.index') }}"
        data-page="{{ $perfumes->currentPage() }}">
        <h1>Perfumes</h1>
        <p class="product-desc">Find your signature scent.</p>
        <nav class="perfume-filters" aria-label="Perfume categories">
            @foreach (['' => 'All'] + \App\Models\Perfume::CATEGORIES as $value => $label)
                <a href="{{ route('perfumes.index', $value ? ['category' => $value] : []) }}"
                    data-category="{{ $value }}" class="btn {{ $selectedCategory === $value || (!$selectedCategory && !$value) ? 'btn-primary' : 'btn-outline' }}"
                    @if ($selectedCategory === $value || (!$selectedCategory && !$value)) aria-current="true" @endif>{{ $label }}</a>
            @endforeach
        </nav>
        @include('perfumes.feedback')
        <div data-results aria-busy="false">
            <div class="grid" data-items>@include('perfumes.cards')</div>
            <div data-pagination>{{ $perfumes->links('components.pagination', ['label' => 'Perfume pages']) }}</div>
        </div>
    </div>
    <script src="{{ asset('js/perfumes.js') }}" defer></script>
</x-layout>
