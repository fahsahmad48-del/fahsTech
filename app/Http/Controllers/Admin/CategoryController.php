<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CategoryController extends Controller
{
    // Show categories
    public function index()
    {
        $categories = Category::withCount('products')
            ->orderBy('name')
            ->get();

        return view('admin.categories.index', compact('categories'));
    }

    // Add category
    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        Category::create([
            'name' => trim($validated['name']),
        ]);

        $this->clearCache();

        return back()->with('success', 'Category added successfully.');
    }

    // Edit category
    public function update(Request $request, Category $category)
    {
        $validated = $request->validate($this->rules($category));

        $category->update([
            'name' => trim($validated['name']),
        ]);

        $this->clearCache();

        return back()->with('success', 'Category updated successfully.');
    }

    // Delete category
    public function destroy(Category $category)
    {
        if ($category->products()->exists()) {
            return back()->withErrors([
                'category' => 'This category contains products and cannot be deleted. Move or delete its products first.',
            ]);
        }

        $category->delete();

        $this->clearCache();

        return back()->with('success', 'Category deleted successfully.');
    }

    private function clearCache(): void
    {
        Cache::forget('home.categories');
        Cache::forget('search.categories');
        Cache::forget('home.products.latest');
    }

    private function rules(?Category $category = null): array
    {
        return [
            'name' => ['bail', 'required', 'string', 'max:100', function ($attribute, $value, $fail) use ($category) {
                $duplicate = Category::whereRaw('LOWER(name) = LOWER(?)', [trim($value)])
                    ->when($category, fn ($query) => $query->where('id', '!=', $category->id))
                    ->exists();

                if ($duplicate) {
                    $fail('A category with this name already exists.');
                }
            }],
        ];
    }
}
