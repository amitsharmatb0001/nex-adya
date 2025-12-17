<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('security_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $t->string('action'); // login_fail, order_spike, payment_fail
            $t->string('reason')->nullable();
            $t->string('ip_address')->nullable();
            $t->json('metadata')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('security_logs');
    }
};
