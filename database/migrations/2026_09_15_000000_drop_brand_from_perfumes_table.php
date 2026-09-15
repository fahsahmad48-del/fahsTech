<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('perfumes', function (Blueprint $table) {
            $table->dropColumn('brand');
        });
    }

    public function down(): void
    {
        Schema::table('perfumes', function (Blueprint $table) {
            // Existing records need a value; deleted brand values cannot be recovered.
            $table->string('brand', 80)->default('');
        });
    }
};
