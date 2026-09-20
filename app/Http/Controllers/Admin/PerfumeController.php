<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Perfume;
use App\Services\CatalogImageService;
use App\Support\PerfumeCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class PerfumeController extends Controller
{
    /** Show the admin perfume list. Keep the image preview if a form has errors. */
    public function index(Request $request, PerfumeCatalog $catalog)
    {
        // Use the catalog helper to check the filters and get one page of perfumes.
        $perfumes = $catalog->paginate($request, 'admin.perfumes.index');
        // Example: if page 4 no longer exists, go to the last page that does.
        // array_filter() leaves the category out of the link when no category was chosen.
        if ($perfumes->currentPage() > $perfumes->lastPage()) {
            return redirect()->route('admin.perfumes.index', array_filter([
                'category' => $request->input('category'), 'page' => $perfumes->lastPage(),
            ]));
        }
        $selectedCategory = $request->input('category', '');
        // old() gets the perfume ID from the form that had an error.
        // Check that it contains only digits before finding that perfume and its image.
        $record = old('_record');
        $editingPerfume = is_scalar($record) && ctype_digit((string) $record)
            ? Perfume::find($record) : null;

        return view('admin.perfumes.index', compact('perfumes', 'selectedCategory', 'editingPerfume'));
    }

    /** Send new table rows and page links without reloading the whole page. */
    public function filter(Request $request, PerfumeCatalog $catalog)
    {
        $perfumes = $catalog->paginate($request, 'admin.perfumes.index');
        // If the chosen page no longer exists, get the last page instead.
        if ($perfumes->currentPage() > $perfumes->lastPage()) {
            $perfumes = $catalog->paginate($request, 'admin.perfumes.index', $perfumes->lastPage());
        }
        $selectedCategory = $request->input('category', '');

        // Turn the views into HTML that JavaScript can put on the page.
        // total() counts all matching perfumes, including those on other pages.
        return response()->json([
            'html' => view('admin.perfumes.rows', compact('perfumes', 'selectedCategory'))->render(),
            'pagination' => $perfumes->links('components.pagination', ['label' => 'Perfume pages'])->toHtml(),
            'count' => $perfumes->total(), 'page' => $perfumes->currentPage(),
        ]);
    }

    /** Check the form, save the image, and add the new perfume. */
    public function store(Request $request, CatalogImageService $images)
    {
        $data = $this->validatedData($request, true);
        // Save the image as a WebP file and keep its file path for the database.
        $data['image_path'] = $images->save($request->file('image'), 'perfumes');
        $data['image_status'] = 'active';
        try {
            Perfume::create($data);
        } catch (Throwable $exception) {
            // If saving the perfume fails, delete the new image so it is not left unused.
            // throw passes the error back to Laravel.
            Storage::disk('public')->delete($data['image_path']);
            throw $exception;
        }

        return $this->returnToList($request, 'Perfume added successfully.', 201);
    }

    /** Save changes to this perfume. Keep its image unless a new one is uploaded. */
    public function update(Request $request, Perfume $perfume, CatalogImageService $images)
    {
        $data = $this->validatedData($request, false);
        // Save the new image first. If that fails, the perfume stays as it was.
        if ($request->hasFile('image')) {
            $data['image_path'] = $images->save($request->file('image'), 'perfumes');
            $data['image_status'] = 'active';
        }
        try {
            // If saving fails, transaction() undoes the database changes made inside this function.
            // use() lets the function below read $perfume and $data.
            $oldImage = DB::transaction(function () use ($perfume, $data) {
                // Get the latest copy of this perfume.
                // Ask the database to stop other edits to this row until we finish, if it supports this.
                $current = Perfume::lockForUpdate()->findOrFail($perfume->id);
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
        // The new image path is now saved, so the old image can be deleted.
        if (isset($data['image_path'])) {
            Storage::disk('public')->delete($oldImage);
        }

        return $this->returnToList($request, 'Perfume updated successfully.');
    }

    /** Delete this perfume and its image. */
    public function destroy(Request $request, Perfume $perfume)
    {
        // Get the latest perfume and ask the database to hold other edits while deleting it.
        // Delete the image only after the database deletion succeeds.
        $imagePath = DB::transaction(function () use ($perfume) {
            $current = Perfume::lockForUpdate()->findOrFail($perfume->id);
            $current->delete();

            return $current->image_path;
        });
        Storage::disk('public')->delete($imagePath);

        return $this->returnToList($request, 'Perfume deleted successfully.');
    }

    /** Check the perfume form and prepare its details for saving. */
    private function validatedData(Request $request, bool $imageRequired): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            // Allow only the categories listed in Perfume::CATEGORIES: men and women.
            'category' => ['required', 'string', Rule::in(array_keys(Perfume::CATEGORIES))],
            // Allow prices such as 25 or 25.50, but not 25.555.
            'price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'description' => ['nullable', 'string', 'max:5000'],
            // New perfumes need an image. Editing does not require a new one.
            // The image must be at most 8 MB, 4096 pixels wide, and 4096 pixels tall.
            'image' => [$imageRequired ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'dimensions:max_width=4096,max_height=4096'],
            'specs' => ['nullable', 'array', 'max:30'],
            // The * means every row. Each row may contain only name and value.
            'specs.*' => ['array:name,value'],
            'specs.*.name' => ['nullable', 'string', 'max:60'],
            'specs.*.value' => ['nullable', 'string', 'max:255'],
        ]);
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
                // specs.0.value means the value box in the first row, where the error will appear.
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
        // Remove the uploaded file from this data. Its file path is saved separately.
        unset($data['image']);

        return $data;
    }

    /** Send a success message and return to the same category and page. */
    private function returnToList(Request $request, string $message, int $status = 200)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], $status);
        }

        // The form remembers the category and page the admin was viewing.
        // Check that the page number is allowed before using it.
        $category = $request->input('filter_category');
        $page = filter_var($request->input('filter_page'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]);

        // Use page 1 if the page number is invalid. Leave out unknown categories.
        // with('success', ...) saves the message to show on the next page.
        return redirect()->route('admin.perfumes.index', array_filter([
            'category' => is_string($category) && isset(Perfume::CATEGORIES[$category]) ? $category : null,
            'page' => $page ?: 1,
        ]))->with('success', $message);
    }
}
