<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The rewards ledger: one row per Moonetix transaction_id.
        Schema::create('moonetix_transactions', function (Blueprint $table) {
            $table->id();
            // The unique key is what makes a retried or duplicated postback harmless.
            $table->string('transaction_id', 191)->unique();
            // The user_id from the postback, as text. Not a foreign key, so a reversal
            // for an unknown or deleted user can still be recorded.
            $table->string('wall_user_id', 100)->index();
            $table->bigInteger('reward_micro');
            // 1 = credited, 2 = reversed (or reversed before any credit arrived).
            $table->unsignedTinyInteger('status');
            $table->string('offer_name', 200)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moonetix_transactions');
    }
};
