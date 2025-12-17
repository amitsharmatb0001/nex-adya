<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('restaurant_id')->constrained()->cascadeOnDelete();
            $t->foreignId('address_id')->nullable();
            $t->foreignId('payment_id')->nullable();
            $t->unsignedInteger('total')->default(0);
            $t->string('status')->default('pending');
            $t->string('notes', 500)->nullable();
            $t->string('voice_note_path')->nullable(); // For voice notes at order level
            $t->timestamps();

            $t->index(['user_id','status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
