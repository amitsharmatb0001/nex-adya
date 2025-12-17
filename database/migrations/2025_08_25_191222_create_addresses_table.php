<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('addresses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();

            $t->string('label')->nullable();    // "Home", "Office"
            $t->string('line1');
            $t->string('line2')->nullable();
            $t->string('landmark')->nullable();

            $t->string('city');
            $t->string('state')->nullable();
            $t->string('pincode', 12);

            $t->decimal('lat', 10, 7)->nullable();
            $t->decimal('lng', 10, 7)->nullable();

            $t->boolean('is_default')->default(false);

            $t->timestamps();

            $t->index(['user_id', 'is_default']);
            $t->index(['pincode']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('addresses');
    }
};
