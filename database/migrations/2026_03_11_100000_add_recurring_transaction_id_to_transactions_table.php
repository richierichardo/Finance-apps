<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Links transactions created from recurring templates for AI Insight aggregation.
     */
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('recurring_transaction_id')
                ->nullable()
                ->after('reference_id')
                ->constrained('recurring_transactions')
                ->nullOnDelete();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->index('recurring_transaction_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['recurring_transaction_id']);
        });
    }
};
