<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_conversation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('role', 16);
            $table->string('channel', 32);
            $table->longText('content')->nullable();
            $table->json('structured_input')->nullable();
            $table->json('structured_output')->nullable();
            $table->string('intent')->nullable();
            $table->string('action_type')->nullable();
            $table->json('safety_flags')->nullable();
            $table->json('token_usage')->nullable();
            $table->string('model')->nullable();
            $table->unsignedInteger('latency_ms')->nullable();
            $table->timestamps();

            $table->index(['ai_conversation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_messages');
    }
};
