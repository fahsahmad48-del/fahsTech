<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Setting;
use Illuminate\View\View;

class ProductController extends Controller
{
    /** Show one product using its name in the URL, called a slug (for example, iphone-15). */
    public function show(string $slug): View
    {
        // Get the product and its category together.
        // firstOrFail() shows a "not found" page if no product has this slug.
        $product = Product::with('category')
            ->where('slug', $slug)
            ->firstOrFail();

        // Get the store information needed on this page, such as contact details.
        $settings = Setting::cached();

        // Open the product view. compact() sends $product and $settings to that page.
        return view('product', compact(
            'product',
            'settings'
        ));
    }
}
