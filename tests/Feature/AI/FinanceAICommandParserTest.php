<?php

use App\Models\User;
use App\Models\Wallet;
use App\Enums\WalletType;
use App\Services\AI\FinanceAICommandParserService;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create(['username' => 'parser_'.Str::random(8)]);
    $this->gopay = Wallet::create([
        'user_id' => $this->user->id,
        'name' => 'GOPAY',
        'type' => WalletType::Ewallet,
        'initial_balance' => 100000,
        'balance' => 100000,
        'is_active' => true,
    ]);
    $this->shopee = Wallet::create([
        'user_id' => $this->user->id,
        'name' => 'SHOPEEPAY',
        'type' => WalletType::Ewallet,
        'initial_balance' => 200000,
        'balance' => 200000,
        'is_active' => true,
    ]);
    $this->parser = app(FinanceAICommandParserService::class);
    $this->context = [
        'user_id' => $this->user->id,
        'wallets' => [
            ['id' => $this->gopay->id, 'name' => 'GOPAY', 'balance' => 100000],
            ['id' => $this->shopee->id, 'name' => 'SHOPEEPAY', 'balance' => 200000],
        ],
    ];
});

test('transfer informal dr gopay ke shopeepay', function () {
    $result = $this->parser->parse(
        $this->user->id,
        'transfer 20 ribu dr gopay ke shopeepay',
        $this->context
    );

    expect($result['matched'])->toBeTrue()
        ->and($result['intent'])->toBe('parse_transfer')
        ->and($result['action_type'])->toBe('create_transfer')
        ->and($result['entities']['amount'])->toBe(20000)
        ->and($result['entities']['from_wallet_name'])->toBe('GOPAY')
        ->and($result['entities']['to_wallet_name'])->toBe('SHOPEEPAY')
        ->and($result['confidence'])->toBeGreaterThanOrEqual(0.9)
        ->and($result['model_called'])->toBeFalse()
        ->and($result['missing_fields'])->toBeEmpty();
});

test('transfer abbreviation tf 50rb', function () {
    $result = $this->parser->parse(
        $this->user->id,
        'tf 50rb dari gopay ke shopeepay',
        $this->context
    );

    expect($result['entities']['amount'])->toBe(50000)
        ->and($result['confidence'])->toBeGreaterThanOrEqual(0.9);
});

test('specific wallet saldo gopay', function () {
    $result = $this->parser->parse($this->user->id, 'saldo gopay berapa?', $this->context);

    expect($result['intent'])->toBe('ask_specific_wallet_balance')
        ->and($result['entities']['wallet_name'])->toBe('GOPAY')
        ->and($result['model_called'])->toBeFalse();
});

test('all wallet saldo gue berapa', function () {
    $result = $this->parser->parse($this->user->id, 'saldo gue berapa?', $this->context);

    expect($result['intent'])->toBe('ask_wallet_balance');
});

test('transaction expense catat pengeluaran', function () {
    $result = $this->parser->parse(
        $this->user->id,
        'catat pengeluaran 25 ribu dari gopay buat kopi',
        $this->context
    );

    expect($result['intent'])->toBe('parse_transaction')
        ->and($result['entities']['transaction_type'])->toBe('expense')
        ->and($result['entities']['amount'])->toBe(25000)
        ->and($result['entities']['wallet_name'])->toBe('GOPAY')
        ->and($result['confidence'])->toBeGreaterThanOrEqual(0.9);
});

test('transaction beli makan with make gopay', function () {
    $result = $this->parser->parse(
        $this->user->id,
        'beli makan 50 ribu make gopay',
        $this->context
    );

    expect($result['intent'])->toBe('parse_transaction')
        ->and($result['entities']['transaction_type'])->toBe('expense')
        ->and($result['entities']['amount'])->toBe(50000)
        ->and($result['entities']['wallet_name'])->toBe('GOPAY')
        ->and($result['confidence'])->toBeGreaterThanOrEqual(0.9);
});

test('transaction income', function () {
    $result = $this->parser->parse(
        $this->user->id,
        'income 200 ribu ke shopeepay dari freelance',
        $this->context
    );

    expect($result['intent'])->toBe('parse_transaction')
        ->and($result['entities']['transaction_type'])->toBe('income')
        ->and($result['entities']['amount'])->toBe(200000)
        ->and($result['entities']['wallet_name'])->toBe('SHOPEEPAY');
});

test('create wallet cash uang dompet', function () {
    $result = $this->parser->parse(
        $this->user->id,
        'buat wallet baru type cash dengan nama uang dompet, 50 ribu',
        $this->context
    );

    expect($result['intent'])->toBe('parse_wallet')
        ->and($result['action_type'])->toBe('create_wallet')
        ->and($result['entities']['wallet_name'])->toBe('uang dompet')
        ->and($result['entities']['wallet_type'])->toBe('cash')
        ->and($result['entities']['initial_balance'])->toBe(50000)
        ->and($result['confidence'])->toBeGreaterThanOrEqual(0.9);
});

test('budget makan 1.5 juta', function () {
    $result = $this->parser->parse(
        $this->user->id,
        'budget makan bulan ini 1.5 juta',
        $this->context
    );

    expect($result['intent'])->toBe('parse_budget')
        ->and($result['entities']['amount'])->toBe(1500000);
});

test('recurring netflix monthly', function () {
    $result = $this->parser->parse(
        $this->user->id,
        'buat recurring netflix 150 ribu tiap bulan dari gopay',
        $this->context
    );

    expect($result['intent'])->toBe('parse_recurring')
        ->and($result['entities']['amount'])->toBe(150000)
        ->and($result['entities']['wallet_name'])->toBe('GOPAY')
        ->and($result['entities']['frequency'])->toBe('monthly');
});
