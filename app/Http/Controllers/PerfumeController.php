<?php

namespace App\Http\Controllers;

use App\Models\Perfume;
use App\Support\PerfumeCatalog;
use Illuminate\Http\Request;

class PerfumeController extends Controller
{
    public function index(Request $request, PerfumeCatalog $catalog)
    {
        $perfumes = $catalog->paginate($request, 'perfumes.index');
        if ($perfumes->currentPage() > $perfumes->lastPage()) {
            return redirect()->route('perfumes.index', array_filter([
                'category' => $request->input('category'), 'page' => $perfumes->lastPage(),
            ]));
        }
        $selectedCategory = $request->input('category', '');

        return view('perfumes.index', compact('perfumes', 'selectedCategory'));
    }

    public function filter(Request $request, PerfumeCatalog $catalog)
    {
        $perfumes = $catalog->paginate($request, 'perfumes.index');
        if ($perfumes->currentPage() > $perfumes->lastPage()) {
            $perfumes = $catalog->paginate($request, 'perfumes.index', $perfumes->lastPage());
        }

        return response()->json([
            'html' => view('perfumes.cards', compact('perfumes'))->render(),
            'pagination' => $perfumes->links('components.pagination', ['label' => 'Perfume pages'])->toHtml(),
            'count' => $perfumes->total(), 'page' => $perfumes->currentPage(),
        ]);
    }

    public function show(Perfume $perfume)
    {
        return view('perfumes.show', compact('perfume'));
    }
}
