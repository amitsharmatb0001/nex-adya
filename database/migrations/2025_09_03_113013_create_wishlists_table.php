<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('wishlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('wishlistable'); // wishlistable_id + wishlistable_type
            $table->timestamps();

            $table->unique(['user_id', 'wishlistable_id', 'wishlistable_type'], 'unique_wishlist');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wishlists');
    }
};
