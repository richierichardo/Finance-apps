<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_messages', function (Blueprint $table) {
            $table->unsignedInteger('prompt_tokens')->nullable()->after('token_usage');
            $table->unsignedInteger('completion_tokens')->nullable()->after('prompt_tokens');
            $table->unsignedInteger('total_tokens')->nullable()->after('completion_tokens');
            $table->string('provider', 32)->nullable()->after('total_tokens');
        });

        Schema::table('ai_training_examples', function (Blueprint $table) {
            $table->unsignedInteger('prompt_tokens')->nullable()->after('feedback_note');
            $table->unsignedInteger('completion_tokens')->nullable()->after('prompt_tokens');
            $table->unsignedInteger('total_tokens')->nullable()->after('completion_tokens');
            $table->string('provider', 32)->nullable()->after('total_tokens');
            $table->string('model')->nullable()->after('provider');
            $table->unsignedInteger('latency_ms')->nullable()->after('model');
            $table->boolean('model_called')->default(true)->after('latency_ms');
            $table->boolean('guardrail_blocked')->default(false)->after('model_called');
        });
    }

    public function down(): void
    {
        Schema::table('ai_messages', function (Blueprint $table) {
            $table->dropColumn(['prompt_tokens', 'completion_tokens', 'total_tokens', 'provider']);
        });

        Schema::table('ai_training_examples', function (Blueprint $table) {
            $table->dropColumn([
                'prompt_tokens',
                'completion_tokens',
                'total_tokens',
                'provider',
                'model',
                'latency_ms',
                'model_called',
                'guardrail_blocked',
            ]);
        });
    }
};
