<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SearchController extends Controller
{
    /** Find products by name or brand, and by category if one is chosen. */
    public function searchProducts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);

        // Choose which product fields we need. The products are fetched later by paginate().
        $query = Product::query()
            ->select([
                'id',
                'name',
                'brand',
                'slug',
                'category_id',
                'price',
                'image_path',
                'created_at',
            ])
            // Get the category names too, without fetching each one separately.
            // The IDs tell Laravel which category belongs to each product.
            ->with('category:id,name');

        // Only ask for product_type if that column exists, so older databases still work.
        if (Schema::hasColumn('products', 'product_type')) {
            $query->addSelect('product_type');
        }

        $search = trim($validated['q'] ?? '');
        if ($search !== '') {
            // Make %, _, and ! count as typed characters, not special search instructions.
            // For example, searching for "50%" should look for an actual percent sign.
            $search = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search);

            // Look for the search text in either the name or the brand.
            // Keep these checks together so the category filter applies to both.
            // LOWER treats "Samsung" and "samsung" alike. % lets the text match part of a name.
            // The ? is a safe place for Laravel to put the search text.
            $query->where(function ($q) use ($search) {
                $q->whereRaw("LOWER(name) LIKE LOWER(?) ESCAPE '!'", ["%{$search}%"])
                    ->orWhereRaw("LOWER(brand) LIKE LOWER(?) ESCAPE '!'", ["%{$search}%"]);
            });
        }

        // If a category was chosen, show only products in that category.
        if ($request->filled('category')) {
            $query->where(
                'category_id',
                $request->integer('category')
            );
        }

        // Get eight products per page, newest first. Use the ID to order products added together.
        // withQueryString() keeps the search text and category when changing pages.
        $products = $query
            ->latest()
            ->orderByDesc('id')
            ->paginate(8)
            ->withQueryString();

        // Send the products, page information, and categories as data JavaScript can read.
        return response()->json([
            'products' => $products,
            'categories' => $this->categories(),
        ]);
    }

    /** Show the search page and its category choices. */
    public function searchPage()
    {
        return view('search', ['categories' => $this->categories()]); // categories() is a private method below.
    }

    /** Get categories from A to Z and keep a saved copy for up to an hour. */
    private function categories(): array
    {
        // Use the saved copy if it exists. Otherwise, get the categories and save a copy.
        // Adding, editing, or deleting a category removes that old copy.
        return Cache::remember(
            'search.categories',
            now()->addHour(),
            function () {
                return Category::orderBy('name')
                    ->get(['id', 'name'])
                    ->toArray();
            }
        );
    }
}
