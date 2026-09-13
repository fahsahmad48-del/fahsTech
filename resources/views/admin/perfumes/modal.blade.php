<dialog class="modal-overlay" id="perfumeModal" aria-labelledby="perfumeModalTitle"
    data-store-url="{{ route('admin.perfumes.store') }}"
    data-old-input="{{ json_encode(session()->getOldInput()) }}"
    data-old-image="{{ $editingPerfume ? asset('storage/'.$editingPerfume->image_path) : '' }}">
    <div class="modal">
        <button type="button" class="modal-close" data-close-perfume aria-label="Close perfume form">&times;</button>
        <h3 id="perfumeModalTitle">Add Perfume</h3>
        <form method="POST" id="perfumeForm" action="{{ route('admin.perfumes.store') }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="_form" value="perfume">
            <input type="hidden" name="_record">
            <input type="hidden" name="filter_category" value="{{ $selectedCategory }}">
            <input type="hidden" name="filter_page" value="{{ $perfumes->currentPage() }}">
            <div id="perfumeMethod"></div>
            @if ($errors->any() && old('_form') === 'perfume')
                <div class="note" role="alert" id="perfumeValidationErrors">
                    @foreach ($errors->all() as $error)<div>{{ $error }}</div>@endforeach
                    <div>Please select the image again if you were uploading one.</div>
                </div>
            @endif
            <div class="field">
                <label for="perfumeName">Name *</label>
                <input type="text" name="name" id="perfumeName" maxlength="120" required>
            </div>
            <div class="two-col">
                <div class="field">
                    <label for="perfumeBrand">Brand *</label>
                    <input type="text" name="brand" id="perfumeBrand" maxlength="80" required>
                </div>
                <div class="field">
                    <label for="perfumeCategory">Category *</label>
                    <select name="category" id="perfumeCategory" required>
                        @foreach (\App\Models\Perfume::CATEGORIES as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="two-col">
                <div class="field">
                    <label for="perfumePrice">Price *</label>
                    <input type="number" name="price" id="perfumePrice" min="0" max="99999999.99" step="0.01" required>
                </div>
                <div class="field">
                    <label for="perfumeImage" id="perfumeImageLabel">Image *</label>
                    <input type="file" name="image" id="perfumeImage" accept="image/jpeg,image/png,image/webp" required>
                    <p class="hint" id="perfumeImageHint"></p>
                    <img id="perfumeImagePreview" class="device-image-preview" alt="Current perfume image" hidden>
                </div>
            </div>
            <div class="field">
                <label for="perfumeDescription">Description</label>
                <textarea name="description" id="perfumeDescription" maxlength="5000"></textarea>
            </div>
            <fieldset class="device-specifications">
                <legend>Specifications</legend>
                <p class="hint">Add up to 30 specifications. Each name must be unique.</p>
                <div id="perfumeSpecRows"></div>
                <button type="button" class="btn btn-outline btn-sm" id="addPerfumeSpecification">+ Add Specification</button>
            </fieldset>
            <div class="device-modal-actions">
                <button type="button" class="btn btn-outline" data-close-perfume>Cancel</button>
                <button type="submit" class="btn btn-primary" id="perfumeSubmit">Add Perfume</button>
            </div>
        </form>
    </div>
</dialog>
<template id="perfumeSpecTemplate">
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
