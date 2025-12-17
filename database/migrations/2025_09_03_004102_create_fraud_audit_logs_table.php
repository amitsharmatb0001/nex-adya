<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('fraud_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('event'); // login_fail, order_spam, payment_fail, etc.
            $table->json('metadata')->nullable(); // store IP, device, payload snapshot
            $table->string('status')->default('info'); // info | warning | blocked
            $table->timestamps();

            $table->index(['user_id', 'event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fraud_audit_logs');
    }
};
