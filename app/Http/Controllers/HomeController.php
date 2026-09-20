<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class HomeController extends Controller
{
    /** Show the home page with store settings, categories, and the newest products. */
    public function homePage(): View
    {
        // Get the store settings. Use a saved copy (cache) to avoid loading them again each time.
        $settings = Setting::cached();

        // Use the saved categories if they are still available.
        // Otherwise, run the function below and save its result for one hour.
        $categoriesData = Cache::remember(
            'home.categories',
            now()->addHour(),
            function () {
                // Count each category's products and skip categories with no products.
                // Choose up to six random categories and turn their data into arrays.
                return Category::withCount('products')
                    ->has('products')
                    ->inRandomOrder()
                    ->take(6)
                    ->get()
                    ->toArray();
            }
        );

        // Change each category into an object so the page can read values like $category->name.
        $categories = collect($categoriesData)->map(function ($category) {
            return (object) $category;
        });

        // Keep a saved copy of the newest eight products for five minutes.
        $productsData = Cache::remember(
            'home.products.latest',
            now()->addMinutes(5),
            function () {
                // Show newest products first. If two were added at the same time, show the higher ID first.
                return Product::latest()->orderByDesc('id')
                    ->take(8)
                    ->get()
                    ->toArray();
            }
        );

        // Change each product into an object so the page can read values like $product->name.
        $products = collect($productsData)->map(function ($product) {
            return (object) $product;
        });

        // Open the home view. compact() sends the variables listed below to that page.
        return view('home', compact(
            'settings',
            'categories',
            'products'
        ));
    }
}
