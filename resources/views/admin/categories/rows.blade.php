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
