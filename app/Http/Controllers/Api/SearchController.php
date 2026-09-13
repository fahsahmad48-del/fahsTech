<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SearchController extends Controller
{
    public function searchProducts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);

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
            ->with('category:id,name');

        // Keep public browsing available while the product type migration is pending.
        if (Schema::hasColumn('products', 'product_type')) {
            $query->addSelect('product_type');
        }

        $search = trim($validated['q'] ?? '');
        if ($search !== '') {
            // Use an explicit escape character so % and _ are searched literally.
            $search = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search);

            $query->where(function ($q) use ($search) {
                $q->whereRaw("LOWER(name) LIKE LOWER(?) ESCAPE '!'", ["%{$search}%"])
                    ->orWhereRaw("LOWER(brand) LIKE LOWER(?) ESCAPE '!'", ["%{$search}%"]);
            });
        }

        if ($request->filled('category')) {
            $query->where(
                'category_id',
                $request->integer('category')
            );
        }

        $products = $query
            ->latest()
            ->orderByDesc('id')
            ->paginate(8)
            ->withQueryString();

        return response()->json([
            'products' => $products,
            'categories' => $this->categories(),
        ]);
    }

    public function searchPage()
    {
        return view('search', ['categories' => $this->categories()]);
    }

    private function categories(): array
    {
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
