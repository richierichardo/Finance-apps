<?php

use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\User;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create(['username' => 'usage_'.Str::random(8)]);
    $conversation = AiConversation::create([
        'user_id' => $this->user->id,
        'channel' => 'dashboard',
        'status' => 'active',
    ]);

    AiMessage::create([
        'ai_conversation_id' => $conversation->id,
        'user_id' => $this->user->id,
        'role' => 'assistant',
        'channel' => 'dashboard',
        'content' => 'test',
        'prompt_tokens' => 100,
        'completion_tokens' => 50,
        'total_tokens' => 150,
        'provider' => 'qwen',
        'model' => 'qwen-plus',
    ]);
});

test('usage summary requires auth', function () {
    $this->getJson(route('ai.usage-summary'))->assertUnauthorized();
});

test('usage summary returns aggregates for current user', function () {
    $this->actingAs($this->user)
        ->getJson(route('ai.usage-summary'))
        ->assertOk()
        ->assertJsonPath('this_month.total_tokens', 150)
        ->assertJsonPath('this_month.requests', 1);
});
