<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('financial_ledgers', function (Blueprint $table) {
            $table->id();

            $table->string('type'); // credit / debit
            $table->string('source'); // order, payout, refund
            $table->unsignedBigInteger('source_id');

            $table->decimal('amount', 10, 2);

            $table->foreignId('user_id')->nullable();
            $table->foreignId('restaurant_id')->nullable();

            $table->timestamps();

            $table->index(['source', 'source_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_ledgers');
    }
};
