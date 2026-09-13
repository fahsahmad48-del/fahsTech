@extends('admin.layout')

@section('title', 'Categories')

@section('admin-content')
    <div id="adminMessage" class="note" role="status" aria-live="polite" hidden></div>
    <button type="button" id="retryCategories" class="btn btn-outline btn-sm" hidden>Reload category list</button>

    <h2>Categories</h2>

    <p style="color: var(--muted);">
        Add, rename, or remove product categories.
    </p>


    {{-- Add category --}}
    <form
        method="POST"
        action="{{ route('admin.categories.store') }}"
        class="add-inline" id="addCategoryForm"
    >
        @csrf

        <input
            type="text"
            name="name"
            placeholder="New category name"
            value="{{ old('_form') === 'category-edit' ? '' : old('name') }}"
            aria-label="New category name" maxlength="100"
            required
        >

        <button type="submit" class="btn btn-primary btn-sm">
            Add
        </button>
    </form>


    {{-- Categories --}}
    <div class="chip-manage" id="categoryRows"
        data-index-url="{{ route('admin.categories.index') }}"
        data-update-url-template="{{ route('admin.categories.update', ['category' => '__CATEGORY__']) }}"
        data-old-input="{{ json_encode(session()->getOldInput()) }}">

        @include('admin.categories.rows')

    </div>


    <p class="hint">
        Categories containing products cannot be deleted.
    </p>


    {{-- Category deletion warning --}}
    <dialog
        id="categoryDeleteWarning"
        class="category-delete-warning"
        aria-labelledby="categoryDeleteWarningTitle"
        aria-describedby="categoryDeleteWarningDescription categoryDeleteWarningHelp"
    >
        <form method="dialog">
            <button type="submit" class="category-delete-warning-close" aria-label="Close warning">
                &times;
            </button>

            <div class="category-delete-warning-icon" aria-hidden="true">
                <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M10.3 4.1 2.4 17.8A2 2 0 0 0 4.1 21h15.8a2 2 0 0 0 1.7-3.2L13.7 4.1a2 2 0 0 0-3.4 0Z" />
                    <path d="M12 9v4m0 4h.01" />
                </svg>
            </div>

            <h3 id="categoryDeleteWarningTitle">Can't delete category</h3>
            <p id="categoryDeleteWarningDescription">
                <strong id="categoryDeleteWarningName"></strong> still contains products.
            </p>
            <p id="categoryDeleteWarningHelp" class="category-delete-warning-help">
                Move its products to another category or delete them before removing this category.
            </p>

            <button type="submit" class="btn btn-primary btn-block" autofocus>Got it</button>
        </form>
    </dialog>


    {{-- Edit Modal --}}
    <dialog
        class="modal-overlay"
        id="categoryModal"
        aria-labelledby="categoryModalTitle"
    >

        <div class="modal" style="max-width: 420px;">

            <button
                type="button"
                class="modal-close"
                id="closeCategoryModal" aria-label="Close category form"
            >
                ×
            </button>

            <h3 id="categoryModalTitle">Edit Category</h3>

            <form method="POST" id="editCategoryForm">

                @csrf
                @method('PUT')
                <input type="hidden" name="_form" value="category-edit">
                <input type="hidden" name="_record" id="categoryRecord">
                @if ($errors->any() && old('_form') === 'category-edit')
                    <div class="note" role="alert">{{ $errors->first() }}</div>
                @endif

                <div class="field">

                    <label for="editCategoryName">
                        Category name
                    </label>

                    <input
                        type="text"
                        name="name"
                        id="editCategoryName" maxlength="100"
                        required
                    >

                </div>

                <div style="display: flex; justify-content: flex-end; gap: 10px;">

                    <button
                        type="button"
                        class="btn btn-outline"
                        id="cancelCategoryModal"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        class="btn btn-primary"
                    >
                        Save Changes
                    </button>

                </div>

            </form>

        </div>

    </dialog>

@endsection

@push('scripts')
    <script src="{{ asset('js/admin/common.js') }}" defer></script>
    <script src="{{ asset('js/admin/categories.js') }}" defer></script>
@endpush
