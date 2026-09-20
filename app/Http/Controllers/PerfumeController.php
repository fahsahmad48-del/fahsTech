<?php

namespace App\Http\Controllers;

use App\Models\Perfume;
use App\Support\PerfumeCatalog;
use Illuminate\Http\Request;

class PerfumeController extends Controller
{
    /** Show the perfume list for the chosen category and page. */
    public function index(Request $request, PerfumeCatalog $catalog)
    {
        // Use the catalog helper to check the filters and get one page of perfumes.
        $perfumes = $catalog->paginate($request, 'perfumes.index');
        // Example: if page 4 no longer exists, go to the last page that does.
        // array_filter() leaves the category out of the link when no category was chosen.
        if ($perfumes->currentPage() > $perfumes->lastPage()) {
            return redirect()->route('perfumes.index', array_filter([
                'category' => $request->input('category'), 'page' => $perfumes->lastPage(),
            ]));
        }
        // Remember the chosen category. An empty string means all categories.
        $selectedCategory = $request->input('category', '');

        return view('perfumes.index', compact('perfumes', 'selectedCategory'));
    }

    /** Send new perfume cards and page links without reloading the whole page. */
    public function filter(Request $request, PerfumeCatalog $catalog)
    {
        $perfumes = $catalog->paginate($request, 'perfumes.index');
        // If the chosen page no longer exists, get the last page instead.
        if ($perfumes->currentPage() > $perfumes->lastPage()) {
            $perfumes = $catalog->paginate($request, 'perfumes.index', $perfumes->lastPage());
        }

        // Turn the views into HTML that JavaScript can put on the page.
        // total() counts all matches. currentPage() gives the page number.
        return response()->json([
            'html' => view('perfumes.cards', compact('perfumes'))->render(),
            'pagination' => $perfumes->links('components.pagination', ['label' => 'Perfume pages'])->toHtml(),
            'count' => $perfumes->total(), 'page' => $perfumes->currentPage(),
        ]);
    }

    /** Show one perfume. Laravel finds it from the URL or shows a "not found" page. */
    public function show(Perfume $perfume)
    {
        return view('perfumes.show', compact('perfume'));
    }
}
