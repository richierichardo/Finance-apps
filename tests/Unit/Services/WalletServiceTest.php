<?php

use App\Enums\WalletType;
use App\Models\User;
use App\Models\Wallet;
use App\Services\WalletService;
use Illuminate\Support\Str;

test('wallet service create update and delete', function () {
    $user = User::factory()->create(['username' => 'ws_'.Str::random(8)]);
    $service = app(WalletService::class);

    $wallet = $service->create([
        'user_id' => $user->id,
        'name' => 'Svc Wallet',
        'type' => WalletType::Bank,
        'initial_balance' => 0,
        'balance' => 0,
        'is_active' => true,
    ]);

    expect($wallet)->toBeInstanceOf(Wallet::class)
        ->and($wallet->name)->toBe('Svc Wallet');

    $service->update($wallet, ['name' => 'Renamed']);
    expect($wallet->fresh()->name)->toBe('Renamed');

    $service->delete($wallet);
    expect(Wallet::find($wallet->id))->toBeNull();
});
