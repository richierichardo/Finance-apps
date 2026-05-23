<?php

use App\Models\User;
use App\Models\Wallet;
use App\Enums\WalletType;
use App\Services\AI\FinanceAIIntentService;
use App\Services\AI\QwenAIClientService;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create(['username' => 'intent_'.Str::random(8)]);
    Wallet::create([
        'user_id' => $this->user->id,
        'name' => 'GOPAY',
        'type' => WalletType::Ewallet,
        'initial_balance' => 100000,
        'balance' => 100000,
        'is_active' => true,
    ]);
    $this->context = [
        'user_id' => $this->user->id,
        'wallets' => [['id' => 1, 'name' => 'GOPAY', 'balance' => 100000]],
    ];
    $this->mock(QwenAIClientService::class, function ($mock) {
        $mock->shouldNotReceive('chat');
    });
    $this->intentService = app(FinanceAIIntentService::class);
});

test('rule parser detects specific wallet balance intent', function () {
    $intent = $this->intentService->ruleBasedParse($this->user->id, 'saldo gopay berapa?', $this->context);

    expect($intent['intent'])->toBe('ask_specific_wallet_balance')
        ->and($intent['entities']['wallet_name'])->toBe('GOPAY');
});

test('rule parser detects transfer with slang', function () {
    $intent = $this->intentService->ruleBasedParse(
        $this->user->id,
        'Transfer 20 ribu dri gopay ke shopeepay',
        [
            'user_id' => $this->user->id,
            'wallets' => [
                ['id' => 1, 'name' => 'GOPAY', 'balance' => 100000],
                ['id' => 2, 'name' => 'SHOPEEPAY', 'balance' => 200000],
            ],
        ]
    );

    expect($intent['intent'])->toBe('parse_transfer')
        ->and($intent['entities']['amount'])->toBe(20000);
});

test('rule parser detects confirm intent', function () {
    $intent = $this->intentService->ruleBasedParse($this->user->id, 'yes', $this->context);

    expect($intent['intent'])->toBe('confirm');
});
