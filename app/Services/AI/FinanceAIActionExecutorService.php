<?php

namespace App\Services\AI;

use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Models\Budget;
use App\Models\RecurringTransaction;
use App\Models\Wallet;
use App\Services\TransactionService;
use App\Services\TransferService;
use App\Services\WalletService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class FinanceAIActionExecutorService
{
    public function __construct(
        protected TransactionService $transactionService,
        protected TransferService $transferService,
        protected WalletService $walletService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, message: string}
     */
    public function execute(int $userId, string $actionType, array $payload): array
    {
        try {
            return match ($actionType) {
                'create_transaction' => $this->executeTransaction($userId, $payload),
                'create_transfer' => $this->executeTransfer($userId, $payload),
                'create_budget' => $this->executeBudget($userId, $payload),
                'create_recurring' => $this->executeRecurring($userId, $payload),
                'create_wallet' => $this->executeWallet($userId, $payload),
                'explain_insight' => ['ok' => true, 'message' => 'Insight explained.'],
                default => ['ok' => false, 'message' => 'Tipe aksi tidak didukung.'],
            };
        } catch (InvalidArgumentException $e) {
            return ['ok' => false, 'message' => $e->getMessage()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'message' => 'Gagal menjalankan aksi. Silakan coba lagi.'];
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, message: string}
     */
    private function executeTransaction(int $userId, array $payload): array
    {
        $wallet = Wallet::belongsToUser($userId)->findOrFail($payload['wallet_id']);

        $this->transactionService->create([
            'user_id' => $userId,
            'wallet_id' => $wallet->id,
            'type' => $payload['type'],
            'amount' => $payload['amount'],
            'category_transaction' => $payload['category_transaction'] ?? null,
            'description' => $payload['description'] ?? null,
            'source' => $payload['source'] ?? TransactionSource::TelegramAi->value,
            'occurred_at' => $payload['transaction_date'] ?? now()->toDateTimeString(),
        ]);

        $label = $payload['type'] === 'income' ? 'Income' : 'Expense';

        return [
            'ok' => true,
            'message' => sprintf(
                'Berhasil disimpan. %s %s dari %s sudah dicatat.',
                $label,
                $this->formatRupiah((float) $payload['amount']),
                $wallet->name
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, message: string}
     */
    private function executeTransfer(int $userId, array $payload): array
    {
        $from = Wallet::belongsToUser($userId)->findOrFail($payload['from_wallet_id']);
        $to = Wallet::belongsToUser($userId)->findOrFail($payload['to_wallet_id']);

        $this->transferService->transfer(
            $from,
            $to,
            (float) $payload['amount'],
            $payload['description'] ?? null,
            $payload['transaction_date'] ?? now()->toDateTimeString(),
        );

        return [
            'ok' => true,
            'message' => sprintf(
                'Transfer %s dari %s ke %s berhasil.',
                $this->formatRupiah((float) $payload['amount']),
                $from->name,
                $to->name
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, message: string}
     */
    private function executeBudget(int $userId, array $payload): array
    {
        Budget::create([
            'user_id' => $userId,
            'category_id' => $payload['category_id'],
            'amount' => $payload['amount'],
            'period' => $payload['period'] ?? 'monthly',
            'start_date' => $payload['start_date'] ?? now()->startOfMonth()->format('Y-m-d'),
            'end_date' => null,
        ]);

        Cache::forget('dashboard.budgets.'.$userId);

        return [
            'ok' => true,
            'message' => sprintf(
                'Budget %s per %s berhasil dibuat.',
                $this->formatRupiah((float) $payload['amount']),
                $payload['period'] ?? 'monthly'
            ),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, message: string}
     */
    private function executeRecurring(int $userId, array $payload): array
    {
        $startDate = Carbon::parse($payload['start_date']);

        RecurringTransaction::create([
            'user_id' => $userId,
            'wallet_id' => $payload['wallet_id'],
            'category_id' => $payload['category_id'],
            'type' => $payload['type'],
            'amount' => $payload['amount'],
            'description' => $payload['description'] ?? null,
            'frequency' => $payload['frequency'] ?? 'monthly',
            'interval' => max(1, (int) ($payload['interval'] ?? 1)),
            'start_date' => $payload['start_date'],
            'next_run_at' => $startDate->copy()->startOfDay(),
            'end_date' => $payload['end_date'] ?? null,
            'is_active' => true,
        ]);

        Cache::forget('dashboard.upcoming_recurring.'.$userId);

        return [
            'ok' => true,
            'message' => 'Transaksi berulang berhasil dibuat.',
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, message: string}
     */
    private function executeWallet(int $userId, array $payload): array
    {
        $wallet = $this->walletService->create([
            'user_id' => $userId,
            'name' => $payload['name'],
            'type' => $payload['type'],
            'initial_balance' => $payload['initial_balance'] ?? 0,
            'balance' => $payload['initial_balance'] ?? 0,
            'is_active' => true,
        ]);

        return [
            'ok' => true,
            'message' => sprintf(
                'Wallet %s berhasil dibuat dengan saldo awal %s.',
                $wallet->name,
                $this->formatRupiah((float) ($payload['initial_balance'] ?? 0))
            ),
        ];
    }

    private function formatRupiah(float $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }
}
