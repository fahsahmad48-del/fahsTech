<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class CategoryController extends Controller
{
    // Show categories
    public function index(Request $request)
    {
        $categories = Category::withCount('products')
            ->orderBy('name')
            ->get();

        if ($request->expectsJson()) {
            return response()->json(['html' => view('admin.categories.rows', compact('categories'))->render()]);
        }

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

        return $this->respond($request, 'Category added successfully.', 201);
    }

    // Edit category
    public function update(Request $request, Category $category)
    {
        $validated = $request->validate($this->rules($category));

        $category->update([
            'name' => trim($validated['name']),
        ]);

        $this->clearCache();

        return $this->respond($request, 'Category updated successfully.');
    }

    // Delete category
    public function destroy(Request $request, Category $category)
    {
        if ($category->products()->exists()) {
            throw ValidationException::withMessages([
                'category' => 'This category contains products and cannot be deleted. Move or delete its products first.',
            ]);
        }

        $category->delete();

        $this->clearCache();

        return $this->respond($request, 'Category deleted successfully.');
    }

    private function respond(Request $request, string $message, int $status = 200)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], $status);
        }

        return back()->with('success', $message);
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
