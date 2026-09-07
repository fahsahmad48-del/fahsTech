<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Intervention\Image\Exceptions\DecoderException;
use Intervention\Image\ImageManager;
use Throwable;

class ProductController extends Controller
{
    // Show products
    public function index()
    {
        $products = Product::with('category')->latest()->orderByDesc('id')->paginate(15);
        if ($products->currentPage() > $products->lastPage()) {
            return redirect()->route('admin.products.index', ['page' => $products->lastPage()]);
        }
        $categories = Category::orderBy('name')->get();

        return view('admin.products.index', compact('products', 'categories'));
    }

    // Add product
    public function store(Request $request)
    {
        $validated = $request->validate($this->rules(true));
        $data = $this->prepareData($validated);

        $data['image_path'] = $this->saveImage($request->file('image'));
        $data['image_status'] = 'active';

        try {
            Product::create($data);
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($data['image_path']);
            throw $exception;
        }

        $this->clearCache();

        return back()->with('success', 'Phone added successfully.');
    }

    // Edit product
    public function update(Request $request, Product $product)
    {
        $validated = $request->validate($this->rules(false));
        $data = $this->prepareData($validated);

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

        return back()->with('success', 'Phone updated successfully.');
    }

    // Delete product
    public function destroy(Product $product)
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

        return back()->with('success', 'Phone deleted successfully.');
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
        $path = 'products/'.Str::uuid().'.webp';

        try {
            $manager = new ImageManager(config('intervention-image.driver'), decodeAnimation: false);
            $image = $manager->decodePath($file->getRealPath());
        } catch (DecoderException $exception) {
            throw ValidationException::withMessages(['image' => 'This image could not be read. Please choose another image.']);
        }
        $image->scaleDown(width: 1200, height: 1200);

        $saved = Storage::disk('public')->put(
            $path,
            (string) $image->encodeUsingFileExtension('webp', quality: 75, strip: true)
        );

        if (! $saved) {
            throw ValidationException::withMessages(['image' => 'The image could not be saved. Please try again.']);
        }

        return $path;
    }

    // Clear changed home data
    private function clearCache(): void
    {
        Cache::forget('home.products.latest');
        Cache::forget('home.categories');
    }
}
