@extends('admin.layout')

@section('title', 'Products')

@section('admin-content')
    <div id="adminMessage" class="note" role="status" aria-live="polite" hidden></div>
    <div id="adminProducts"
        data-filter-url="{{ route('admin.products.filter') }}"
        data-index-url="{{ route('admin.products.index') }}"
        data-phone-store-url="{{ route('admin.products.store') }}"
        data-device-store-url="{{ route('admin.products.devices.store') }}"
        data-page="{{ $products->currentPage() }}"
        data-old-input="{{ json_encode(session()->getOldInput()) }}">
        <div class="admin-products-toolbar">
            <div class="field admin-category-filter">
                <label for="adminCategory">Category:</label>
                <select id="adminCategory" data-searchable-category>
                    <option value="">All Categories</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}" @selected((string) $selectedCategory === (string) $category->id)>{{ $category->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="admin-product-buttons">
                <button type="button" class="btn btn-primary" id="addProductBtn">+ Add Phone</button>
                <button type="button" class="btn btn-outline" id="addDeviceBtn">+ Add Device</button>
            </div>
        </div>
        <div id="adminProductsLoading" class="search-loading" role="status" hidden>
            <span class="loading-spinner" aria-hidden="true"></span> Loading products...
        </div>
        <div id="adminProductsError" class="note" role="alert" hidden>
            <span></span>
            <button type="button" class="btn btn-outline btn-sm" id="retryProducts">Try again</button>
        </div>
        <p id="adminProductsCount" role="status" aria-live="polite">{{ $products->total() }} {{ $products->total() === 1 ? 'product' : 'products' }} found</p>
        <div id="adminProductsResults" aria-busy="false">
            <div class="table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr><th>Product</th><th>Brand</th><th>Category</th><th>Price</th><th>Actions</th></tr>
                    </thead>
                    <tbody id="adminProductRows">
                        @include('admin.products.rows')
                    </tbody>
                </table>
            </div>
            <div id="adminProductsPagination">
                {{ $products->links('components.pagination') }}
            </div>
        </div>
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
                <input type="hidden" name="filter_category" value="{{ $selectedCategory }}">
                <input type="hidden" name="filter_page" value="{{ $products->currentPage() }}">
                <div id="methodField"></div>
                @if ($errors->any() && old('_form') === 'product')
                    <div class="note" role="alert">
                        @foreach ($errors->all() as $error)<div>{{ $error }}</div> @endforeach
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
                            data-searchable-category
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

    @include('admin.products.device-modal')
@endsection

@push('scripts')
    <script src="{{ asset('js/admin/common.js') }}" defer></script>
    <script src="{{ asset('js/admin/products.js') }}" defer></script>
@endpush
