<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained()->cascadeOnDelete();
            $table->string('name');          // "Paneer Tikka"
            $table->text('description')->nullable();  // Add after 'name'
            $table->unsignedInteger('price'); // in paise; 19900 => ₹199
            $table->boolean('veg')->default(true);
            $table->decimal('rating', 3, 2)->default(0); // 0.00–5.00
            $table->string('image_url')->nullable();
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            $table->index(['menu_id', 'is_available']);
            $table->index(['veg', 'is_available']); // quick veg filter
            
            // Add search indexes
            $table->index('name', 'menu_items_name_index');
            $table->index('is_available', 'menu_items_available_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_items');
    }
};
