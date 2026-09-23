<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class CategoryController extends Controller
{
    /** Show categories from A to Z and count their products. */
    public function index(Request $request)
    {
        $categories = Category::withCount('products') // Get the count of products in each category
            ->orderBy('name')
            ->get();

        // If JavaScript asks for an update, send only the table rows.
        // Otherwise, show the whole page.
        if ($request->expectsJson()) {
            return response()->json(['html' => view('admin.categories.rows', compact('categories'))->render()]);
        }

        return view('admin.categories.index', compact('categories'));
    }

    /** Check the category name and save the new category. */
    public function store(Request $request)
    {
        $validated = $request->validate($this->rules());

        // trim() removes spaces at the start and end: " Phones " becomes "Phones".
        Category::create([
            'name' => trim($validated['name']),
        ]);

        $this->clearCache();

        // 201 means the category was created successfully.
        return $this->respond($request, 'Category added successfully.', 201);
    }

    /** Change this category's name. Keeping its current name is allowed. */
    public function update(Request $request, Category $category)
    {
        $validated = $request->validate($this->rules($category));

        $category->update([
            'name' => trim($validated['name']),
        ]);

        $this->clearCache();

        return $this->respond($request, 'Category updated successfully.');
    }

    /** Delete the category only if it has no products. */
    public function destroy(Request $request, Category $category)
    {
        // Check if this category has any products.
        // If it does, stop and show the error below.
        if ($category->products()->exists()) {
            throw ValidationException::withMessages([
                'category' => 'This category contains products and cannot be deleted. Move or delete its products first.',
            ]);
        }

        $category->delete();

        $this->clearCache();

        return $this->respond($request, 'Category deleted successfully.');
    }

    /** Send the success message to JavaScript, or show it after going back to the page. */
    private function respond(Request $request, string $message, int $status = 200)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], $status);
        }

        return back()->with('success', $message);
    }

    /** Remove saved copies of old data so the pages can show the latest categories. */
    private function clearCache(): void
    {
        Cache::forget('home.categories');
        Cache::forget('search.categories');
        Cache::forget('home.products.latest');
    }

    /** Set the rules for a category name, including checking if the name is already used. */
    private function rules(?Category $category = null): array
    {
        // use ($category) lets the function below read $category.
        // bail stops checking the name as soon as one rule fails.
        return [
            'name' => ['bail', 'required', 'string', 'max:100', function ($attribute, $value, $fail) use ($category) {
                // Treat "Phones" and "phones" as the same name.
                // The ? is a safe place for Laravel to put the name being checked.
                $duplicate = Category::whereRaw('LOWER(name) = LOWER(?)', [trim($value)])
                    // When editing, ignore this category itself when looking for the same name.
                    ->when($category, fn ($query) => $query->where('id', '!=', $category->id))
                    ->exists();

                if ($duplicate) {
                    // Show this error next to the name field.
                    $fail('A category with this name already exists.');
                }
            }],
        ];
    }
}
