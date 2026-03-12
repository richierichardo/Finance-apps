<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWalletRequest;
use App\Http\Requests\UpdateWalletRequest;
use App\Models\Wallet;
use App\Services\WalletService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class WalletController extends Controller
{
    public function __construct(
        protected WalletService $walletService
    ) {}
    public function index(): Response
    {
        $this->authorize('viewAny', Wallet::class);
        $wallets = Wallet::belongsToUser(auth()->id())->get();

        return Inertia::render('Wallets/Index', ['wallets' => $wallets]);
    }

    public function create(): Response
    {
        $this->authorize('create', Wallet::class);

        return Inertia::render('Wallets/Create');
    }

    public function store(StoreWalletRequest $request): RedirectResponse
    {
        $this->authorize('create', Wallet::class);

        $wallet = $this->walletService->create([
            'user_id' => auth()->id(),
            'name' => $request->name,
            'type' => $request->type,
            'initial_balance' => $request->initial_balance ?? 0,
        ]);

        return Redirect::route('wallets.show', $wallet)
            ->with('success', 'Wallet created successfully.');
    }

    public function show(Wallet $wallet): Response
    {
        $this->authorize('view', $wallet);

        $wallet->load('transactions');

        return Inertia::render('Wallets/Show', ['wallet' => $wallet]);
    }

    public function edit(Wallet $wallet): Response
    {
        $this->authorize('update', $wallet);

        return Inertia::render('Wallets/Edit', ['wallet' => $wallet]);
    }

    public function update(UpdateWalletRequest $request, Wallet $wallet): RedirectResponse
    {
        $this->authorize('update', $wallet);

        $this->walletService->update($wallet, $request->validated());

        return Redirect::route('wallets.show', $wallet)
            ->with('success', 'Wallet updated successfully.');
    }

    public function destroy(Wallet $wallet): RedirectResponse
    {
        $this->authorize('delete', $wallet);

        $this->walletService->delete($wallet);

        return Redirect::route('wallets.index')
            ->with('success', 'Wallet deleted successfully.');
    }

    public function syncBalance(): RedirectResponse
    {
        $wallets = Wallet::belongsToUser(auth()->id())->get();

        foreach ($wallets as $wallet) {
            $this->walletService->syncBalance($wallet);
        }

        return Redirect::back()->with('success', 'Synced balance for ' . $wallets->count() . ' wallet(s).');
    }
}
