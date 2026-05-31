<?php

use App\Enums\WalletType;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create(['username' => 'u_'.Str::random(8)]);
    $this->other = User::factory()->create(['username' => 'o_'.Str::random(8)]);
});

test('wallet routes require authentication', function () {
    $this->get(route('wallets.index'))->assertRedirect(route('login'));
});

test('guest cannot sync wallets', function () {
    $this->post(route('wallets.sync'))->assertRedirect(route('login'));
});

test('authenticated user can create wallet and is redirected to show', function () {
    $response = $this->actingAs($this->user)->post(route('wallets.store'), [
        'name' => 'Main Bank',
        'type' => WalletType::Bank->value,
        'initial_balance' => 100,
    ]);

    $wallet = Wallet::where('user_id', $this->user->id)->where('name', 'Main Bank')->first();
    expect($wallet)->not->toBeNull()
        ->and((float) $wallet->initial_balance)->toBe(100.0);

    $response->assertRedirect(route('wallets.show', $wallet));
});

test('creating wallet with initial balance stores system_initial_balance source', function () {
    $this->actingAs($this->user)->post(route('wallets.store'), [
        'name' => 'BCA 7106',
        'type' => WalletType::Bank->value,
        'initial_balance' => 4_809_142,
    ])->assertRedirect();

    $wallet = Wallet::where('user_id', $this->user->id)->where('name', 'BCA 7106')->first();

    expect($wallet)->not->toBeNull()
        ->and((float) $wallet->initial_balance)->toBe(4809142.0);

    $adjustment = $wallet->transactions()->where('description', 'Initial balance')->first();

    expect($adjustment)->not->toBeNull()
        ->and((float) $adjustment->amount)->toBe(4809142.0)
        ->and($adjustment->source->value)->toBe('system_initial_balance');
});

test('wallet store validation rejects missing name', function () {
    $response = $this->actingAs($this->user)->from(route('wallets.create'))
        ->post(route('wallets.store'), [
            'type' => WalletType::Cash->value,
        ]);

    $response->assertSessionHasErrors('name');
});

test('user cannot view another users wallet', function () {
    $wallet = Wallet::create([
        'user_id' => $this->other->id,
        'name' => 'Other wallet',
        'type' => WalletType::Cash,
        'initial_balance' => 0,
        'balance' => 0,
        'is_active' => true,
    ]);

    $this->actingAs($this->user)->get(route('wallets.show', $wallet))->assertForbidden();
});

test('user cannot update another users wallet', function () {
    $wallet = Wallet::create([
        'user_id' => $this->other->id,
        'name' => 'Other wallet',
        'type' => WalletType::Cash,
        'initial_balance' => 0,
        'balance' => 0,
        'is_active' => true,
    ]);

    $this->actingAs($this->user)->put(route('wallets.update', $wallet), [
        'name' => 'Hacked',
        'type' => WalletType::Bank->value,
    ])->assertForbidden();
});

test('user can update own wallet', function () {
    $wallet = Wallet::create([
        'user_id' => $this->user->id,
        'name' => 'Mine',
        'type' => WalletType::Cash,
        'initial_balance' => 50,
        'balance' => 50,
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)->from(route('wallets.edit', $wallet))
        ->put(route('wallets.update', $wallet), [
            'name' => 'Renamed',
            'type' => WalletType::Bank->value,
        ]);

    $response->assertRedirect(route('wallets.show', $wallet));
    expect($wallet->fresh()->name)->toBe('Renamed');
});

test('updating initial balance syncs adjustment transaction and wallet balance', function () {
    $wallet = Wallet::create([
        'user_id' => $this->user->id,
        'name' => 'GOPAY',
        'type' => WalletType::Ewallet,
        'initial_balance' => 348455,
        'balance' => 348455,
        'is_active' => true,
    ]);

    $this->actingAs($this->user)->from(route('wallets.edit', $wallet))
        ->put(route('wallets.update', $wallet), [
            'name' => 'GOPAY',
            'type' => WalletType::Ewallet->value,
            'initial_balance' => 79909,
        ])
        ->assertRedirect(route('wallets.show', $wallet));

    $wallet->refresh();

    expect((float) $wallet->initial_balance)->toBe(79909.0)
        ->and((float) $wallet->balance)->toBe(79909.0);

    $adjustment = $wallet->transactions()
        ->where('description', 'Initial balance')
        ->first();

    expect($adjustment)->not->toBeNull()
        ->and((float) $adjustment->amount)->toBe(79909.0);
});

test('user can sync balances for own wallets', function () {
    $wallet = Wallet::create([
        'user_id' => $this->user->id,
        'name' => 'Sync me',
        'type' => WalletType::Bank,
        'initial_balance' => 10,
        'balance' => 10,
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)->from(route('wallets.index'))
        ->post(route('wallets.sync'));

    $response->assertRedirect();
    $response->assertSessionHas('success');
});
