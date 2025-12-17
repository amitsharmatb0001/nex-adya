<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $table->string('title');                 // e.g., "Main Menu"
            $table->text('description')->nullable(); // optional copy
            $table->timestamps();

            // Helpful for queries like "all menus for restaurant"
            $table->index('restaurant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menus');
    }
};
