<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('status_change_logs', function (Blueprint $table) {
            $table->id();

            // order / delivery
            $table->string('entity_type');
            $table->unsignedBigInteger('entity_id');

            $table->string('from_status');
            $table->string('to_status');

            // Who changed it
            $table->foreignId('changed_by')
                ->constrained('users');

            $table->string('role'); // admin / restaurant / delivery

            $table->timestamps();

            $table->index(['entity_type', 'entity_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('status_change_logs');
    }
};
