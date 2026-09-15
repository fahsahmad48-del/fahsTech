                @forelse ($products as $product)
                    <tr>
                        {{-- Product --}}
                        <td data-label="Product">

                            @if ($product->image_path)
                                <span class="mini-thumb">
                                    <img
                                        src="{{ asset('storage/' . $product->image_path) }}"
                                        alt="{{ $product->name }}"
                                    >
                                </span>
                            @endif

                            {{ $product->name }}
                            <span class="product-type-badge">{{ $product->typeLabel() }}</span>
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
                                    data-type="{{ $product->product_type }}"
                                    data-update-url="{{ route('admin.products.update', $product) }}"
                                    data-specs="{{ json_encode($product->specs ?? (object) []) }}"
                                    data-image="{{ $product->image_path ? asset('storage/' . $product->image_path) : '' }}"
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
                                    <input type="hidden" name="filter_category" value="{{ $selectedCategory }}">
                                    <input type="hidden" name="filter_page" value="{{ $products->currentPage() }}">

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
                        <td colspan="5" class="admin-products-empty">
                            {{ $selectedCategory ? 'No products found in this category.' : 'No products added yet.' }}
                        </td>
                    </tr>

                @endforelse
