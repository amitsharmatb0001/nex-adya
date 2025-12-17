<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('admin_actions', function (Blueprint $table) {
            $table->id();

            // Which admin performed the action
            $table->foreignId('admin_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // What happened
            $table->string('action'); // e.g. REVIEW_DELETED, OWNER_APPROVED

            // What entity was affected
            $table->string('entity_type'); // review, order, restaurant
            $table->unsignedBigInteger('entity_id')->nullable();

            // Extra context
            $table->json('meta')->nullable();

            // Security info
            $table->ipAddress('ip');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_actions');
    }
};
