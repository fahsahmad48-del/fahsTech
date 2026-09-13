<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Services\CatalogImageService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Throwable;

class ProductController extends Controller
{
    // Show products
    public function index(Request $request)
    {
        $products = $this->filteredProducts($request);
        if ($products->currentPage() > $products->lastPage()) {
            return redirect()->route('admin.products.index', array_filter([
                'category' => $request->input('category'), 'page' => $products->lastPage(),
            ]));
        }
        $categories = Category::orderBy('name')->get();
        $selectedCategory = $request->input('category', '');

        return view('admin.products.index', compact('products', 'categories', 'selectedCategory'));
    }

    public function filter(Request $request): JsonResponse
    {
        $products = $this->filteredProducts($request);
        if ($products->currentPage() > $products->lastPage()) {
            $products = $this->filteredProducts($request, $products->lastPage());
        }
        $selectedCategory = $request->input('category', '');

        return response()->json([
            'html' => view('admin.products.rows', compact('products', 'selectedCategory'))->render(),
            'pagination' => $products->links('components.pagination')->toHtml(),
            'count' => $products->total(),
            'page' => $products->currentPage(),
        ]);
    }

    private function filteredProducts(Request $request, ?int $page = null): LengthAwarePaginator
    {
        $validated = $request->validate([
            'category' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);

        return Product::with('category')
            ->when($validated['category'] ?? null, fn ($query, $category) => $query->where('category_id', $category))
            ->latest()->orderByDesc('id')
            ->paginate(15, ['*'], 'page', $page ?? ($validated['page'] ?? 1))
            ->withPath(route('admin.products.index'))
            ->appends(array_filter(['category' => $validated['category'] ?? null]));
    }

    // Add product
    public function store(Request $request)
    {
        $validated = $request->validate($this->rules(true));
        $data = $this->prepareData($validated);
        $data['product_type'] = 'phone';

        return $this->createProduct($request, $data, 'Phone added successfully.');
    }

    public function storeDevice(Request $request)
    {
        $data = $this->prepareDeviceData($request->validate($this->deviceRules(true)));

        return $this->createProduct($request, $data, 'Device added successfully.');
    }

    private function createProduct(Request $request, array $data, string $message)
    {
        $data['image_path'] = $this->saveImage($request->file('image'));
        $data['image_status'] = 'active';

        try {
            Product::create($data);
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($data['image_path']);
            throw $exception;
        }

        $this->clearCache();

        return $this->returnToProducts($request, $message, 201);
    }

    // Edit product
    public function update(Request $request, Product $product)
    {
        if ($product->isPhone()) {
            $data = $this->prepareData($request->validate($this->rules(false)));
            $data['product_type'] = 'phone';
        } else {
            $data = $this->prepareDeviceData($request->validate($this->deviceRules(false)));
        }

        if ($request->hasFile('image')) {
            $data['image_path'] = $this->saveImage($request->file('image'));
        }

        try {
            $oldImage = DB::transaction(function () use ($product, $data) {
                $current = Product::lockForUpdate()->findOrFail($product->id);
                $oldImage = $current->image_path;
                $current->update($data);

                return $oldImage;
            });
        } catch (Throwable $exception) {
            if (isset($data['image_path'])) {
                Storage::disk('public')->delete($data['image_path']);
            }
            throw $exception;
        }

        $this->clearCache();
        if (isset($data['image_path']) && $oldImage) {
            Storage::disk('public')->delete($oldImage);
        }

        return $this->returnToProducts($request, $product->isPhone() ? 'Phone updated successfully.' : 'Device updated successfully.');
    }

    // Delete product
    public function destroy(Request $request, Product $product)
    {
        $imagePath = DB::transaction(function () use ($product) {
            $current = Product::lockForUpdate()->findOrFail($product->id);
            $current->delete();

            return $current->image_path;
        });
        $this->clearCache();

        if ($imagePath) {
            Storage::disk('public')->delete($imagePath);
        }

        return $this->returnToProducts($request, 'Product deleted successfully.');
    }

    private function returnToProducts(Request $request, string $message, int $status = 200)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], $status);
        }

        // Preserve the list context for normal form submissions, including delete.
        if ($request->has('filter_page')) {
            $category = filter_var($request->input('filter_category'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $page = filter_var($request->input('filter_page'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]);

            return redirect()->route('admin.products.index', array_filter([
                'category' => $category ?: null, 'page' => $page ?: 1,
            ]))->with('success', $message);
        }

        return back()->with('success', $message);
    }

    private function deviceRules(bool $imageRequired): array
    {
        $rules = array_diff_key($this->rules($imageRequired), array_flip([
            'ram', 'storage', 'screen', 'processor', 'battery', 'camera',
        ]));

        return $rules + [
            'product_type' => ['required', 'string', Rule::in(array_keys(Product::deviceTypes()))],
            'specs' => ['nullable', 'array', 'max:30'],
            'specs.*' => ['array:name,value'],
            'specs.*.name' => ['nullable', 'string', 'max:60'],
            'specs.*.value' => ['nullable', 'string', 'max:255'],
        ];
    }

    private function prepareDeviceData(array $data): array
    {
        $specs = [];
        $names = [];
        foreach ($data['specs'] ?? [] as $index => $row) {
            $name = trim($row['name'] ?? '');
            $value = trim($row['value'] ?? '');
            if ($name === '' && $value === '') {
                continue;
            }
            if ($name === '' || $value === '') {
                $field = $name === '' ? 'name' : 'value';
                throw ValidationException::withMessages(["specs.$index.$field" => 'Enter both a specification name and value, or leave both empty.']);
            }
            $key = mb_strtolower($name);
            if (isset($names[$key])) {
                throw ValidationException::withMessages(["specs.$index.name" => 'Each specification name must be unique.']);
            }
            $names[$key] = true;
            $specs[$name] = $value;
        }

        // Store a JSON object even when specification names happen to be numeric.
        $data['specs'] = (object) $specs;
        $data['colors'] = $this->toArray($data['colors'] ?? null);
        unset($data['image']);

        return $data;
    }

    // Validation
    private function rules(bool $imageRequired): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'brand' => ['required', 'string', 'max:60'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'description' => ['nullable', 'string', 'max:5000'],
            'ram' => ['nullable', 'string', 'max:500'],
            'storage' => ['nullable', 'string', 'max:500'],
            'colors' => ['nullable', 'string', 'max:500'],
            'screen' => ['nullable', 'string', 'max:255'],
            'processor' => ['nullable', 'string', 'max:255'],
            'battery' => ['nullable', 'string', 'max:255'],
            'camera' => ['nullable', 'string', 'max:255'],
            'image' => [$imageRequired ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'dimensions:max_width=4096,max_height=4096'],
        ];
    }

    // Prepare RAM, storage, colors and specs
    private function prepareData(array $data): array
    {
        $data['ram'] = $this->toArray($data['ram'] ?? null);
        $data['storage'] = $this->toArray($data['storage'] ?? null);
        $data['colors'] = $this->toArray($data['colors'] ?? null);

        $data['specs'] = array_filter([
            'Screen' => $data['screen'] ?? null,
            'Processor' => $data['processor'] ?? null,
            'Battery' => $data['battery'] ?? null,
            'Camera' => $data['camera'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        unset($data['screen'], $data['processor'], $data['battery'], $data['camera'], $data['image']);

        return $data;
    }

    // "8GB, 12GB" => ["8GB", "12GB"]
    private function toArray(?string $value): ?array
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return array_values(array_unique(array_filter(
            array_map('trim', explode(',', $value)),
            fn ($item) => $item !== ''
        ))) ?: null;
    }

    // Resize, compress and save image as WebP
    private function saveImage(UploadedFile $file): string
    {
        return app(CatalogImageService::class)->save($file, 'products');
    }

    // Clear changed home data
    private function clearCache(): void
    {
        Cache::forget('home.products.latest');
        Cache::forget('home.categories');
    }
}
