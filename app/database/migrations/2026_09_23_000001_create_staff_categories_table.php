<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('farm_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('icon')->default('•');
            $table->string('color')->default('#286846');
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->unique(['farm_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_categories');
    }
};
