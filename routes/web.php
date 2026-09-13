<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\PerfumeController as AdminPerfumeController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PerfumeController;
use Illuminate\Support\Facades\Route;



// =========================
// Public
// =========================

Route::get('/', [HomeController::class, 'homePage'])
    ->name('home');

Route::get('/search', [SearchController::class, 'searchPage'])
    ->name('search');

Route::get('/search/products', [SearchController::class, 'searchProducts'])
    ->name('search.products');

Route::get('/products/{slug}', [App\Http\Controllers\ProductController::class, 'show'])
    ->name('products.show');

Route::get('/perfumes', [PerfumeController::class, 'index'])->name('perfumes.index');
Route::get('/perfumes/filter', [PerfumeController::class, 'filter'])->name('perfumes.filter');
Route::get('/perfumes/{perfume:slug}', [PerfumeController::class, 'show'])->name('perfumes.show');

// =========================
// Admin login
// =========================

Route::get('/admin/login', [AuthController::class, 'showLogin'])
    ->name('admin.login');

Route::post('/admin/login', [AuthController::class, 'login'])
    ->middleware('throttle:admin-login')
    ->name('admin.login.submit');

// =========================
// Protected admin
// =========================

Route::middleware('admin')
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {

        // Products
        Route::get('/products', [ProductController::class, 'index'])
            ->name('products.index');

        Route::get('/products/filter', [ProductController::class, 'filter'])
            ->name('products.filter');

        Route::post('/products', [ProductController::class, 'store'])
            ->name('products.store');

        Route::post('/products/devices', [ProductController::class, 'storeDevice'])
            ->name('products.devices.store');

        Route::put('/products/{product}', [ProductController::class, 'update'])
            ->name('products.update');

        Route::delete('/products/{product}', [ProductController::class, 'destroy'])
            ->name('products.destroy');

        // Perfumes
        Route::get('/perfumes/filter', [AdminPerfumeController::class, 'filter'])->name('perfumes.filter');
        Route::resource('perfumes', AdminPerfumeController::class)->only(['index', 'store', 'update', 'destroy']);

        // Categories
        Route::get('/categories', [CategoryController::class, 'index'])
            ->name('categories.index');

        Route::post('/categories', [CategoryController::class, 'store'])
            ->name('categories.store');

        Route::put('/categories/{category}', [CategoryController::class, 'update'])
            ->name('categories.update');

        Route::delete('/categories/{category}', [CategoryController::class, 'destroy'])
            ->name('categories.destroy');

        // Store settings
        Route::get('/settings', [SettingController::class, 'index'])
            ->name('settings.index');

        Route::put('/settings', [SettingController::class, 'update'])
            ->name('settings.update');

        // Change admin password
        Route::put('/settings/password', [SettingController::class, 'updatePassword'])
            ->middleware('throttle:5,1')
            ->name('settings.password');

        // Logout
        Route::post('/logout', [AuthController::class, 'logout'])
            ->name('logout');

});
