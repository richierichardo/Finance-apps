<?php

use App\Models\User;
use App\Models\Wallet;
use App\Enums\WalletType;
use App\Services\Telegram\TelegramFastReplyService;
use Illuminate\Support\Str;

test('fast reply returns single wallet for saldo gopay query', function () {
    $user = User::factory()->create(['username' => 'tg_'.Str::random(8)]);
    Wallet::create([
        'user_id' => $user->id,
        'name' => 'GOPAY',
        'type' => WalletType::Ewallet,
        'initial_balance' => 100000,
        'balance' => 100000,
        'is_active' => true,
    ]);
    Wallet::create([
        'user_id' => $user->id,
        'name' => 'SHOPEEPAY',
        'type' => WalletType::Ewallet,
        'initial_balance' => 200000,
        'balance' => 200000,
        'is_active' => true,
    ]);

    $service = app(TelegramFastReplyService::class);
    $reply = $service->tryFastReply($user->id, 'Saldo gopay berapa?', '123');

    expect($reply)->toContain('GOPAY')
        ->and($reply)->toContain('Rp 100.000')
        ->and($reply)->not->toContain('SHOPEEPAY');
});

test('fast reply returns all wallets for general saldo query', function () {
    $user = User::factory()->create(['username' => 'tg2_'.Str::random(8)]);
    Wallet::create([
        'user_id' => $user->id,
        'name' => 'GOPAY',
        'type' => WalletType::Ewallet,
        'initial_balance' => 100000,
        'balance' => 100000,
        'is_active' => true,
    ]);

    $service = app(TelegramFastReplyService::class);
    $reply = $service->tryFastReply($user->id, 'Saldo gue berapa', '123');

    expect($reply)->toContain('GOPAY')
        ->and($reply)->toContain('Rp');
});
