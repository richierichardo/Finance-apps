<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_training_examples', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source_channel', 32);
            $table->text('raw_user_input');
            $table->json('normalized_input')->nullable();
            $table->string('expected_intent')->nullable();
            $table->string('expected_action_type')->nullable();
            $table->json('model_output')->nullable();
            $table->json('final_action_payload')->nullable();
            $table->boolean('was_confirmed')->nullable();
            $table->boolean('was_successful')->nullable();
            $table->unsignedTinyInteger('feedback_rating')->nullable();
            $table->text('feedback_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_training_examples');
    }
};
