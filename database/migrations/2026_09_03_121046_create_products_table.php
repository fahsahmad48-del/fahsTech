<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('products', function (Blueprint $table) {
            $table->id();

            $table->string('name', 120);

            $table->string('brand', 60)->index();

            $table->string('slug', 140)->unique();

            $table->foreignId('category_id')
                ->constrained()
                ->cascadeOnUpdate()
                ->restrictOnDelete();

            $table->decimal('price', 10, 2);

            $table->json('ram')->nullable();
            $table->json('storage')->nullable();
            $table->json('colors')->nullable();
            $table->json('specs')->nullable();

            $table->text('description')->nullable();

            $table->string('image_path')->nullable();

            $table->string('image_status', 30)
                ->default('active');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
