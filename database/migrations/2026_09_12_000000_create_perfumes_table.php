<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('perfumes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('brand', 80);
            $table->string('slug', 160)->unique();
            $table->string('category', 20);
            $table->decimal('price', 10, 2);
            $table->json('specs')->nullable();
            $table->text('description')->nullable();
            $table->string('image_path');
            $table->string('image_status', 20)->default('active');
            $table->timestamps();
            $table->index(['category', 'created_at', 'id']);
            $table->index(['created_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('perfumes');
    }
};
