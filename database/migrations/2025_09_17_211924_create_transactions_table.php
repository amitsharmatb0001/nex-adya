<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTransactionsTable extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payout_id')->nullable()->constrained()->onDelete('cascade');
            $table->foreignId('restaurant_id')->constrained()->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('type'); // payout, deposit, refund
            $table->integer('amount'); // in paise
            $table->string('currency', 3)->default('INR');
            $table->string('reference')->unique();
            $table->json('meta')->nullable();
            $table->timestamps();
            
            $table->index(['type', 'created_at']);
            $table->index(['restaurant_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
}
