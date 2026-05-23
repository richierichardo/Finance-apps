<?php

use App\Models\AiActionDraft;
use App\Models\User;
use App\Services\AI\FinanceAIOrchestratorService;
use App\Services\AI\QwenAIClientService;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create(['username' => 'worch_'.Str::random(8)]);
});

test('orchestrator complete wallet create without qwen on telegram', function () {
    $qwen = Mockery::mock(QwenAIClientService::class);
    $qwen->shouldNotReceive('chat');
    $this->app->instance(QwenAIClientService::class, $qwen);

    $result = app(FinanceAIOrchestratorService::class)->handleUserMessage(
        $this->user->id,
        'buat wallet e-wallet nama gopay saldo 348.455',
        'telegram',
        ['telegram_chat_id' => '12345']
    );

    expect($result['type'])->toBe('confirmation_required')
        ->and($result['structured']['action_type'])->toBe('create_wallet')
        ->and($result['message'])->toContain('gopay')
        ->and($result['draft_id'])->not->toBeNull();
});

test('orchestrator complete wallet create without qwen on dashboard', function () {
    $qwen = Mockery::mock(QwenAIClientService::class);
    $qwen->shouldNotReceive('chat');
    $this->app->instance(QwenAIClientService::class, $qwen);

    $result = app(FinanceAIOrchestratorService::class)->handleUserMessage(
        $this->user->id,
        'buat wallet e-wallet nama gopay saldo 348.455',
        'dashboard'
    );

    expect($result['type'])->toBe('confirmation_required')
        ->and($result['structured']['action_type'])->toBe('create_wallet');
});

test('orchestrator partial wallet asks only wallet type', function () {
    $qwen = Mockery::mock(QwenAIClientService::class);
    $qwen->shouldNotReceive('chat');
    $this->app->instance(QwenAIClientService::class, $qwen);

    $result = app(FinanceAIOrchestratorService::class)->handleUserMessage(
        $this->user->id,
        'buat wallet gopay 348.455',
        'telegram',
        ['telegram_chat_id' => '12346']
    );

    expect($result['type'])->toBe('clarification')
        ->and($result['message'])->toContain('Tipe wallet')
        ->and($result['message'])->not->toContain('Contoh: buat wallet cash');
});

test('orchestrator slot fill e-wallet after partial', function () {
    $qwen = Mockery::mock(QwenAIClientService::class);
    $qwen->shouldNotReceive('chat');
    $this->app->instance(QwenAIClientService::class, $qwen);

    $orchestrator = app(FinanceAIOrchestratorService::class);
    $chatId = '12347';

    $partial = $orchestrator->handleUserMessage(
        $this->user->id,
        'buat wallet gopay 348.455',
        'telegram',
        ['telegram_chat_id' => $chatId]
    );

    expect($partial['type'])->toBe('clarification')
        ->and($partial['draft_id'])->not->toBeNull();

    $collecting = AiActionDraft::find($partial['draft_id']);
    expect($collecting->status)->toBe(AiActionDraft::STATUS_COLLECTING);

    $confirm = $orchestrator->handleUserMessage(
        $this->user->id,
        'e-wallet',
        'telegram',
        ['telegram_chat_id' => $chatId]
    );

    expect($confirm['type'])->toBe('confirmation_required')
        ->and($confirm['message'])->toContain('E-Wallet')
        ->and($confirm['message'])->toContain('348.455');
});
