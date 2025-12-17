<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
PayoutTransaction (ledger) - every event that affects money
 - payout_id: nullable (some ledger entries may be generic)
 - kind: requested|approved|sent|provider_settled|adjustment|fee|refund
 - amount: signed integer in paise (+/-)
 - running_balance: optional snapshot after this txn
 - provider_data: provider response details
 - reference: optional external reference (bank reference, transfer id)
 - reconciled: boolean
*/
return new class extends Migration {
    public function up(): void
    {
        Schema::create('payout_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('payout_id')->nullable()->index();
            $table->string('kind', 50)->index();
            $table->bigInteger('amount')->comment('signed, in paise');
            $table->bigInteger('running_balance')->nullable()->comment('snapshot balance after txn (optional)');
            $table->json('provider_data')->nullable();
            $table->string('reference')->nullable();
            $table->boolean('reconciled')->default(false);
            $table->timestamps();

            $table->foreign('payout_id')->references('id')->on('payouts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payout_transactions');
    }
};
