<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::create('restaurants', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('slug')->unique();
        $table->string('city');
        $table->string('state')->nullable();
        $table->string('image')->nullable();
        $table->decimal('rating', 2, 1)->default(0);
        $table->integer('eta_minutes')->default(30);
        $table->string('cuisine')->nullable();
        $table->boolean('is_active')->default(true);
        $table->timestamps();

        // Add search indexes
        $table->index(['city', 'is_active'], 'restaurants_city_is_active_index');
        $table->index('name', 'restaurants_name_index');
        $table->index('eta_minutes', 'restaurants_eta_index');
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('restaurants');
    }
};
