<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('wallet_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // income, expense, transfer_out, transfer_in
            $table->decimal('amount', 15, 2)->unsigned();
            $table->text('description')->nullable();
            $table->string('source', 32)->default('web_manual');
            $table->unsignedBigInteger('reference_id')->nullable(); // untuk transfer pair
            $table->timestamp('occurred_at');
            $table->timestamps();
        });

        // Self-reference FK untuk reference_id (transfer pair)
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreign('reference_id')->references('id')->on('transactions')->nullOnDelete();
        });

        // Index untuk query laporan dan filter (wallet_id, user_id sudah ter-index dari FK)
        Schema::table('transactions', function (Blueprint $table) {
            $table->index('occurred_at');
            $table->index('type');
            $table->index('source');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
