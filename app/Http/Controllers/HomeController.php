<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function homePage(): View
    {
        $settings = Setting::cached();

        $categoriesData = Cache::remember(
            'home.categories',
            now()->addHour(),
            function () {
                return Category::withCount('products')
                    ->orderBy('name')
                    ->get()
                    ->toArray();
            }
        );

        $categories = collect($categoriesData)->map(function ($category) {
            return (object) $category;
        });

        $productsData = Cache::remember(
            'home.products.latest',
            now()->addMinutes(5),
            function () {
                return Product::latest()->orderByDesc('id')
                    ->take(8)
                    ->get()
                    ->toArray();
            }
        );

        $products = collect($productsData)->map(function ($product) {
            return (object) $product;
        });

        return view('home', compact(
            'settings',
            'categories',
            'products'
        ));
    }
}
