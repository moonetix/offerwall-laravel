<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Coins in millionths, as a signed integer: exact on every database,
            // and a reversal of coins the user already spent may take it below zero.
            $table->bigInteger('balance_micro')->default(0);
            // Set when the user confirms they are 16 or older. No offerwall link before that.
            $table->timestamp('age_confirmed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['balance_micro', 'age_confirmed_at']);
        });
    }
};
