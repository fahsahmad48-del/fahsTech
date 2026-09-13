<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Product extends Model
{
    public const TYPES = [
        'phone' => 'Phone',
        'laptop' => 'Laptop',
        'earbuds' => 'Earbuds / AirPods',
        'playstation' => 'PlayStation',
        'tablet' => 'Tablet',
        'smart_watch' => 'Smart Watch',
        'other' => 'Other',
    ];

    protected $attributes = ['product_type' => 'phone'];

    protected $fillable = [
        'product_type',
        'name',
        'brand',
        'slug',
        'category_id',
        'price',
        'ram',
        'storage',
        'colors',
        'specs',
        'description',
        'image_path',
        'image_status',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'ram' => 'array',
        'storage' => 'array',
        'colors' => 'array',
        'specs' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (Product $product) {
            if (! $product->slug) {
                $product->slug = static::createUniqueSlug($product->name);
            }
        });
    }

    private static function createUniqueSlug(string $name): string
    {
        $slug = substr(Str::slug($name), 0, 120) ?: 'product';
        $originalSlug = $slug;
        $counter = 1;

        while (static::where('slug', $slug)->exists()) {
            $slug = $originalSlug.'-'.$counter;
            $counter++;
        }

        return $slug;
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public static function deviceTypes(): array
    {
        return array_diff_key(self::TYPES, ['phone' => true]);
    }

    public function isPhone(): bool
    {
        return ($this->product_type ?? 'phone') === 'phone';
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->product_type ?? 'phone'] ?? 'Other';
    }
}
