<?php

use App\Models\User;
use App\Models\Wallet;
use App\Enums\WalletType;
use Illuminate\Support\Str;

beforeEach(function () {
    config([
        'ai.access.feature_enabled' => true,
        'ai.access.public_access' => false,
        'ai.access.allowed_user_ids' => [],
    ]);
});

test('member without ai access gets 403 on chat', function () {
    $user = User::factory()->create(['ai_enabled' => false]);
    Wallet::create([
        'user_id' => $user->id,
        'name' => 'Cash',
        'type' => WalletType::Cash,
        'initial_balance' => 0,
        'balance' => 0,
        'is_active' => true,
    ]);

    $this->actingAs($user)
        ->postJson(route('ai.chat'), ['message' => 'hello'])
        ->assertForbidden()
        ->assertJsonPath('code', 'ai_access_denied');
});

test('owner can access ai chat endpoint', function () {
    $user = User::factory()->owner()->create(['username' => 'own_'.Str::random(6)]);

    $this->mock(\App\Services\AI\FinanceAIOrchestratorService::class, function ($mock) {
        $mock->shouldReceive('handleUserMessage')
            ->once()
            ->andReturn(['ok' => true, 'type' => 'answer', 'message' => 'ok']);
    });

    $this->actingAs($user)
        ->postJson(route('ai.chat'), ['message' => 'saldo'])
        ->assertOk();
});

test('insights generate requires ai access', function () {
    $user = User::factory()->create(['ai_enabled' => false]);

    $this->actingAs($user)
        ->postJson(route('insights.generate'))
        ->assertForbidden();
});
