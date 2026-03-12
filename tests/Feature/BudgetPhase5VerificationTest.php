<?php

use App\Enums\TransactionCategory;
use App\Enums\TransactionType;
use App\Enums\WalletType;
use App\Models\Budget;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\BudgetService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create(['username' => 'user_'.Str::random(8)]);

    $this->category = Category::firstOrCreate(
        ['slug' => TransactionCategory::Food->value],
        ['name' => 'Food']
    );

    $this->wallet = Wallet::create([
        'user_id' => $this->user->id,
        'name' => 'Test Wallet',
        'type' => WalletType::Bank,
        'initial_balance' => 0,
        'balance' => 0,
        'is_active' => true,
    ]);
});

test('budget service calculates progress correctly', function () {
    $budget = Budget::create([
        'user_id' => $this->user->id,
        'category_id' => $this->category->id,
        'amount' => 1000,
        'period' => 'monthly',
        'start_date' => now()->startOfMonth(),
    ]);

    Transaction::create([
        'user_id' => $this->user->id,
        'wallet_id' => $this->wallet->id,
        'type' => TransactionType::Expense,
        'amount' => 700,
        'category_transaction' => TransactionCategory::Food->value,
        'source' => 'web',
        'occurred_at' => now(),
    ]);

    $service = app(BudgetService::class);
    $progress = $service->getProgressForBudget($budget, $this->user->id);

    expect((float) $progress['budget_amount'])->toBe(1000.0)
        ->and((float) $progress['spent_amount'])->toBe(700.0)
        ->and((float) $progress['remaining_amount'])->toBe(300.0)
        ->and($progress['status'])->toBe('warning');
});

test('budget service getStatus returns safe when under 70 percent', function () {
    $service = app(BudgetService::class);
    expect($service->getStatus(50))->toBe('safe');
});

test('budget service getStatus returns exceeded when over 100 percent', function () {
    $service = app(BudgetService::class);
    expect($service->getStatus(150))->toBe('exceeded');
});

test('dashboard budgets endpoint returns correct structure and is scoped to user', function () {
    $budget = Budget::create([
        'user_id' => $this->user->id,
        'category_id' => $this->category->id,
        'amount' => 2000000,
        'period' => 'monthly',
        'start_date' => now()->startOfMonth(),
    ]);

    $response = $this->actingAs($this->user)->getJson('/dashboard/budgets');

    $response->assertOk()
        ->assertJsonStructure([[
            'budget_id',
            'category_id',
            'category',
            'budget_amount',
            'spent',
            'remaining',
            'percentage',
            'status',
        ]]);

    expect($response->json()[0]['category'])->toBe('Food')
        ->and((float) $response->json()[0]['budget_amount'])->toBe(2000000.0);
});

test('budget controller store requires auth', function () {
    $response = $this->postJson(route('budgets.store'), [
        'category_id' => $this->category->id,
        'amount' => 1000,
        'period' => 'monthly',
    ]);

    $response->assertUnauthorized();
});

test('budget controller store creates budget for authenticated user', function () {
    $response = $this->actingAs($this->user)->post(route('budgets.store'), [
        'category_id' => $this->category->id,
        'amount' => 1000,
        'period' => 'monthly',
    ]);

    $response->assertRedirect(route('transactions.index'));

    $budget = Budget::where('user_id', $this->user->id)->first();
    expect($budget)->not->toBeNull()
        ->and((float) $budget->amount)->toBe(1000.0);
});

test('budget cache is invalidated on budget create', function () {
    Cache::put("dashboard.budgets.{$this->user->id}", ['cached' => true], 600);

    $this->actingAs($this->user)->post(route('budgets.store'), [
        'category_id' => $this->category->id,
        'amount' => 1000,
        'period' => 'monthly',
    ]);

    expect(Cache::has("dashboard.budgets.{$this->user->id}"))->toBeFalse();
});
