@extends('admin.layout')

@section('title', 'Products')

@section('admin-content')

    {{-- Add Product --}}
    <div style="display:flex; justify-content:flex-end; margin-bottom:16px;">
        <button type="button" class="btn btn-primary" id="addProductBtn">
            + Add Phone
        </button>
    </div>


    {{-- Products --}}
    <div class="table-wrap">

        <table class="admin-table">

            <thead>
                <tr>
                    <th>Phone</th>
                    <th>Brand</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>

                @forelse ($products as $product)

                    <tr>

                        {{-- Phone --}}
                        <td data-label="Phone">

                            @if ($product->image_path)
                                <span class="mini-thumb">
                                    <img
                                        src="{{ asset('storage/' . $product->image_path) }}"
                                        alt="{{ $product->name }}"
                                    >
                                </span>
                            @endif

                            {{ $product->name }}

                        </td>


                        {{-- Brand --}}
                        <td data-label="Brand">
                            {{ $product->brand }}
                        </td>


                        {{-- Category --}}
                        <td data-label="Category">
                            {{ $product->category?->name ?? '—' }}
                        </td>


                        {{-- Price --}}
                        <td data-label="Price">
                            ${{ number_format($product->price, 2) }}
                        </td>


                        {{-- Actions --}}
                        <td data-label="Actions">

                            <div class="row-actions">

                                <button
                                    type="button"
                                    class="btn btn-outline btn-sm edit-product"
                                    data-id="{{ $product->id }}"
                                    data-name="{{ $product->name }}"
                                    data-brand="{{ $product->brand }}"
                                    data-category="{{ $product->category_id }}"
                                    data-price="{{ $product->price }}"
                                    data-description="{{ $product->description }}"
                                    data-ram="{{ implode(', ', $product->ram ?? []) }}"
                                    data-storage="{{ implode(', ', $product->storage ?? []) }}"
                                    data-colors="{{ implode(', ', $product->colors ?? []) }}"
                                    data-screen="{{ $product->specs['Screen'] ?? $product->specs['screen'] ?? '' }}"
                                    data-processor="{{ $product->specs['Processor'] ?? $product->specs['processor'] ?? '' }}"
                                    data-battery="{{ $product->specs['Battery'] ?? $product->specs['battery'] ?? '' }}"
                                    data-camera="{{ $product->specs['Camera'] ?? $product->specs['camera'] ?? '' }}"
                                >
                                    Edit
                                </button>


                                <form
                                    method="POST"
                                    action="{{ route('admin.products.destroy', $product) }}"
                                    data-confirm="Delete {{ $product->name }}? This cannot be undone."
                                >
                                    @csrf
                                    @method('DELETE')

                                    <button
                                        type="submit"
                                        class="btn btn-danger btn-sm"
                                    >
                                        Delete
                                    </button>

                                </form>

                            </div>

                        </td>

                    </tr>

                @empty

                    <tr>
                        <td colspan="5" style="text-align:center; padding:30px;">
                            No phones added yet.
                        </td>
                    </tr>

                @endforelse

            </tbody>

        </table>

    </div>


    {{-- Pagination --}}
    <div style="margin-top:20px;">
        {{ $products->links('components.pagination') }}
    </div>


    {{-- ==========================================
         Product Modal
    =========================================== --}}

    <dialog
        class="modal-overlay"
        id="productModal"
        aria-labelledby="modalTitle"
    >

        <div class="modal">

            <button
                type="button"
                class="modal-close"
                id="closeModal" aria-label="Close product form"
            >
                ×
            </button>


            <h3 id="modalTitle">
                Add Phone
            </h3>


            <form
                method="POST"
                id="productForm"
                action="{{ url('/admin/products') }}"
                enctype="multipart/form-data"
            >

                @csrf

                <input type="hidden" name="_form" value="product">
                <input type="hidden" name="_record" id="productRecord">
                <div id="methodField"></div>
                @if ($errors->any() && old('_form') === 'product')
                    <div class="note" role="alert">
                        @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                        <div>Please select the image again if you were uploading one.</div>
                    </div>
                @endif


                {{-- Name --}}
                <div class="field">
                    <label for="name">Name</label>

                    <input
                        type="text"
                        name="name"
                        id="name" maxlength="120"
                        required
                    >
                </div>


                {{-- Brand + Category --}}
                <div class="two-col">

                    <div class="field">
                        <label for="brand">Brand</label>

                        <input
                            type="text"
                            name="brand"
                            id="brand" maxlength="60"
                            required
                        >
                    </div>


                    <div class="field">
                        <label for="category_id">Category</label>

                        <select
                            name="category_id"
                            id="category_id"
                            required
                        >

                            <option value="">
                                Select Category
                            </option>

                            @foreach ($categories as $category)
                                <option value="{{ $category->id }}">
                                    {{ $category->name }}
                                </option>
                            @endforeach

                        </select>
                    </div>

                </div>


                {{-- Price + Image --}}
                <div class="two-col">

                    <div class="field">
                        <label for="price">Price</label>

                        <input
                            type="number"
                            name="price"
                            id="price"
                            step="0.01"
                            min="0" max="99999999.99"
                            required
                        >
                    </div>


                    <div class="field">
                        <label for="image">Image</label>

                        <input
                            type="file"
                            name="image"
                            id="image"
                            accept="image/jpeg,image/png,image/webp"
                        >

                        <p class="hint" id="imageHint"></p>
                    </div>

                </div>


                {{-- RAM + Storage --}}
                <div class="two-col">

                    <div class="field">
                        <label for="ram">RAM</label>

                        <input
                            type="text"
                            name="ram"
                            id="ram" maxlength="500"
                            placeholder="8GB, 12GB, 16GB"
                        >
                    </div>


                    <div class="field">
                        <label for="storage">Storage</label>

                        <input
                            type="text"
                            name="storage"
                            id="storage" maxlength="500"
                            placeholder="128GB, 256GB, 512GB"
                        >
                    </div>

                </div>


                {{-- Colors --}}
                <div class="field">
                    <label for="colors">Colors</label>

                    <input
                        type="text"
                        name="colors"
                        id="colors" maxlength="500"
                        placeholder="Black, White, Blue"
                    >
                </div>


                {{-- Screen + Processor --}}
                <div class="two-col">

                    <div class="field">
                        <label for="screen">Screen</label>

                        <input
                            type="text"
                            name="screen"
                            id="screen" maxlength="255"
                        >
                    </div>


                    <div class="field">
                        <label for="processor">Processor</label>

                        <input
                            type="text"
                            name="processor"
                            id="processor" maxlength="255"
                        >
                    </div>

                </div>


                {{-- Battery + Camera --}}
                <div class="two-col">

                    <div class="field">
                        <label for="battery">Battery</label>

                        <input
                            type="text"
                            name="battery"
                            id="battery" maxlength="255"
                        >
                    </div>


                    <div class="field">
                        <label for="camera">Camera</label>

                        <input
                            type="text"
                            name="camera"
                            id="camera" maxlength="255"
                        >
                    </div>

                </div>


                {{-- Description --}}
                <div class="field">

                    <label for="description">Description</label>

                    <textarea
                        name="description"
                        id="description" maxlength="5000"
                    ></textarea>

                </div>


                {{-- Buttons --}}
                <div
                    style="
                        display:flex;
                        justify-content:flex-end;
                        gap:10px;
                        margin-top:18px;
                    "
                >

                    <button
                        type="button"
                        class="btn btn-outline"
                        id="cancelModal"
                    >
                        Cancel
                    </button>


                    <button
                        type="submit"
                        class="btn btn-primary"
                        id="submitButton"
                    >
                        Add Phone
                    </button>

                </div>

            </form>

        </div>

    </dialog>

@endsection


@push('scripts')

<script>
    document.addEventListener("DOMContentLoaded", () => {

        const modal = document.getElementById("productModal");
        const form = document.getElementById("productForm");
        const methodField = document.getElementById("methodField");

        const modalTitle = document.getElementById("modalTitle");
        const submitButton = document.getElementById("submitButton");
        const image = document.getElementById("image");
        const imageHint = document.getElementById("imageHint");


        function openModal() {
            modal.showModal();
        }


        function closeModal() {
            modal.close();
        }


        function clearForm() {
            form.reset();
            methodField.innerHTML = "";
            imageHint.textContent = "JPEG, PNG or WebP, up to 8 MB and 4096 × 4096 pixels.";
        }


        // =========================
        // Add Product
        // =========================

        document
            .getElementById("addProductBtn")
            .addEventListener("click", () => {

                clearForm();

                form.action = "{{ url('/admin/products') }}";

                modalTitle.textContent = "Add Phone";
                submitButton.textContent = "Add Phone";

                image.required = true;

                openModal();
            });


        // =========================
        // Edit Product
        // =========================

        document.querySelectorAll(".edit-product")
            .forEach(button => {

                button.addEventListener("click", () => {

                    clearForm();

                    const id = button.dataset.id;
                    document.getElementById("productRecord").value = id;

                    form.action =
                        "{{ url('/admin/products') }}/" + id;

                    methodField.innerHTML =
                        '<input type="hidden" name="_method" value="PUT">';


                    document.getElementById("name").value =
                        button.dataset.name;

                    document.getElementById("brand").value =
                        button.dataset.brand;

                    document.getElementById("category_id").value =
                        button.dataset.category;

                    document.getElementById("price").value =
                        button.dataset.price;

                    document.getElementById("description").value =
                        button.dataset.description;

                    document.getElementById("ram").value =
                        button.dataset.ram;

                    document.getElementById("storage").value =
                        button.dataset.storage;

                    document.getElementById("colors").value =
                        button.dataset.colors;

                    document.getElementById("screen").value =
                        button.dataset.screen;

                    document.getElementById("processor").value =
                        button.dataset.processor;

                    document.getElementById("battery").value =
                        button.dataset.battery;

                    document.getElementById("camera").value =
                        button.dataset.camera;


                    modalTitle.textContent = "Edit Phone";
                    submitButton.textContent = "Save Changes";

                    image.required = false;

                    imageHint.textContent =
                        "Leave empty to keep the current image.";

                    openModal();
                });

            });


        // =========================
        // Close Modal
        // =========================

        document
            .getElementById("closeModal")
            .addEventListener("click", closeModal);


        document
            .getElementById("cancelModal")
            .addEventListener("click", closeModal);


        modal.addEventListener("click", event => {

            if (event.target === modal) {
                closeModal();
            }

        });


        const previous = {{ Illuminate\Support\Js::from(session()->getOldInput()) }};
        if (previous._form === 'product') {
            const editing = /^\d+$/.test(previous._record ?? '');
            form.action = "{{ url('/admin/products') }}" + (editing ? '/' + previous._record : '');
            methodField.innerHTML = editing ? '<input type="hidden" name="_method" value="PUT">' : '';
            for (const key of ['name', 'brand', 'category_id', 'price', 'description', 'ram', 'storage', 'colors', 'screen', 'processor', 'battery', 'camera']) {
                form.elements.namedItem(key).value = previous[key] ?? '';
            }
            document.getElementById('productRecord').value = previous._record ?? '';
            modalTitle.textContent = editing ? 'Edit Phone' : 'Add Phone';
            submitButton.textContent = editing ? 'Save Changes' : 'Add Phone';
            image.required = !editing;
            imageHint.textContent = editing ? 'Leave empty to keep the current image.' : 'JPEG, PNG or WebP, up to 8 MB and 4096 × 4096 pixels.';
            openModal();
        }

    });
</script>

@endpush
