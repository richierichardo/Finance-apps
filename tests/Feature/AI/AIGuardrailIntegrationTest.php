<?php

use App\Models\User;
use App\Models\Wallet;
use App\Enums\WalletType;
use App\Services\AI\FinanceAIOrchestratorService;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create(['username' => 'guard_'.Str::random(8)]);
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

test('orchestrator blocks out of scope without model call', function () {
    $result = app(FinanceAIOrchestratorService::class)->handleUserMessage(
        $this->user->id,
        'bagaimana cara buat nasi goreng',
        'dashboard'
    );

    expect($result['type'])->toBe('blocked')
        ->and($result['structured']['model_called'])->toBeFalse()
        ->and($result['structured']['guardrail_blocked'])->toBeTrue()
        ->and($result['structured']['token_usage']['total_tokens'])->toBe(0)
        ->and($result['message'])->not->toContain('bawang');
});

test('orchestrator returns specific wallet balance without listing all', function () {
    $result = app(FinanceAIOrchestratorService::class)->handleUserMessage(
        $this->user->id,
        'Saldo gopay berapa?',
        'dashboard'
    );

    expect($result['type'])->toBe('answer')
        ->and($result['message'])->toContain('GOPAY')
        ->and($result['message'])->toContain('Rp 100.000')
        ->and($result['message'])->not->toContain('SHOPEEPAY');
});

test('orchestrator creates transfer draft from slang input', function () {
    $result = app(FinanceAIOrchestratorService::class)->handleUserMessage(
        $this->user->id,
        'Transfer 20 ribu dri gopay ke shopeepay',
        'dashboard'
    );

    expect($result['type'])->toBe('confirmation_required')
        ->and($result['structured']['action_type'])->toBe('create_transfer')
        ->and($result['structured']['payload']['amount'])->toBe(20000.0);
});

test('orchestrator creates wallet draft', function () {
    $result = app(FinanceAIOrchestratorService::class)->handleUserMessage(
        $this->user->id,
        'Buat wallet baru type cash dengan nama uang dompet, 50 ribu',
        'dashboard'
    );

    expect($result['type'])->toBe('confirmation_required')
        ->and($result['structured']['action_type'])->toBe('create_wallet')
        ->and($result['structured']['payload']['name'])->toBe('uang dompet')
        ->and($result['structured']['payload']['type'])->toBe('cash')
        ->and($result['structured']['payload']['initial_balance'])->toBe(50000.0);
});
