<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Perfume extends Model
{
    public const CATEGORIES = [
        'men' => 'Men', 'women' => 'Women', 'kids' => 'Kids',
        'body-mists' => 'Body Mists', 'home-sprays' => 'Home Sprays',
    ];

    protected $fillable = [
        'name', 'slug', 'category', 'price', 'specs',
        'description', 'image_path', 'image_status',
    ];

    protected $casts = ['specs' => 'array', 'price' => 'decimal:2'];

    protected static function booted(): void
    {
        static::creating(function (Perfume $perfume) {
            if (! $perfume->slug) {
                $base = substr(Str::slug($perfume->name), 0, 120) ?: 'perfume';
                $slug = $base;
                $counter = 1;
                // Keep the public JSON endpoint available even for a perfume named "Filter".
                while ($slug === 'filter' || static::where('slug', $slug)->exists()) {
                    $slug = $base.'-'.$counter++;
                }
                $perfume->slug = $slug;
            }
        });
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }
}
