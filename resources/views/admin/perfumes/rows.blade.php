@forelse ($perfumes as $perfume)
    <tr>
        <td data-label="Product">
            <span class="mini-thumb"><img src="{{ asset('storage/'.$perfume->image_path) }}" alt="{{ $perfume->name }}" loading="lazy"></span>
            {{ $perfume->name }}
        </td>
        <td data-label="Category">{{ $perfume->categoryLabel() }}</td>
        <td data-label="Price">${{ number_format($perfume->price, 2) }}</td>
        <td data-label="Actions">
            <div class="row-actions">
                <button type="button" class="btn btn-outline btn-sm edit-perfume"
                    data-record="{{ $perfume->id }}" data-update-url="{{ route('admin.perfumes.update', $perfume) }}"
                    data-name="{{ $perfume->name }}"
                    data-category="{{ $perfume->category }}" data-price="{{ $perfume->price }}"
                    data-description="{{ $perfume->description }}" data-specs="{{ json_encode($perfume->specs ?? (object) []) }}"
                    data-image="{{ asset('storage/'.$perfume->image_path) }}">Edit</button>
                <form method="POST" action="{{ route('admin.perfumes.destroy', $perfume) }}"
                    data-confirm="Delete {{ $perfume->name }}? This cannot be undone.">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="filter_category" value="{{ $selectedCategory }}">
                    <input type="hidden" name="filter_q" value="{{ $search }}">
                    <input type="hidden" name="filter_page" value="{{ $perfumes->currentPage() }}">
                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                </form>
            </div>
        </td>
    </tr>
@empty
    <tr><td colspan="4" class="admin-products-empty">No perfumes match your search and category.</td></tr>
@endforelse
