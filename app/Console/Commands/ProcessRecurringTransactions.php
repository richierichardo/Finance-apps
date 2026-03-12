<?php

namespace App\Console\Commands;

use App\Services\RecurringTransactionService;
use Illuminate\Console\Command;

class ProcessRecurringTransactions extends Command
{
    protected $signature = 'recurring:process';

    protected $description = 'Process due recurring transactions and create actual transactions';

    public function __construct(
        protected RecurringTransactionService $recurringService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        try {
            $count = $this->recurringService->processDueRecurringTransactions();
            $this->info("Created {$count} transaction(s) from recurring.");

            return Command::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Recurring process failed: '.$e->getMessage());
            report($e);

            return Command::FAILURE;
        }
    }
}
