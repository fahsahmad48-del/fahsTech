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
    }  // we write it in model because we want to use it in the view, not the controller. The controller is for handling requests and responses, while the model is for data and business logic.

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
