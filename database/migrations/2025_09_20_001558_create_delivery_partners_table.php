<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('delivery_partners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('license_number')->unique();
            $table->string('vehicle_type')->default('bike'); // bike, car, scooter
            $table->string('vehicle_number');
            $table->string('phone')->unique();
            $table->json('documents'); // license, insurance, etc.
            $table->decimal('rating', 3, 2)->default(0.00);
            $table->integer('total_deliveries')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_available')->default(false);
            $table->timestamp('last_active_at')->nullable();
            $table->json('current_location')->nullable(); // lat, lng
            $table->timestamps();
            
            $table->index(['is_active', 'is_available']);
            $table->index('rating');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('delivery_partners');
    }
};
