<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        Schema::create('restaurant_owners', function (Blueprint $table) {
            // Primary key
            $table->id();

            // Foreign keys to other tables
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('restaurant_id')->constrained()->cascadeOnDelete();

            // Owner attributes
            $table->string('role')->default('manager'); // e.g., manager | owner | staff
            $table->boolean('is_active')->default(true);

            // Approval tracking columns
            $table->unsignedBigInteger('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();

            // Standard timestamps
            $table->timestamps();

            // Foreign key constraint for the approver
            $table->foreign('approved_by')->references('id')->on('users')->onDelete('set null');

            // Indexes for performance and data integrity
            $table->unique(['user_id', 'restaurant_id'], 'unique_owner_per_restaurant');
            $table->index(['restaurant_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down(): void
    {
        Schema::dropIfExists('restaurant_owners');
    }
};