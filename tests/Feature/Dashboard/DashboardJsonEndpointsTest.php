<?php

use App\Enums\TransactionCategory;
use App\Enums\TransactionType;
use App\Enums\WalletType;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

beforeEach(function () {
    Cache::flush();

    $this->user = User::factory()->create(['username' => 'du_'.Str::random(8)]);
    $this->other = User::factory()->create(['username' => 'do_'.Str::random(8)]);

    $this->wallet = Wallet::create([
        'user_id' => $this->user->id,
        'name' => 'Main',
        'type' => WalletType::Bank,
        'initial_balance' => 0,
        'balance' => 0,
        'is_active' => true,
    ]);

    $this->category = Category::firstOrCreate(
        ['slug' => TransactionCategory::Food->value],
        ['name' => 'Food']
    );
});

test('dashboard json routes require authentication', function (string $uri) {
    $this->getJson($uri)->assertUnauthorized();
})->with([
    ['/dashboard/summary'],
    ['/dashboard/cashflow'],
    ['/dashboard/category-breakdown'],
    ['/dashboard/wallet-distribution'],
    ['/dashboard/daily-expense'],
    ['/dashboard/top-expenses'],
    ['/dashboard/budgets'],
    ['/dashboard/upcoming-recurring'],
]);

test('summary is scoped to authenticated user', function () {
    $otherWallet = Wallet::create([
        'user_id' => $this->other->id,
        'name' => 'Other',
        'type' => WalletType::Cash,
        'initial_balance' => 0,
        'balance' => 0,
        'is_active' => true,
    ]);

    Transaction::create([
        'user_id' => $this->user->id,
        'wallet_id' => $this->wallet->id,
        'type' => TransactionType::Income,
        'amount' => 100,
        'source' => 'web',
        'occurred_at' => now(),
    ]);

    Transaction::create([
        'user_id' => $this->other->id,
        'wallet_id' => $otherWallet->id,
        'type' => TransactionType::Income,
        'amount' => 99999,
        'source' => 'web',
        'occurred_at' => now(),
    ]);

    $json = $this->actingAs($this->user)->getJson('/dashboard/summary')->assertOk()->json();

    expect((float) $json['total_income'])->toBe(100.0)
        ->and((float) $json['total_expense'])->toBe(0.0);
});

test('cashflow rejects invalid period', function () {
    $this->actingAs($this->user)
        ->getJson('/dashboard/cashflow?period=yearly')
        ->assertStatus(422)
        ->assertJsonStructure(['errors' => ['period']]);
});

test('cashflow returns json for daily and monthly', function (string $period) {
    Transaction::create([
        'user_id' => $this->user->id,
        'wallet_id' => $this->wallet->id,
        'type' => TransactionType::Expense,
        'amount' => 10,
        'category_transaction' => TransactionCategory::Food->value,
        'source' => 'web',
        'occurred_at' => now(),
    ]);

    $response = $this->actingAs($this->user)->getJson('/dashboard/cashflow?period='.$period);

    $response->assertOk();
    expect($response->json())->toBeArray();
})->with(['daily', 'monthly']);

test('category breakdown does not include other users spending', function () {
    $otherWallet = Wallet::create([
        'user_id' => $this->other->id,
        'name' => 'OW',
        'type' => WalletType::Cash,
        'initial_balance' => 0,
        'balance' => 0,
        'is_active' => true,
    ]);

    Transaction::create([
        'user_id' => $this->user->id,
        'wallet_id' => $this->wallet->id,
        'type' => TransactionType::Expense,
        'amount' => 20,
        'category_transaction' => TransactionCategory::Food->value,
        'source' => 'web',
        'occurred_at' => now(),
    ]);

    Transaction::create([
        'user_id' => $this->other->id,
        'wallet_id' => $otherWallet->id,
        'type' => TransactionType::Expense,
        'amount' => 5000,
        'category_transaction' => TransactionCategory::Food->value,
        'source' => 'web',
        'occurred_at' => now(),
    ]);

    $rows = $this->actingAs($this->user)->getJson('/dashboard/category-breakdown')->assertOk()->json();

    $total = (float) collect($rows)->sum('total');
    expect($total)->toBe(20.0);
});

test('wallet distribution lists only current user active wallets', function () {
    Wallet::create([
        'user_id' => $this->other->id,
        'name' => 'Secret',
        'type' => WalletType::Cash,
        'initial_balance' => 0,
        'balance' => 999,
        'is_active' => true,
    ]);

    $rows = $this->actingAs($this->user)->getJson('/dashboard/wallet-distribution')->assertOk()->json();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['wallet_name'])->toBe('Main');
});

test('top expenses and daily expense return arrays', function () {
    Transaction::create([
        'user_id' => $this->user->id,
        'wallet_id' => $this->wallet->id,
        'type' => TransactionType::Expense,
        'amount' => 42,
        'category_transaction' => TransactionCategory::Food->value,
        'source' => 'web',
        'occurred_at' => now(),
    ]);

    $this->actingAs($this->user)->getJson('/dashboard/daily-expense')->assertOk()->json();
    $top = $this->actingAs($this->user)->getJson('/dashboard/top-expenses')->assertOk()->json();

    expect($top)->not->toBeEmpty();
});

test('dashboard budgets endpoint matches budget rows for user', function () {
    Budget::create([
        'user_id' => $this->user->id,
        'category_id' => $this->category->id,
        'amount' => 1000,
        'period' => 'monthly',
        'start_date' => now()->startOfMonth(),
    ]);

    $rows = $this->actingAs($this->user)->getJson('/dashboard/budgets')->assertOk()->json();

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['category'])->toBe('Food');
});

test('summary response is stable across repeated requests when cache warm', function () {
    Cache::flush();

    Transaction::create([
        'user_id' => $this->user->id,
        'wallet_id' => $this->wallet->id,
        'type' => TransactionType::Income,
        'amount' => 77,
        'source' => 'web',
        'occurred_at' => now(),
    ]);

    $a = $this->actingAs($this->user)->getJson('/dashboard/summary')->assertOk()->json();
    $b = $this->actingAs($this->user)->getJson('/dashboard/summary')->assertOk()->json();

    expect($a)->toBe($b);
});
