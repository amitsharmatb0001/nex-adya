<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('order_id')->nullable()->constrained()->cascadeOnDelete();

            $t->string('provider')->default('mock'); // e.g. razorpay, phonepe
            $t->string('provider_payment_id')->nullable(); // gateway ID when real
            $t->string('status')->default('pending'); // pending, paid, failed, refunded

            $t->unsignedInteger('amount'); // in paise
            $t->string('currency', 10)->default('INR');

            $t->json('meta')->nullable(); // store raw response / debug data

            $t->timestamps();
            $t->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
