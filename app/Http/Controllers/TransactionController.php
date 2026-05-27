<?php

namespace App\Http\Controllers;

use App\Enums\TransactionCategoryExpenses;
use App\Enums\TransactionCategoryIncome;
use App\Enums\TransactionSource;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Requests\TransferTransactionRequest;
use App\Http\Requests\UpdateTransactionRequest;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\Wallet;
use App\Services\TransactionService;
use App\Services\TransferService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use InvalidArgumentException;

class TransactionController extends Controller
{
    public function __construct(
        protected TransactionService $transactionService,
        protected TransferService $transferService
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Transaction::class);

        $query = Transaction::with('wallet')
            ->where('user_id', auth()->id());

        if ($request->filled('wallet_id')) {
            $query->where('wallet_id', $request->wallet_id);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('occurred_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('occurred_at', '<=', $request->date_to);
        }

        $totalIncome = (float) (clone $query)->where('type', 'income')->sum('amount');
        $totalExpense = (float) (clone $query)->where('type', 'expense')->sum('amount');
        $totalCount = (clone $query)->count();

        $transactions = (clone $query)->orderByDesc('occurred_at')->paginate(15);
        $wallets = Wallet::belongsToUser(auth()->id())->get();

        $categories = Category::orderBy('name')->get(['id', 'name', 'slug']);

        return Inertia::render('Transactions/Index', [
            'transactions' => $transactions,
            'wallets' => $wallets,
            'categories' => $categories,
            'filters' => $request->only(['wallet_id', 'type', 'date_from', 'date_to']),
            'summary' => [
                'total_income' => $totalIncome,
                'total_expense' => $totalExpense,
                'net_cashflow' => $totalIncome - $totalExpense,
                'total_count' => $totalCount,
            ],
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Transaction::class);
        $wallets = Wallet::belongsToUser(auth()->id())->get();

        return Inertia::render('Transactions/Create', [
            'wallets' => $wallets,
            'categoriesExpenses' => collect(TransactionCategoryExpenses::cases())->map(fn ($c) => [
                'value' => $c->value,
                'label' => $c->name,
            ])->values()->toArray(),
            'categoriesIncome' => collect(TransactionCategoryIncome::cases())->map(fn ($c) => [
                'value' => $c->value,
                'label' => $c->name,
            ])->values()->toArray(),
        ]);
    }

    public function store(StoreTransactionRequest $request): RedirectResponse
    {
        $this->authorize('create', Transaction::class);

        $wallet = Wallet::belongsToUser(auth()->id())->findOrFail($request->wallet_id);

        $this->transactionService->create([
            'user_id' => auth()->id(),
            'wallet_id' => $wallet->id,
            'type' => $request->type,
            'amount' => $request->amount,
            'category_transaction' => $request->category_transaction,
            'description' => $request->description,
            'source' => TransactionSource::WebManual->value,
            'occurred_at' => $request->occurred_at,
        ]);

        return Redirect::route('transactions.index')
            ->with('success', 'Transaction created successfully.');
    }

    public function show(Transaction $transaction): Response
    {
        $this->authorize('view', $transaction);
        $transaction->load('wallet', 'reference.wallet');

        return Inertia::render('Transactions/Show', ['transaction' => $transaction]);
    }

    public function edit(Transaction $transaction): Response
    {
        $this->authorize('update', $transaction);
        $wallets = Wallet::belongsToUser(auth()->id())->get();

        return Inertia::render('Transactions/Edit', [
            'transaction' => $transaction,
            'wallets' => $wallets,
            'categoriesExpenses' => collect(TransactionCategoryExpenses::cases())->map(fn ($c) => [
                'value' => $c->value,
                'label' => $c->name,
            ])->values()->toArray(),
            'categoriesIncome' => collect(TransactionCategoryIncome::cases())->map(fn ($c) => [
                'value' => $c->value,
                'label' => $c->name,
            ])->values()->toArray(),
        ]);
    }

    public function update(UpdateTransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        $this->authorize('update', $transaction);

        $data = $request->validated();
        if (isset($data['wallet_id'])) {
            Wallet::belongsToUser(auth()->id())->findOrFail($data['wallet_id']);
        }

        $this->transactionService->update($transaction, $data);

        return redirect()->route('transactions.show', $transaction)
            ->with('success', 'Transaction updated successfully.');
    }

    public function destroy(Transaction $transaction): RedirectResponse
    {
        $this->authorize('delete', $transaction);

        $this->transactionService->delete($transaction);

        return Redirect::route('transactions.index')
            ->with('success', 'Transaction deleted successfully.');
    }

    public function transferForm(): Response
    {
        $this->authorize('create', Transaction::class);
        $wallets = Wallet::belongsToUser(auth()->id())->get();

        return Inertia::render('Transactions/Transfer', ['wallets' => $wallets]);
    }

    public function transfer(TransferTransactionRequest $request): RedirectResponse
    {
        $this->authorize('create', Transaction::class);

        $fromWallet = Wallet::belongsToUser(auth()->id())->findOrFail($request->from_wallet_id);
        $toWallet = Wallet::belongsToUser(auth()->id())->findOrFail($request->to_wallet_id);

        try {
            $this->transferService->transfer(
                $fromWallet,
                $toWallet,
                (float) $request->amount,
                $request->description,
                $request->occurred_at
            );
        } catch (InvalidArgumentException $e) {
            return Redirect::back()->withErrors(['amount' => $e->getMessage()]);
        }

        return Redirect::route('transactions.index')
            ->with('success', 'Transfer completed successfully.');
    }
}
