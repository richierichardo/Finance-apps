<?php

use App\Enums\WalletType;
use App\Models\User;
use App\Models\Wallet;
use App\Services\TransferService;
use Illuminate\Support\Str;

test('transfer service rejects wallets from different users', function () {
    $userA = User::factory()->create(['username' => 'ta_'.Str::random(8)]);
    $userB = User::factory()->create(['username' => 'tb_'.Str::random(8)]);

    $wA = Wallet::create([
        'user_id' => $userA->id,
        'name' => 'A',
        'type' => WalletType::Bank,
        'initial_balance' => 100,
        'balance' => 100,
        'is_active' => true,
    ]);

    $wB = Wallet::create([
        'user_id' => $userB->id,
        'name' => 'B',
        'type' => WalletType::Cash,
        'initial_balance' => 0,
        'balance' => 0,
        'is_active' => true,
    ]);

    $service = app(TransferService::class);

    $service->transfer($wA, $wB, 10, null, now()->toDateTimeString());
})->throws(\InvalidArgumentException::class);
