<?php

namespace App\Services\AI;

use Illuminate\Support\Str;

class FinanceAIClarificationService
{
  /**
   * @param  list<string>  $missing
   * @param  array<string, mixed>  $entities
   */
  public function buildFieldSpecificClarification(string $actionType, array $missing, array $entities): ?string
  {
    if (empty($missing)) {
      return null;
    }

    return match ($actionType) {
      'create_wallet' => $this->buildWalletClarifyingQuestion($missing, $entities),
      'create_transfer' => $this->buildTransferClarifyingQuestion($missing, $entities),
      'create_transaction' => $this->buildTransactionClarifyingQuestion($missing, $entities),
      'create_budget' => $this->buildBudgetClarifyingQuestion($missing, $entities),
      'create_recurring' => $this->buildRecurringClarifyingQuestion($missing, $entities),
      default => null,
    };
  }

  public function buildGenericExample(string $actionType): string
  {
    return match ($actionType) {
      'create_wallet' => 'Sebutkan nama dan tipe wallet. Contoh: buat wallet cash nama uang dompet saldo 50 ribu.',
      'create_transfer' => 'Contoh: transfer 20 ribu dari GOPAY ke SHOPEEPAY.',
      'create_transaction' => 'Contoh: catat pengeluaran 25 ribu dari GOPAY buat kopi.',
      'create_budget' => 'Contoh: budget makan bulan ini 1.5 juta.',
      'create_recurring' => 'Contoh: recurring netflix 150 ribu tiap bulan dari gopay.',
      default => 'Aku belum bisa memahami perintah itu. Coba tulis perintah lebih lengkap.',
    };
  }

  /**
   * @param  list<string>  $missing
   * @param  array<string, mixed>  $entities
   */
  public function buildWalletClarifyingQuestion(array $missing, array $entities): string
  {
    $hasUsefulEntity = ! empty($entities['wallet_name'])
      || ! empty($entities['wallet_type'])
      || (isset($entities['initial_balance']) && (int) $entities['initial_balance'] > 0);

    if (! $hasUsefulEntity) {
      return $this->buildGenericExample('create_wallet');
    }

    $questions = [];
    $displayName = $this->walletDisplayName($entities['wallet_name'] ?? null);

    if (in_array('wallet_type', $missing, true)) {
      $questions[] = $displayName
        ? "Tipe wallet {$displayName} apa? Pilih: Bank, E-Wallet, atau Cash."
        : 'Tipe wallet apa? Pilih: Bank, E-Wallet, atau Cash.';
    }
    if (in_array('wallet_name', $missing, true)) {
      $questions[] = 'Nama wallet-nya apa?';
    }
    if (in_array('initial_balance', $missing, true)) {
      $questions[] = 'Saldo awalnya mau diisi berapa? Kalau kosong, saya isi Rp 0.';
    }

    return ! empty($questions)
      ? implode(' ', $questions)
      : $this->buildGenericExample('create_wallet');
  }

  /**
   * @param  list<string>  $missing
   * @param  array<string, mixed>  $entities
   */
  private function buildTransferClarifyingQuestion(array $missing, array $entities): string
  {
    $questions = [];
    if (in_array('amount', $missing, true)) {
      $questions[] = 'Mau transfer berapa?';
    }
    if (in_array('from_wallet', $missing, true)) {
      $questions[] = 'Dari wallet mana?';
    }
    if (in_array('to_wallet', $missing, true)) {
      $questions[] = 'Ke wallet mana?';
    }

    return ! empty($questions)
      ? implode(' ', $questions)
      : $this->buildGenericExample('create_transfer');
  }

  /**
   * @param  list<string>  $missing
   * @param  array<string, mixed>  $entities
   */
  private function buildTransactionClarifyingQuestion(array $missing, array $entities): string
  {
    $questions = [];
    if (in_array('amount', $missing, true)) {
      $questions[] = 'Berapa jumlahnya?';
    }
    if (in_array('wallet_name', $missing, true)) {
      $questions[] = 'Wallet mana yang dipakai?';
    }

    return ! empty($questions)
      ? implode(' ', $questions)
      : $this->buildGenericExample('create_transaction');
  }

  /**
   * @param  list<string>  $missing
   * @param  array<string, mixed>  $entities
   */
  private function buildBudgetClarifyingQuestion(array $missing, array $entities): string
  {
    if (in_array('amount', $missing, true) && in_array('category_name', $missing, true)) {
      return 'Berapa jumlah budget-nya dan kategori apa?';
    }
    if (in_array('amount', $missing, true)) {
      return 'Berapa jumlah budget-nya?';
    }
    if (in_array('category_name', $missing, true)) {
      return 'Kategori apa yang mau dibuat budget-nya?';
    }

    return $this->buildGenericExample('create_budget');
  }

  /**
   * @param  list<string>  $missing
   * @param  array<string, mixed>  $entities
   */
  private function buildRecurringClarifyingQuestion(array $missing, array $entities): string
  {
    $questions = [];
    if (in_array('amount', $missing, true)) {
      $questions[] = 'Berapa jumlahnya?';
    }
    if (in_array('wallet_name', $missing, true)) {
      $questions[] = 'Wallet mana yang dipakai?';
    }

    return ! empty($questions)
      ? implode(' ', $questions)
      : $this->buildGenericExample('create_recurring');
  }

  private function walletDisplayName(?string $name): ?string
  {
    if ($name === null || trim($name) === '') {
      return null;
    }

    return Str::upper(trim($name));
  }

  /**
   * @param  array<string, mixed>  $entities
   * @return array<string, mixed>
   */
  public function normalizeWalletEntities(array $entities): array
  {
    $name = trim((string) ($entities['wallet_name'] ?? $entities['name'] ?? ''));
    $type = $entities['wallet_type'] ?? $entities['type'] ?? null;
    $balance = $entities['initial_balance'] ?? $entities['balance'] ?? $entities['amount'] ?? $entities['saldo_awal'] ?? null;

    return [
      'wallet_name' => $name !== '' ? $name : null,
      'wallet_type' => $this->normalizeWalletTypeValue($type),
      'initial_balance' => $balance !== null ? (int) $balance : null,
    ];
  }

  public function normalizeWalletTypeValue(mixed $type): ?string
  {
    if ($type === null || $type === '') {
      return null;
    }

    $key = mb_strtolower(trim((string) $type));
    $key = str_replace([' ', '-'], '', $key);

    return match ($key) {
      'cash', 'tunai', 'uangtunai' => 'cash',
      'bank', 'rekening' => 'bank',
      'ewallet' => 'ewallet',
      default => match (true) {
        str_contains($key, 'cash') || str_contains($key, 'tunai') => 'cash',
        str_contains($key, 'bank') || str_contains($key, 'rekening') => 'bank',
        str_contains($key, 'ewallet') || str_contains($key, 'wallet') => 'ewallet',
        default => in_array($key, ['cash', 'bank', 'ewallet'], true) ? $key : null,
      },
    };
  }
}
