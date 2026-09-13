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
    public function index(Request $request, PerfumeCatalog $catalog)
    {
        $perfumes = $catalog->paginate($request, 'admin.perfumes.index');
        if ($perfumes->currentPage() > $perfumes->lastPage()) {
            return redirect()->route('admin.perfumes.index', array_filter([
                'category' => $request->input('category'), 'page' => $perfumes->lastPage(),
            ]));
        }
        $selectedCategory = $request->input('category', '');
        // Restore the existing preview even after a validation redirect.
        $record = old('_record');
        $editingPerfume = is_scalar($record) && ctype_digit((string) $record)
            ? Perfume::find($record) : null;

        return view('admin.perfumes.index', compact('perfumes', 'selectedCategory', 'editingPerfume'));
    }

    public function filter(Request $request, PerfumeCatalog $catalog)
    {
        $perfumes = $catalog->paginate($request, 'admin.perfumes.index');
        if ($perfumes->currentPage() > $perfumes->lastPage()) {
            $perfumes = $catalog->paginate($request, 'admin.perfumes.index', $perfumes->lastPage());
        }
        $selectedCategory = $request->input('category', '');

        return response()->json([
            'html' => view('admin.perfumes.rows', compact('perfumes', 'selectedCategory'))->render(),
            'pagination' => $perfumes->links('components.pagination', ['label' => 'Perfume pages'])->toHtml(),
            'count' => $perfumes->total(), 'page' => $perfumes->currentPage(),
        ]);
    }

    public function store(Request $request, CatalogImageService $images)
    {
        $data = $this->validatedData($request, true);
        $data['image_path'] = $images->save($request->file('image'), 'perfumes');
        $data['image_status'] = 'active';
        try {
            Perfume::create($data);
        } catch (Throwable $exception) {
            Storage::disk('public')->delete($data['image_path']);
            throw $exception;
        }

        return $this->returnToList($request, 'Perfume added successfully.', 201);
    }

    public function update(Request $request, Perfume $perfume, CatalogImageService $images)
    {
        $data = $this->validatedData($request, false);
        if ($request->hasFile('image')) {
            $data['image_path'] = $images->save($request->file('image'), 'perfumes');
            $data['image_status'] = 'active';
        }
        try {
            $oldImage = DB::transaction(function () use ($perfume, $data) {
                $current = Perfume::lockForUpdate()->findOrFail($perfume->id);
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
        if (isset($data['image_path'])) {
            Storage::disk('public')->delete($oldImage);
        }

        return $this->returnToList($request, 'Perfume updated successfully.');
    }

    public function destroy(Request $request, Perfume $perfume)
    {
        $imagePath = DB::transaction(function () use ($perfume) {
            $current = Perfume::lockForUpdate()->findOrFail($perfume->id);
            $current->delete();

            return $current->image_path;
        });
        Storage::disk('public')->delete($imagePath);

        return $this->returnToList($request, 'Perfume deleted successfully.');
    }

    private function validatedData(Request $request, bool $imageRequired): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'brand' => ['required', 'string', 'max:80'],
            'category' => ['required', 'string', Rule::in(array_keys(Perfume::CATEGORIES))],
            'price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:99999999.99'],
            'description' => ['nullable', 'string', 'max:5000'],
            'image' => [$imageRequired ? 'required' : 'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192', 'dimensions:max_width=4096,max_height=4096'],
            'specs' => ['nullable', 'array', 'max:30'],
            'specs.*' => ['array:name,value'],
            'specs.*.name' => ['nullable', 'string', 'max:60'],
            'specs.*.value' => ['nullable', 'string', 'max:255'],
        ]);
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
        $data['specs'] = (object) $specs;
        unset($data['image']);

        return $data;
    }

    private function returnToList(Request $request, string $message, int $status = 200)
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], $status);
        }

        $category = $request->input('filter_category');
        $page = filter_var($request->input('filter_page'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100000]]);

        return redirect()->route('admin.perfumes.index', array_filter([
            'category' => is_string($category) && isset(Perfume::CATEGORIES[$category]) ? $category : null,
            'page' => $page ?: 1,
        ]))->with('success', $message);
    }
}
