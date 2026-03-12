<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRecurringTransactionRequest;
use App\Http\Requests\UpdateRecurringTransactionRequest;
use App\Models\Category;
use App\Models\RecurringTransaction;
use App\Models\Wallet;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class RecurringTransactionController extends Controller
{
    public function index(): Response
    {
        $this->authorize('viewAny', RecurringTransaction::class);

        $recurringTransactions = RecurringTransaction::forUser(auth()->id())
            ->with(['wallet:id,name', 'category:id,name,slug'])
            ->orderBy('next_run_at')
            ->get();

        $wallets = Wallet::belongsToUser(auth()->id())->get(['id', 'name']);
        $categories = Category::orderBy('name')->get(['id', 'name', 'slug']);

        return Inertia::render('RecurringTransactions/Index', [
            'recurringTransactions' => $recurringTransactions,
            'wallets' => $wallets,
            'categories' => $categories,
        ]);
    }

    public function store(StoreRecurringTransactionRequest $request): RedirectResponse
    {
        $this->authorize('create', RecurringTransaction::class);

        $startDate = Carbon::parse($request->start_date);
        $nextRunAt = $startDate->copy()->startOfDay();

        RecurringTransaction::create([
            'user_id' => auth()->id(),
            'wallet_id' => $request->wallet_id,
            'category_id' => $request->category_id,
            'type' => $request->type,
            'amount' => $request->amount,
            'description' => $request->description,
            'frequency' => $request->frequency,
            'interval' => max(1, (int) $request->interval),
            'start_date' => $request->start_date,
            'next_run_at' => $nextRunAt,
            'end_date' => $request->end_date,
            'is_active' => true,
        ]);

        $this->invalidateUpcomingRecurringCache();

        return Redirect::route('recurring-transactions.index')
            ->with('success', 'Recurring transaction created successfully.');
    }

    public function update(UpdateRecurringTransactionRequest $request, RecurringTransaction $recurringTransaction): RedirectResponse
    {
        $this->authorize('update', $recurringTransaction);

        $recurringTransaction->update($request->validated());

        $this->invalidateUpcomingRecurringCache();

        return Redirect::route('recurring-transactions.index')
            ->with('success', 'Recurring transaction updated successfully.');
    }

    public function destroy(RecurringTransaction $recurringTransaction): RedirectResponse
    {
        $this->authorize('delete', $recurringTransaction);

        $recurringTransaction->delete();

        $this->invalidateUpcomingRecurringCache();

        return Redirect::route('recurring-transactions.index')
            ->with('success', 'Recurring transaction deleted successfully.');
    }

    public function toggleActive(RecurringTransaction $recurringTransaction): RedirectResponse
    {
        $this->authorize('update', $recurringTransaction);

        $recurringTransaction->update(['is_active' => ! $recurringTransaction->is_active]);

        $this->invalidateUpcomingRecurringCache();

        return Redirect::route('recurring-transactions.index')
            ->with('success', 'Recurring transaction ' . ($recurringTransaction->is_active ? 'activated' : 'paused') . '.');
    }

    private function invalidateUpcomingRecurringCache(): void
    {
        Cache::forget('dashboard.upcoming_recurring.' . auth()->id());
    }
}
