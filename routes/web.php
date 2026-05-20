<?php

use App\Http\Controllers\AIChatController;
use App\Http\Controllers\AIUsageController;
use App\Http\Controllers\AIInsightController;
use App\Http\Controllers\TelegramLinkController;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RecurringTransactionController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\WalletController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::post('/telegram/webhook/{secret}', [TelegramWebhookController::class, 'handle'])
    ->name('telegram.webhook');

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// Admin Routes
Route::middleware(['auth', 'admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', function () {
        return Inertia::render('Admin/Dashboard');
    })->name('admin.dashboard');

    Route::get('/users', function () {
        return Inertia::render('Admin/Users/Index');
    })->name('admin.users.index');

    Route::get('/users/{id}', function ($id) {
        return Inertia::render('Admin/Users/Show', ['userId' => $id]);
    })->name('admin.users.show');

    Route::get('/reset-password', function () {
        return Inertia::render('Admin/ResetPassword');
    })->name('admin.reset-password');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('/wallet-sync', [WalletController::class, 'syncBalance'])->name('wallets.sync');

    Route::resource('wallets', WalletController::class);

    Route::get('/transfer', [TransactionController::class, 'transferForm'])->name('transactions.transfer.form');
    Route::post('/transfer', [TransactionController::class, 'transfer'])->name('transactions.transfer');
    Route::resource('transactions', TransactionController::class);

    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::resource('budgets', BudgetController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::post('/recurring-transactions/{recurring_transaction}/toggle', [RecurringTransactionController::class, 'toggleActive'])->name('recurring-transactions.toggle');
    Route::resource('recurring-transactions', RecurringTransactionController::class)->only(['index', 'store', 'update', 'destroy']);

    Route::prefix('dashboard')->group(function () {
        Route::get('/summary', [DashboardController::class, 'summary'])->name('dashboard.summary');
        Route::get('/cashflow', [DashboardController::class, 'cashflow'])->name('dashboard.cashflow');
        Route::get('/category-breakdown', [DashboardController::class, 'categoryBreakdown'])->name('dashboard.category-breakdown');
        Route::get('/wallet-distribution', [DashboardController::class, 'walletDistribution'])->name('dashboard.wallet-distribution');
        Route::get('/daily-expense', [DashboardController::class, 'dailyExpense'])->name('dashboard.daily-expense');
        Route::get('/top-expenses', [DashboardController::class, 'topExpenses'])->name('dashboard.top-expenses');
        Route::get('/budgets', [DashboardController::class, 'budgets'])->name('dashboard.budgets');
        Route::get('/upcoming-recurring', [DashboardController::class, 'upcomingRecurring'])->name('dashboard.upcoming-recurring');
    });

    Route::get('/insights', [AIInsightController::class, 'index'])->name('insights.index');
    Route::get('/insights/forecast', [AIInsightController::class, 'forecast'])->name('insights.forecast');
    Route::get('/insights/{periodKey}', [AIInsightController::class, 'show'])->name('insights.show');
    Route::post('/insights/generate', [AIInsightController::class, 'generate'])->name('insights.generate');

    Route::get('/ai/usage-summary', [AIUsageController::class, 'summary'])->name('ai.usage-summary');
    Route::post('/ai/chat', [AIChatController::class, 'chat'])->name('ai.chat');
    Route::post('/ai/action-drafts/{draft}/confirm', [AIChatController::class, 'confirm'])->name('ai.action.confirm');
    Route::post('/ai/action-drafts/{draft}/cancel', [AIChatController::class, 'cancel'])->name('ai.action.cancel');

    Route::post('/telegram/link-token', [TelegramLinkController::class, 'store'])->name('telegram.link-token');
});

require __DIR__.'/auth.php';
