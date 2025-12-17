<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('order_id')->constrained()->cascadeOnDelete();
            $t->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $t->unsignedInteger('quantity')->default(1);
            $t->unsignedInteger('price');
            $t->string('voice_note_path')->nullable();
            $t->timestamps();
            $t->index(['order_id']);
            
            // Add unique constraint to prevent duplicate rows for the same (order_id, menu_item_id)
            $t->unique(['order_id', 'menu_item_id'], 'uniq_order_menuitem');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
