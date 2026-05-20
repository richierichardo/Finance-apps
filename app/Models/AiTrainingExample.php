<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiTrainingExample extends Model
{
    protected $fillable = [
        'user_id',
        'source_channel',
        'raw_user_input',
        'normalized_input',
        'expected_intent',
        'expected_action_type',
        'model_output',
        'final_action_payload',
        'was_confirmed',
        'was_successful',
        'feedback_rating',
        'feedback_note',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'provider',
        'model',
        'latency_ms',
        'model_called',
        'guardrail_blocked',
    ];

    protected function casts(): array
    {
        return [
            'normalized_input' => 'array',
            'model_output' => 'array',
            'final_action_payload' => 'array',
            'was_confirmed' => 'boolean',
            'was_successful' => 'boolean',
            'model_called' => 'boolean',
            'guardrail_blocked' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
