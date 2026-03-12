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
        Schema::create('wallets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 191);
            $table->string('type', 50); // bank, ewallet, cash
            $table->decimal('initial_balance', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->default(0)->nullable(); // cache, sync via event
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Composite index untuk query user + filter active (user_id sudah ter-index dari FK)
        Schema::table('wallets', function (Blueprint $table) {
            $table->index(['user_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallets');
    }
};
