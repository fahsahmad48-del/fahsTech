<?php

namespace App\Support;

use App\Models\Perfume;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\Rule;

class PerfumeCatalog
{
    public function paginate(Request $request, string $route, ?int $page = null, bool $searchable = true): LengthAwarePaginator
    {
        $data = $request->validate([
            ...($searchable ? ['q' => ['nullable', 'string', 'max:120']] : []),
            'category' => ['nullable', 'string', Rule::in(array_keys(Perfume::CATEGORIES))],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ]);

        $search = trim($data['q'] ?? '');
        $query = Perfume::query();
        if ($search !== '') {
            // Treat LIKE wildcards and the escape character as literal input.
            $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search);
            $query->whereRaw("LOWER(perfumes.name) LIKE LOWER(?) ESCAPE '!'", ["%{$escaped}%"]);
        }

        return $query
            ->when($data['category'] ?? null, fn ($query, $category) => $query->where('category', $category))
            ->latest()->orderByDesc('id')
            ->paginate(15, ['*'], 'page', $page ?? ($data['page'] ?? 1))
            ->withPath(route($route))
            ->appends(array_filter([
                'q' => $search, 'category' => $data['category'] ?? null,
            ], fn ($value) => $value !== null && $value !== ''));
    }
}
