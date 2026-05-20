<?php

use App\Models\User;
use App\Models\Wallet;
use App\Enums\WalletType;
use App\Services\AI\FinanceAIOrchestratorService;
use App\Services\AI\QwenAIClientService;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create(['username' => 'orch_'.Str::random(8)]);
    Wallet::create([
        'user_id' => $this->user->id,
        'name' => 'GOPAY',
        'type' => WalletType::Ewallet,
        'initial_balance' => 100000,
        'balance' => 100000,
        'is_active' => true,
    ]);
    Wallet::create([
        'user_id' => $this->user->id,
        'name' => 'SHOPEEPAY',
        'type' => WalletType::Ewallet,
        'initial_balance' => 50000,
        'balance' => 50000,
        'is_active' => true,
    ]);
});

test('orchestrator transfer creates draft without calling qwen', function () {
    $qwen = Mockery::mock(QwenAIClientService::class);
    $qwen->shouldNotReceive('chat');
    $this->app->instance(QwenAIClientService::class, $qwen);

    $result = app(FinanceAIOrchestratorService::class)->handleUserMessage(
        $this->user->id,
        'transfer 20 ribu dr gopay ke shopeepay',
        'telegram'
    );

    expect($result['type'])->toBe('confirmation_required')
        ->and($result['structured']['action_type'])->toBe('create_transfer')
        ->and($result['structured']['payload']['amount'])->toBe(20000.0)
        ->and($result['draft_id'])->not->toBeNull();
});

test('orchestrator beli makan does not trigger investment block', function () {
    $qwen = Mockery::mock(QwenAIClientService::class);
    $qwen->shouldNotReceive('chat');
    $this->app->instance(QwenAIClientService::class, $qwen);

    $result = app(FinanceAIOrchestratorService::class)->handleUserMessage(
        $this->user->id,
        'beli makan 50 ribu make gopay',
        'telegram'
    );

    expect($result['type'])->toBe('confirmation_required')
        ->and($result['message'])->not->toContain('saham')
        ->and($result['structured']['action_type'])->toBe('create_transaction');
});

test('orchestrator create wallet draft not balance list', function () {
    $qwen = Mockery::mock(QwenAIClientService::class);
    $qwen->shouldNotReceive('chat');
    $this->app->instance(QwenAIClientService::class, $qwen);

    $result = app(FinanceAIOrchestratorService::class)->handleUserMessage(
        $this->user->id,
        'Buat wallet baru type cash dengan nama uang dompet, 50 ribu',
        'telegram'
    );

    expect($result['type'])->toBe('confirmation_required')
        ->and($result['structured']['action_type'])->toBe('create_wallet')
        ->and($result['message'])->toContain('uang dompet')
        ->and($result['message'])->not->toContain('Rinciannya');
});

test('orchestrator specific wallet balance without qwen', function () {
    $qwen = Mockery::mock(QwenAIClientService::class);
    $qwen->shouldNotReceive('chat');
    $this->app->instance(QwenAIClientService::class, $qwen);

    $result = app(FinanceAIOrchestratorService::class)->handleUserMessage(
        $this->user->id,
        'Saldo gopay berapa?',
        'telegram'
    );

    expect($result['type'])->toBe('answer')
        ->and($result['message'])->toContain('GOPAY')
        ->and($result['message'])->toContain('Rp 100.000')
        ->and($result['message'])->not->toContain('SHOPEEPAY');
});
