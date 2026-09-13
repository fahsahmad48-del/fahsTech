@extends('admin.layout')
@section('title', 'Perfumes')
@section('admin-content')
    <div id="adminMessage" class="note" role="status" aria-live="polite" hidden></div>
    <div id="perfumeCatalog" data-filter-url="{{ route('admin.perfumes.filter') }}"
        data-index-url="{{ route('admin.perfumes.index') }}" data-page="{{ $perfumes->currentPage() }}">
        <div class="admin-products-toolbar">
            <h2>Perfumes</h2>
            <button type="button" class="btn btn-primary" id="addPerfume">+ Add Perfume</button>
        </div>
        <div class="field admin-category-filter">
            <label for="perfumeCategoryFilter">Category:</label>
            <select id="perfumeCategoryFilter" data-category-select>
                <option value="">All Categories</option>
                @foreach (\App\Models\Perfume::CATEGORIES as $value => $label)
                    <option value="{{ $value }}" @selected($selectedCategory === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        @include('perfumes.feedback')
        <div data-results aria-busy="false">
            <div class="table-wrap">
                <table class="admin-table">
                    <thead><tr><th>Perfume</th><th>Brand</th><th>Category</th><th>Price</th><th>Actions</th></tr></thead>
                    <tbody data-items>@include('admin.perfumes.rows')</tbody>
                </table>
            </div>
            <div data-pagination>{{ $perfumes->links('components.pagination', ['label' => 'Perfume pages']) }}</div>
        </div>
    </div>
    @include('admin.perfumes.modal')
@endsection

@push('scripts')
    <script src="{{ asset('js/admin/common.js') }}" defer></script>
    <script src="{{ asset('js/admin/perfumes.js') }}" defer></script>
@endpush
