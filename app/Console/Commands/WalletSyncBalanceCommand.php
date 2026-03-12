<?php

namespace App\Console\Commands;

use App\Models\Wallet;
use App\Services\WalletService;
use Illuminate\Console\Command;

class WalletSyncBalanceCommand extends Command
{
    protected $signature = 'wallet:sync-balance {--user= : Sync only wallets for this user ID}';

    protected $description = 'Recalculate wallet balances from transaction history';

    public function __construct(
        protected WalletService $walletService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $query = Wallet::query();

        if ($userId = $this->option('user')) {
            $query->where('user_id', $userId);
        }

        $wallets = $query->get();

        foreach ($wallets as $wallet) {
            $this->walletService->syncBalance($wallet);
        }

        $this->info('Synced balance for '.$wallets->count().' wallet(s).');

        return Command::SUCCESS;
    }
}
