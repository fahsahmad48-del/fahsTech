@extends('admin.layout')

@section('title', 'Categories')

@section('admin-content')

    <h2>Categories</h2>

    <p style="color: var(--muted);">
        Add, rename, or remove product categories.
    </p>


    {{-- Add category --}}
    <form
        method="POST"
        action="{{ route('admin.categories.store') }}"
        class="add-inline"
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
    <div class="chip-manage">

        @forelse ($categories as $category)

            <div class="chip">

                <span>
                    {{ $category->name }}
                    ({{ $category->products_count }})
                </span>


                {{-- Edit --}}
                <button
                    type="button"
                    class="edit-category"
                    data-id="{{ $category->id }}"
                    data-name="{{ $category->name }}"
                    title="Edit"
                >
                    ✎
                </button>


                {{-- Delete --}}
                <form
                    method="POST"
                    action="{{ route('admin.categories.destroy', $category) }}"
                    @if ($category->products_count > 0)
                        class="category-delete-blocked"
                        data-category-name="{{ $category->name }}"
                    @else
                        data-confirm="Delete {{ $category->name }}? This cannot be undone."
                    @endif
                >
                    @csrf
                    @method('DELETE')

                    <button
                        type="submit"
                        title="Delete"
                    >
                        ×
                    </button>

                </form>

            </div>

        @empty

            <p style="color: var(--muted);">
                No categories added yet.
            </p>

        @endforelse

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

<script>
    document.addEventListener("DOMContentLoaded", () => {

        const modal = document.getElementById("categoryModal");
        const form = document.getElementById("editCategoryForm");
        const nameInput = document.getElementById("editCategoryName");
        const deleteWarning = document.getElementById("categoryDeleteWarning");
        const deleteWarningName = document.getElementById("categoryDeleteWarningName");

        document.querySelectorAll(".category-delete-blocked").forEach(deleteForm => {
            deleteForm.addEventListener("submit", event => {
                event.preventDefault();
                deleteWarningName.textContent = deleteForm.dataset.categoryName;
                deleteWarning.showModal();
            });
        });

        deleteWarning.addEventListener("click", event => {
            const bounds = deleteWarning.getBoundingClientRect();
            if (event.target === deleteWarning && (
                event.clientX < bounds.left || event.clientX > bounds.right ||
                event.clientY < bounds.top || event.clientY > bounds.bottom
            )) {
                deleteWarning.close();
            }
        });

        function closeModal() {
            modal.close();
        }

        document.querySelectorAll(".edit-category").forEach(button => {

            button.addEventListener("click", () => {

                nameInput.value = button.dataset.name;
                document.getElementById("categoryRecord").value = button.dataset.id;

                form.action =
                    "{{ url('/admin/categories') }}/" + button.dataset.id;

                modal.showModal();
            });

        });

        document
            .getElementById("closeCategoryModal")
            .addEventListener("click", closeModal);

        document
            .getElementById("cancelCategoryModal")
            .addEventListener("click", closeModal);

        modal.addEventListener("click", event => {
            if (event.target === modal) {
                closeModal();
            }
        });


        const previous = {{ Illuminate\Support\Js::from(session()->getOldInput()) }};
        if (previous._form === 'category-edit' && /^\d+$/.test(previous._record ?? '')) {
            nameInput.value = previous.name ?? '';
            document.getElementById('categoryRecord').value = previous._record;
            form.action = "{{ url('/admin/categories') }}/" + previous._record;
            modal.showModal();
        }
    });
</script>

@endpush
