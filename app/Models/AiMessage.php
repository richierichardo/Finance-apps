<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiMessage extends Model
{
    protected $fillable = [
        'ai_conversation_id',
        'user_id',
        'role',
        'channel',
        'content',
        'structured_input',
        'structured_output',
        'intent',
        'action_type',
        'safety_flags',
        'token_usage',
        'prompt_tokens',
        'completion_tokens',
        'total_tokens',
        'provider',
        'model',
        'latency_ms',
    ];

    protected function casts(): array
    {
        return [
            'structured_input' => 'array',
            'structured_output' => 'array',
            'safety_flags' => 'array',
            'token_usage' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiConversation::class, 'ai_conversation_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
