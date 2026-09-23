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
use Throwable;

class ProductController extends Controller
{
    /** Show the admin product list and the category filter. */
    public function index(Request $request)
    {
        $products = $this->filteredProducts($request);
        // Deleting items can make a page disappear.
        // If that happens, open the last available page and keep the chosen category.
        if ($products->currentPage() > $products->lastPage()) {
            return redirect()->route('admin.products.index', array_filter([
                'category' => $request->input('category'), 'page' => $products->lastPage(),
            ]));
        }
        $categories = Category::orderBy('name')->get();
        $selectedCategory = $request->input('category', '');

        return view('admin.products.index', compact('products', 'categories', 'selectedCategory'));
    }

    /** Send new product rows and page links without reloading the whole page. */
    public function filter(Request $request): JsonResponse
    {
        $products = $this->filteredProducts($request);
        // If the chosen page no longer exists, get the last available page.
        if ($products->currentPage() > $products->lastPage()) {
            $products = $this->filteredProducts($request, $products->lastPage());
        }
        $selectedCategory = $request->input('category', '');

        // Turn the table rows and page links into HTML for JavaScript to display.
        // total() counts all matching products, including those on other pages.
        return response()->json([
            'html' => view('admin.products.rows', compact('products', 'selectedCategory'))->render(),
            'pagination' => $products->links('components.pagination')->toHtml(),
            'count' => $products->total(),
            'page' => $products->currentPage(),
        ]);
    }

    /** Check the category and page number, then get 15 products for that page. */
    private function filteredProducts(Request $request, ?int $page = null): LengthAwarePaginator
    {
        $validated = $request->validate([
            'category' => ['nullable', 'integer', 'min:1'],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);

        // with('category') gets the category names too, so we do not fetch each one separately.
        // when() filters by category only if the admin chose one.
        return Product::with('category')
            ->when($validated['category'] ?? null, fn ($query, $category) => $query->where('category_id', $category))
            // Show newest products first. If two were added at the same time, show the higher ID first.
            ->latest()->orderByDesc('id')
            // Use $page if given. Otherwise, use the requested page, or page 1.
            // The ?? means "use the next value if this one is null or missing".
            ->paginate(15, ['*'], 'page', $page ?? ($validated['page'] ?? 1))
            // Keep the chosen category when the admin clicks another page number.
            ->withPath(route('admin.products.index'))
            ->appends(array_filter(['category' => $validated['category'] ?? null]));
    }

    /** Check the phone form and add a new phone. */
    public function store(Request $request)
    {
        $validated = $request->validate($this->rules(true));
        $data = $this->prepareData($validated);
        // This function adds phones, so always set the type to phone.
        $data['product_type'] = 'phone';

        return $this->createProduct($request, $data, 'Phone added successfully.');
    }

    /** Add another kind of device, such as a laptop, with its own details. */
    public function storeDevice(Request $request)
    {
        $data = $this->prepareDeviceData($request->validate($this->deviceRules(true)));
        $data['product_type'] = 'device';

        return $this->createProduct($request, $data, 'Device added successfully.');
    }

    /** Save a new phone or device and its image, then send a success message. */
    private function createProduct(Request $request, array $data, string $message)
    {
        // Save the image file. Only its file path goes in the database.
        $data['image_path'] = $this->saveImage($request->file('image'));
        $data['image_status'] = 'active';

        try {
            Product::create($data);
        } catch (Throwable $exception) {
            // If saving the product fails, delete the new image so it is not left unused.
            // throw passes the error back to Laravel.
            Storage::disk('public')->delete($data['image_path']);
            throw $exception;
        }

        $this->clearCache();

        return $this->returnToProducts($request, $message, 201);
    }

    /** Save changes to this product. Keep its image unless a new one is uploaded. */
    public function update(Request $request, Product $product)
    {
        // Phones use fields like RAM and storage. Other devices use detail names and values.
        if ($product->isPhone()) {
            $data = $this->prepareData($request->validate($this->rules(false)));
            $data['product_type'] = 'phone';
        } else {
            $data = $this->prepareDeviceData($request->validate($this->deviceRules(false)));
            $data['product_type'] = 'device';
        }

        // Save the new image first. If that fails, the product stays as it was.
        if ($request->hasFile('image')) {
            $data['image_path'] = $this->saveImage($request->file('image'));
        }

        try {
            // If saving fails, transaction() undoes the database changes made inside this function.
            $oldImage = DB::transaction(function () use ($product, $data) {
                // Get the latest product and its current image path.
                // Ask the database to hold other edits to this row until we finish, if it supports this.
                $current = Product::lockForUpdate()->findOrFail($product->id); // if two admins edit the same product at the same time, one will wait for the other to finish.
                $oldImage = $current->image_path;
                $current->update($data);

                return $oldImage;
            });
        } catch (Throwable $exception) {
            // If saving fails, delete the new image and keep the old one.
            if (isset($data['image_path'])) {
                Storage::disk('public')->delete($data['image_path']);
            }
            throw $exception;
        }

        $this->clearCache();
        // The new image path is now saved, so the old image can be deleted.
        if (isset($data['image_path']) && $oldImage) {
            Storage::disk('public')->delete($oldImage);
        }

        return $this->returnToProducts($request, $product->isPhone() ? 'Phone updated successfully.' : 'Device updated successfully.');
    }

    /** Delete the product and its image, then clear old saved home page data. */
    public function destroy(Request $request, Product $product)
    {
        // Get the latest product and ask the database to hold other edits while deleting it.
        // Delete the image only after the database deletion succeeds.
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

    /** Send a success message and return to the product list. */
    private function returnToProducts(Request $request, string $message, int $status = 200)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], $status);
        }

        // The form remembers the category and page the admin was viewing.
        // filter_var checks the numbers. It returns false if a number is not allowed.
        if ($request->has('filter_page')) {
            $category = filter_var($request->input('filter_category'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $page = filter_var($request->input('filter_page'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]);

            // Use page 1 if the page number is invalid. Leave out an empty category.
            // with('success', ...) saves the message to show on the next page.
            return redirect()->route('admin.products.index', array_filter([
                'category' => $category ?: null, 'page' => $page ?: 1,
            ]))->with('success', $message);
        }

        return back()->with('success', $message);
    }

    /** Set the form rules for devices other than phones. */
    private function deviceRules(bool $imageRequired): array
    {
        // Start with the phone rules, then remove the rules for RAM, storage, and the fields below.
        // array_flip makes those field names into keys so array_diff_key can remove them.
        $rules = array_diff_key($this->rules($imageRequired), array_flip([
            'ram', 'storage', 'screen', 'processor', 'battery', 'camera',
        ]));

        // Add the device specification rules to the rules above.
        // The * means every details row. Each row may contain only name and value.
        return $rules + [
            'specs' => ['nullable', 'array', 'max:30'],
            'specs.*' => ['array:name,value'],
            'specs.*.name' => ['nullable', 'string', 'max:60'],
            'specs.*.value' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** Prepare the device's details and colors for saving. */
    private function prepareDeviceData(array $data): array
    {
        $specs = [];
        $names = [];
        // Go through the details one row at a time. If there are no rows, skip the loop.
        foreach ($data['specs'] ?? [] as $index => $row) {
            $name = trim($row['name'] ?? '');
            $value = trim($row['value'] ?? '');
            // Skip a row if both boxes are empty.
            // If only one box is filled, ask the admin to fill the other one too.
            if ($name === '' && $value === '') {
                continue;
            }
            if ($name === '' || $value === '') {
                // specs.2.value means the value box in the third row, where the error will appear.
                $field = $name === '' ? 'name' : 'value';
                throw ValidationException::withMessages(["specs.$index.$field" => 'Enter both a specification name and value, or leave both empty.']);
            }
            // Treat "Size" and "size" as the same name so it cannot be added twice.
            // Keep the original spelling when saving.
            $key = mb_strtolower($name);
            if (isset($names[$key])) {
                throw ValidationException::withMessages(["specs.$index.name" => 'Each specification name must be unique.']);
            }
            $names[$key] = true;
            $specs[$name] = $value;
        }

        // Save details as name/value pairs, even when a name is a number.
        $data['specs'] = (object) $specs;
        $data['colors'] = $this->toArray($data['colors'] ?? null);
        // Remove the uploaded file from this data. Its file path is saved separately.
        unset($data['image']);

        return $data;
    }

    /** Set the phone form rules. New phones need an image; edits can keep the old one. */
    private function rules(bool $imageRequired): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'brand' => ['required', 'string', 'max:60'],
            // Check that the chosen category really exists in the database.
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            // Allow prices such as 25 or 25.50, but not 25.555.
            'price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'description' => ['nullable', 'string', 'max:5000'],
            'ram' => ['nullable', 'string', 'max:500'],
            'storage' => ['nullable', 'string', 'max:500'],
            'colors' => ['nullable', 'string', 'max:500'],
            'screen' => ['nullable', 'string', 'max:255'],
            'processor' => ['nullable', 'string', 'max:255'],
            'battery' => ['nullable', 'string', 'max:255'],
            'camera' => ['nullable', 'string', 'max:255'],
            // The image must be at most 8 MB, 4096 pixels wide, and 4096 pixels tall.
            'image' => [$imageRequired ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'dimensions:max_width=4096,max_height=4096'],
        ];
    }

    /** Prepare the phone's RAM, storage, colors, and other details for saving. */
    private function prepareData(array $data): array
    {
        // Turn text like "8GB, 12GB" into a list: ["8GB", "12GB"].
        $data['ram'] = $this->toArray($data['ram'] ?? null);
        $data['storage'] = $this->toArray($data['storage'] ?? null);
        $data['colors'] = $this->toArray($data['colors'] ?? null);

        // Put the screen, processor, battery, and camera into one details list.
        // Leave out empty values, but keep "0" if it was entered.
        $data['specs'] = array_filter([
            'Screen' => $data['screen'] ?? null,
            'Processor' => $data['processor'] ?? null,
            'Battery' => $data['battery'] ?? null,
            'Camera' => $data['camera'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');

        // Remove these separate form fields because their values were already handled above.
        unset($data['screen'], $data['processor'], $data['battery'], $data['camera'], $data['image']);

        return $data;
    }

    /** Turn text separated by commas into a list, removing extra spaces and repeated items. */
    private function toArray(?string $value): ?array
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        // Example: " Red, Blue, Red, " becomes ["Red", "Blue"].
        // explode splits at commas. array_map trims spaces. array_filter removes empty items.
        // array_unique removes repeats. array_values numbers the list from 0 again.
        // If nothing is left, return null.
        return array_values(array_unique(array_filter(
            array_map('trim', explode(',', $value)),
            fn ($item) => $item !== ''
        ))) ?: null;
    }

    /** Make the image smaller, save it as WebP, and return its file path. */
    private function saveImage(UploadedFile $file): string
    {
        // Get our image helper and ask it to save the image in the products folder.
        return app(CatalogImageService::class)->save($file, 'products');
    }

    /** Clear old saved home page data so the next visit shows the latest products and counts. */
    private function clearCache(): void
    {
        Cache::forget('home.products.latest');
        Cache::forget('home.categories');
    }
}
