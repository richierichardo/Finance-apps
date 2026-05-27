<?php

use App\Enums\TransactionCategory;
use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Enums\WalletType;
use App\Models\Category;
use App\Models\RecurringTransaction;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Services\RecurringTransactionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->user = User::factory()->create(['username' => 'user_'.Str::random(8)]);

    $this->category = Category::firstOrCreate(
        ['slug' => TransactionCategory::Bills->value],
        ['name' => 'Bills']
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

test('recurring process creates transaction with source system', function () {
    RecurringTransaction::create([
        'user_id' => $this->user->id,
        'wallet_id' => $this->wallet->id,
        'category_id' => $this->category->id,
        'type' => 'expense',
        'amount' => 150000,
        'description' => 'Netflix',
        'frequency' => 'monthly',
        'interval' => 1,
        'start_date' => now()->subMonth(),
        'next_run_at' => now()->subHour(),
        'is_active' => true,
    ]);

    Artisan::call('recurring:process');

    $transaction = Transaction::where('user_id', $this->user->id)
        ->where('description', 'Netflix')
        ->first();

    expect($transaction)->not->toBeNull()
        ->and($transaction->source)->toBe(TransactionSource::SystemRecurring)
        ->and((float) $transaction->amount)->toBe(150000.0);
});

test('recurring process updates next_run_at for monthly frequency', function () {
    $nextRun = now()->subHour();
    $recurring = RecurringTransaction::create([
        'user_id' => $this->user->id,
        'wallet_id' => $this->wallet->id,
        'category_id' => $this->category->id,
        'type' => 'expense',
        'amount' => 100,
        'description' => 'Test',
        'frequency' => 'monthly',
        'interval' => 1,
        'start_date' => now(),
        'next_run_at' => $nextRun,
        'is_active' => true,
    ]);

    Artisan::call('recurring:process');

    $recurring->refresh();

    expect($recurring->next_run_at->format('Y-m-d'))
        ->toBe($nextRun->copy()->addMonth()->format('Y-m-d'));
});

test('getUpcomingForUser returns correct structure', function () {
    RecurringTransaction::create([
        'user_id' => $this->user->id,
        'wallet_id' => $this->wallet->id,
        'category_id' => $this->category->id,
        'type' => 'expense',
        'amount' => 150000,
        'description' => 'Netflix',
        'frequency' => 'monthly',
        'interval' => 1,
        'start_date' => now(),
        'next_run_at' => now()->addDays(5),
        'is_active' => true,
    ]);

    $service = app(RecurringTransactionService::class);
    $upcoming = $service->getUpcomingForUser($this->user->id, 5);

    expect($upcoming)->toHaveCount(1)
        ->and($upcoming[0])->toHaveKeys(['description', 'amount', 'next_run_at'])
        ->and($upcoming[0]['description'])->toBe('Netflix')
        ->and($upcoming[0]['amount'])->toBe(150000.0);
});

test('dashboard upcoming-recurring endpoint returns correct structure', function () {
    RecurringTransaction::create([
        'user_id' => $this->user->id,
        'wallet_id' => $this->wallet->id,
        'category_id' => $this->category->id,
        'type' => 'expense',
        'amount' => 150000,
        'description' => 'Netflix',
        'frequency' => 'monthly',
        'interval' => 1,
        'start_date' => now(),
        'next_run_at' => now()->addDays(5),
        'is_active' => true,
    ]);

    $response = $this->actingAs($this->user)->getJson('/dashboard/upcoming-recurring');

    $response->assertOk()
        ->assertJsonStructure([['description', 'amount', 'next_run_at']]);

    expect($response->json()[0]['description'])->toBe('Netflix')
        ->and((float) $response->json()[0]['amount'])->toBe(150000.0);
});

test('recurring with end_date in past is not processed', function () {
    RecurringTransaction::create([
        'user_id' => $this->user->id,
        'wallet_id' => $this->wallet->id,
        'category_id' => $this->category->id,
        'type' => 'expense',
        'amount' => 100,
        'description' => 'Expired',
        'frequency' => 'monthly',
        'interval' => 1,
        'start_date' => now()->subMonths(2),
        'next_run_at' => now()->subHour(),
        'end_date' => now()->subDays(10),
        'is_active' => true,
    ]);

    $countBefore = Transaction::where('user_id', $this->user->id)->count();
    Artisan::call('recurring:process');
    $countAfter = Transaction::where('user_id', $this->user->id)->count();

    expect($countAfter)->toBe($countBefore);
});

test('recurring controller store requires auth', function () {
    $response = $this->post(route('recurring-transactions.store'), [
        'wallet_id' => $this->wallet->id,
        'category_id' => $this->category->id,
        'type' => 'expense',
        'amount' => 1000,
        'frequency' => 'monthly',
        'interval' => 1,
        'start_date' => now()->format('Y-m-d'),
    ]);

    $response->assertRedirect(route('login'));
});

test('recurring controller store creates for authenticated user', function () {
    $response = $this->actingAs($this->user)->post(route('recurring-transactions.store'), [
        'wallet_id' => $this->wallet->id,
        'category_id' => $this->category->id,
        'type' => 'expense',
        'amount' => 1000,
        'description' => 'Rent',
        'frequency' => 'monthly',
        'interval' => 1,
        'start_date' => now()->format('Y-m-d'),
    ]);

    $response->assertRedirect(route('recurring-transactions.index'));

    $recurring = RecurringTransaction::where('user_id', $this->user->id)->first();
    expect($recurring)->not->toBeNull()
        ->and($recurring->description)->toBe('Rent')
        ->and((float) $recurring->amount)->toBe(1000.0);
});

test('upcoming recurring cache is invalidated on recurring create', function () {
    Cache::put("dashboard.upcoming_recurring.{$this->user->id}", ['cached' => true], 300);

    $this->actingAs($this->user)->post(route('recurring-transactions.store'), [
        'wallet_id' => $this->wallet->id,
        'category_id' => $this->category->id,
        'type' => 'expense',
        'amount' => 1000,
        'frequency' => 'monthly',
        'interval' => 1,
        'start_date' => now()->format('Y-m-d'),
    ]);

    expect(Cache::has("dashboard.upcoming_recurring.{$this->user->id}"))->toBeFalse();
});
