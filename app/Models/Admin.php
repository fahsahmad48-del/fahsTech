<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Admin extends Model
{
    protected $fillable = [
        'password',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return ['password' => 'hashed'];
    }

    public function sessionFingerprint(): string
    {
        return hash_hmac('sha256', $this->password, config('app.key')); 
    }
}
