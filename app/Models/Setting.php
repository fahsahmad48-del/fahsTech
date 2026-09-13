<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    use HasFactory;

    public static function cached(): object
    {
        return (object) Cache::remember('home.settings', now()->addHour(), function () {
            return static::first()?->only([
                'store_name', 'tagline', 'offer_text', 'about_title', 'about_text', 'phone', 'whatsapp', 'whatsapp_number', 'address',
            ]) ?? [];
        });
    }

    protected $fillable = [
        'store_name',
        'tagline',
        'offer_text',
        'about_title',
        'about_text',
        'phone',
        'whatsapp',
        'whatsapp_number',
        'address',
    ];
}
