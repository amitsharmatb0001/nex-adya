<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->morphs('reviewable'); // reviewable_id + reviewable_type
            $table->tinyInteger('rating')->unsigned(); // 1–5
            $table->text('comment')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'reviewable_id', 'reviewable_type'], 'unique_review'); // user can review once
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
