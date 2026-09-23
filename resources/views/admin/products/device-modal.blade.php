<dialog class="modal-overlay" id="deviceModal" aria-labelledby="deviceModalTitle">
    <div class="modal">
        <button type="button" class="modal-close" id="closeDeviceModal" aria-label="Close device form">&times;</button>
        <h3 id="deviceModalTitle">Add Device</h3>
        <form method="POST" id="deviceForm" action="{{ route('admin.products.devices.store') }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="_form" value="device">
            <input type="hidden" name="_record">
            <input type="hidden" name="filter_category" value="{{ $selectedCategory }}">
            <input type="hidden" name="filter_page" value="{{ $products->currentPage() }}">
            <div id="deviceMethodField"></div>
            @if ($errors->any() && old('_form') === 'device')
                <div class="note" role="alert">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                    <div>Please select the image again if you were uploading one.</div>
                </div>
            @endif
            <div class="field">
                <label for="deviceName">Name *</label>
                <input type="text" name="name" id="deviceName" maxlength="120" required>
            </div>
            <div class="two-col">
                <div class="field">
                    <label for="deviceBrand">Brand *</label>
                    <input type="text" name="brand" id="deviceBrand" maxlength="60" required>
                </div>
                <div class="field">
                    <label for="deviceCategory">Category *</label>
                    <select name="category_id" id="deviceCategory" data-searchable-category required>
                        <option value="">Select Category</option>
                        @foreach ($categories as $category)
                            <option value="{{ $category->id }}">{{ $category->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="two-col">
                <div class="field">
                    <label for="devicePrice">Price *</label>
                    <input type="number" name="price" id="devicePrice" step="0.01" min="0" max="99999999.99" required>
                </div>
                <div class="field">
                    <label for="deviceImage" id="deviceImageLabel">Image *</label>
                    <input type="file" name="image" id="deviceImage" accept="image/jpeg,image/png,image/webp" required>
                    <p class="hint" id="deviceImageHint"></p>
                    <img id="deviceImagePreview" class="device-image-preview" alt="Current product image" hidden>
                </div>
            </div>
            <div class="field">
                <label for="deviceColors">Colors</label>
                <input type="text" name="colors" id="deviceColors" maxlength="500" placeholder="Black, White, Blue">
            </div>
            <fieldset class="device-specifications">
                <legend>Specifications</legend>
                <p class="hint">Add up to 30 specifications. Each name must be unique.</p>
                <div id="deviceSpecRows"></div>
                <button type="button" class="btn btn-outline btn-sm" id="addSpecification">+ Add Specification</button>
            </fieldset>
            <div class="field">
                <label for="deviceDescription">Description</label>
                <textarea name="description" id="deviceDescription" maxlength="5000"></textarea>
            </div>
            <div class="device-modal-actions">
                <button type="button" class="btn btn-outline" id="cancelDeviceModal">Cancel</button>
                <button type="submit" class="btn btn-primary" id="deviceSubmitButton">Add Device</button>
            </div>
        </form>
    </div>
</dialog>

<template id="deviceSpecTemplate">
    <div class="device-spec-row">
        <div class="field">
            <label data-spec-label="name">Specification</label>
            <input type="text" data-spec-input="name" maxlength="60" placeholder="Specification">
        </div>
        <div class="field">
            <label data-spec-label="value">Value</label>
            <input type="text" data-spec-input="value" maxlength="255" placeholder="Value">
        </div>
        <button type="button" class="btn btn-outline btn-sm remove-specification">Remove</button>
    </div>
</template>
