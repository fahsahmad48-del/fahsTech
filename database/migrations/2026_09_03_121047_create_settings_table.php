<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();

            $table->string('store_name', 120)
                ->default('Mobile Hub');

            $table->string('tagline')
                ->nullable();

            $table->string('about_title')
                ->nullable();

            $table->text('about_text')
                ->nullable();

            $table->string('phone', 30)
                ->nullable();

            $table->string('whatsapp', 30)
                ->nullable();

            $table->string('address')
                ->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
