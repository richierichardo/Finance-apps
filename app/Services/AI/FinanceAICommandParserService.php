<?php

namespace App\Services\AI;

use App\Enums\WalletType;

class FinanceAICommandParserService
{
    private const COMPLETE_CONFIDENCE = 0.95;

    private const PARTIAL_CONFIDENCE = 0.75;

    /** @var list<string> */
    private const EWALLET_BRANDS = [
        'gopay', 'shopeepay', 'ovo', 'dana', 'linkaja', 'shoppepay',
    ];

    /** @var list<string> */
    private const BANK_BRANDS = [
        'bca', 'mandiri', 'bri', 'bni', 'cimb', 'jago', 'seabank',
    ];

    public function __construct(
        protected FinanceNLPNormalizerService $normalizer,
        protected FinanceEntityResolverService $resolver,
        protected FinanceAIClarificationService $clarificationService,
    ) {}

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function parse(int $userId, string $message, array $context = []): array
    {
        $context['user_id'] = $userId;
        $normalized = $this->normalizer->normalize($message);
        $text = $normalized['normalized_text'];

        $parsers = [
            fn () => $this->parseConfirm($text),
            fn () => $this->parseCancel($text),
            fn () => $this->parseHelp($text),
            fn () => $this->parseWallet($userId, $text, $context, $normalized),
            fn () => $this->parseTransfer($userId, $text, $context, $normalized),
            fn () => $this->parseTransaction($userId, $text, $context, $normalized),
            fn () => $this->parseRecurring($userId, $text, $context, $normalized),
            fn () => $this->parseBudget($userId, $text, $context, $normalized),
            fn () => $this->parseSpecificWalletBalance($userId, $text, $context),
            fn () => $this->parseAllWalletBalance($text),
            fn () => $this->parseForecast($text),
            fn () => $this->parseSummary($text),
            fn () => $this->parseBudgetStatus($text),
        ];

        foreach ($parsers as $parser) {
            $result = $parser();
            if ($result !== null) {
                return $this->finalize($result, $normalized);
            }
        }

        return $this->finalize([
            'matched' => false,
            'intent' => 'unknown',
            'confidence' => 0.0,
            'requires_action' => false,
            'action_type' => null,
            'entities' => [],
            'missing_fields' => [],
            'clarifying_question' => null,
            'debug' => ['matched_pattern' => null],
        ], $normalized);
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $normalized
     */
    private function parseTransfer(int $userId, string $text, array $context, array $normalized): ?array
    {
        if (! preg_match('/\b(transfer|pindah)\b/u', $text)) {
            return null;
        }

        $amount = $this->normalizer->primaryAmount($normalized);
        $pair = $this->resolver->findWalletsInTransfer($userId, $text, $context);
        $from = $pair['from'];
        $to = $pair['to'];

        $missing = [];
        $questions = [];
        if (! $amount) {
            $missing[] = 'amount';
            $questions[] = 'Mau transfer berapa?';
        }
        if (! $from) {
            $missing[] = 'from_wallet';
            $questions[] = 'Dari wallet mana?';
        }
        if (! $to) {
            $missing[] = 'to_wallet';
            $questions[] = 'Ke wallet mana?';
        }
        if ($from && $to && $from['id'] === $to['id']) {
            return [
                'matched' => true,
                'intent' => 'parse_transfer',
                'confidence' => self::PARTIAL_CONFIDENCE,
                'requires_action' => false,
                'action_type' => 'create_transfer',
                'entities' => [],
                'missing_fields' => ['wallets'],
                'clarifying_question' => 'Wallet asal dan tujuan harus berbeda.',
                'debug' => ['matched_pattern' => 'transfer_same_wallet'],
            ];
        }

        $entities = [
            'amount' => $amount,
            'from_wallet_name' => $from['name'] ?? null,
            'from_wallet_id' => $from['id'] ?? null,
            'to_wallet_name' => $to['name'] ?? null,
            'to_wallet_id' => $to['id'] ?? null,
            'description' => null,
            'date' => now()->format('Y-m-d'),
        ];

        if (empty($missing)) {
            return [
                'matched' => true,
                'intent' => 'parse_transfer',
                'confidence' => self::COMPLETE_CONFIDENCE,
                'requires_action' => true,
                'action_type' => 'create_transfer',
                'entities' => $entities,
                'missing_fields' => [],
                'clarifying_question' => null,
                'debug' => ['matched_pattern' => 'transfer_complete'],
            ];
        }

        return [
            'matched' => true,
            'intent' => 'parse_transfer',
            'confidence' => self::PARTIAL_CONFIDENCE,
            'requires_action' => true,
            'action_type' => 'create_transfer',
            'entities' => $entities,
            'missing_fields' => $missing,
            'clarifying_question' => implode(' ', $questions),
            'debug' => ['matched_pattern' => 'transfer_partial'],
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $normalized
     */
    private function parseTransaction(int $userId, string $text, array $context, array $normalized): ?array
    {
        $isTransaction = preg_match(
            '/\b(catat|tambah|input|expense|pengeluaran|income|pemasukan|jajan|masuk|terima|gaji|beli|bayar|transaksi)\b/u',
            $text
        );
        if (! $isTransaction) {
            return null;
        }

        $type = $this->detectTransactionType($text);
        $amount = $this->normalizer->primaryAmount($normalized);
        $wallet = $this->resolveTransactionWallet($userId, $text, $type, $context);

        $description = null;
        if (preg_match('/\b(?:untuk|buat|keterangan)\s+(.+?)(?:\s+dari|\s+pakai|\s+ke|$)/u', $text, $m)) {
            $description = trim($m[1]);
        } elseif (preg_match('/\b(?:beli|jajan|bayar)\s+(.+?)(?:\s+\d|\s+\d+|\s+ribu|\s+rb|\s+pakai|\s+ke|$)/u', $text, $m)) {
            $description = trim($m[1]);
        }

        $missing = [];
        $questions = [];
        if (! $amount) {
            $missing[] = 'amount';
            $questions[] = 'Berapa jumlahnya?';
        }
        if (! $wallet) {
            $missing[] = 'wallet_name';
            $questions[] = 'Wallet mana yang dipakai?';
        }

        $entities = [
            'transaction_type' => $type,
            'amount' => $amount,
            'wallet_name' => $wallet['name'] ?? null,
            'wallet_id' => $wallet['id'] ?? null,
            'description' => $description,
            'date' => now()->format('Y-m-d'),
        ];

        if (empty($missing)) {
            return [
                'matched' => true,
                'intent' => 'parse_transaction',
                'confidence' => self::COMPLETE_CONFIDENCE,
                'requires_action' => true,
                'action_type' => 'create_transaction',
                'entities' => $entities,
                'missing_fields' => [],
                'clarifying_question' => null,
                'debug' => ['matched_pattern' => 'transaction_complete'],
            ];
        }

        return [
            'matched' => true,
            'intent' => 'parse_transaction',
            'confidence' => self::PARTIAL_CONFIDENCE,
            'requires_action' => true,
            'action_type' => 'create_transaction',
            'entities' => $entities,
            'missing_fields' => $missing,
            'clarifying_question' => implode(' ', $questions),
            'debug' => ['matched_pattern' => 'transaction_partial'],
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $normalized
     */
    private function parseWallet(int $userId, string $text, array $context, array $normalized): ?array
    {
        if (! $this->matchesWalletCreateIntent($text)) {
            return null;
        }

        $walletType = $this->extractWalletType($text);
        $walletName = $this->extractWalletName($text, $walletType);
        $initialBalance = $this->extractWalletBalance($text, $normalized, $walletName, $walletType);

        $missing = [];
        if (! $walletName) {
            $missing[] = 'wallet_name';
        }
        if (! $walletType) {
            $missing[] = 'wallet_type';
        }
        if ($initialBalance === null && $this->expectsBalanceInput($text)) {
            $missing[] = 'initial_balance';
        }

        $entities = [
            'wallet_name' => $walletName,
            'wallet_type' => $walletType,
            'initial_balance' => $initialBalance ?? 0,
        ];

        if (empty($missing)) {
            return [
                'matched' => true,
                'intent' => 'parse_wallet',
                'confidence' => self::COMPLETE_CONFIDENCE,
                'requires_action' => true,
                'action_type' => 'create_wallet',
                'entities' => $entities,
                'missing_fields' => [],
                'clarifying_question' => null,
                'debug' => ['matched_pattern' => 'wallet_create_complete'],
            ];
        }

        return [
            'matched' => true,
            'intent' => 'parse_wallet',
            'confidence' => self::PARTIAL_CONFIDENCE,
            'requires_action' => true,
            'action_type' => 'create_wallet',
            'entities' => $entities,
            'missing_fields' => $missing,
            'clarifying_question' => $this->clarificationService->buildWalletClarifyingQuestion($missing, $entities),
            'debug' => ['matched_pattern' => 'wallet_create_partial'],
        ];
    }

    private function matchesWalletCreateIntent(string $text): bool
    {
        if (preg_match('/\bcreate\s+wallet\b/u', $text)) {
            return true;
        }
        if (preg_match('/\b(wallet|dompet|rekening)\s+baru\b/u', $text)) {
            return true;
        }
        if (preg_match('/\b(dompet|rekening)\s+baru\b/u', $text)) {
            return true;
        }
        if (preg_match('/\b(buat|tambah|create)\b/u', $text)
            && preg_match('/\b(wallet|dompet|rekening|ewallet|e-wallet|e wallet)\b/u', $text)) {
            return true;
        }
        if (preg_match('/\b(buat|tambah)\b/u', $text)
            && preg_match('/\b(dompet|rekening)\b/u', $text)) {
            return true;
        }
        if (preg_match('/\btambah\s+(?:ewallet|e-wallet|e wallet)\b/u', $text)) {
            return true;
        }
        if (preg_match('/\bbuat\s+(?:dompet\s+)?tunai\b/u', $text)) {
            return true;
        }

        return false;
    }

    private function extractWalletType(string $text): ?string
    {
        if (preg_match('/\b(?:type|tipe)\s+([a-z0-9\-\s]+?)(?:,|\s+dengan|\s+nama|\s+saldo|\s+\d|$)/u', $text, $m)) {
            $type = $this->resolver->detectExplicitWalletType(trim($m[1]))
                ?? $this->resolver->normalizeWalletType(trim($m[1]));
            if ($type) {
                return $type;
            }
        }

        $explicit = $this->resolver->detectExplicitWalletType($text);
        if ($explicit !== null) {
            return $explicit;
        }

        if (preg_match('/\b(?:buat|tambah)\s+rekening\b/u', $text)) {
            return WalletType::Bank->value;
        }

        foreach (self::BANK_BRANDS as $brand) {
            if (preg_match('/\b(?:rekening|bank)\s+'.preg_quote($brand, '/').'\b/u', $text)) {
                return WalletType::Bank->value;
            }
        }

        return null;
    }

    private function extractWalletName(string $text, ?string $walletType): ?string
    {
        if (preg_match('/\bnama(?:nya)?\s+([a-z0-9\s]+?)(?:,|\s+saldo|\s+type|\s+tipe|\s+dengan|\s+\d|$)/u', $text, $m)) {
            $name = trim($m[1]);
            if ($name !== '' && ! $this->isWalletTypeKeyword($name)) {
                return $name;
            }
        }

        if (preg_match('/\b(?:rekening|bank)\s+([a-z0-9]+)(?:\s+saldo|\s|$)/u', $text, $m)) {
            $candidate = trim($m[1]);
            if (! $this->isWalletTypeKeyword($candidate)) {
                return strtoupper($candidate);
            }
        }

        if (preg_match('/\b(?:ewallet|e-wallet|e wallet)\s+(?:nama\s+)?([a-z0-9]+)/u', $text, $m)) {
            $candidate = trim($m[1]);
            if ($candidate !== 'nama' && ! $this->isWalletTypeKeyword($candidate)) {
                return strtolower($candidate) === $candidate ? strtoupper($candidate) : $candidate;
            }
        }

        if (preg_match('/\b(?:buat|tambah)\s+(?:wallet\s+)?([a-z0-9]+)\s+\d/u', $text, $m)) {
            $candidate = trim($m[1]);
            if (! $this->isWalletTypeKeyword($candidate) && ! in_array($candidate, ['wallet', 'dompet', 'rekening', 'baru'], true)) {
                foreach (self::EWALLET_BRANDS as $brand) {
                    if ($candidate === $brand) {
                        return strtoupper($candidate);
                    }
                }
            }
        }

        if (preg_match('/\b(?:buat|tambah)\s+wallet\s+([a-z0-9]+)\s+\d/u', $text, $m)) {
            $candidate = trim($m[1]);
            if (! $this->isWalletTypeKeyword($candidate)) {
                foreach (self::EWALLET_BRANDS as $brand) {
                    if ($candidate === $brand) {
                        return strtoupper($candidate);
                    }
                }
            }
        }

        foreach (self::EWALLET_BRANDS as $brand) {
            if (preg_match('/\b'.preg_quote($brand, '/').'\b/u', $text)) {
                return strtoupper($brand);
            }
        }

        foreach (self::BANK_BRANDS as $brand) {
            if (preg_match('/\b'.preg_quote($brand, '/').'\b/u', $text)) {
                return strtoupper($brand);
            }
        }

        if (preg_match('/\bbuat\s+dompet\s+tunai\b/u', $text) || preg_match('/\bdompet\s+tunai\b/u', $text)) {
            return 'uang tunai';
        }

        if ($walletType === WalletType::Cash->value && preg_match('/\bcash\s+nama\s+([a-z0-9\s]+)/u', $text, $m)) {
            return trim($m[1]);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    private function extractWalletBalance(string $text, array $normalized, ?string $walletName, ?string $walletType): ?int
    {
        $amount = $this->normalizer->primaryAmount($normalized);
        if ($amount !== null) {
            return $amount;
        }

        if ($walletName && $walletType && ! $this->expectsBalanceInput($text)) {
            return 0;
        }

        return null;
    }

    private function expectsBalanceInput(string $text): bool
    {
        return (bool) preg_match('/\b(saldo|saldo\s+awal|isi|isinya)\b/u', $text);
    }

    private function isWalletTypeKeyword(string $word): bool
    {
        $w = mb_strtolower(trim($word));

        return in_array($w, [
            'cash', 'tunai', 'bank', 'rekening', 'ewallet', 'e-wallet', 'e', 'wallet',
            'dompet', 'baru', 'type', 'tipe', 'nama', 'dengan',
        ], true);
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $normalized
     */
    private function parseBudget(int $userId, string $text, array $context, array $normalized): ?array
    {
        if (! preg_match('/\b(budget|anggaran|batas|batasin|limit)\b/u', $text)) {
            return null;
        }

        $amount = $this->normalizer->primaryAmount($normalized);
        $hasCreateMarker = preg_match('/\b(set|buat|buatkan|catat|atur|batas|batasin)\b/u', $text);
        if (! $hasCreateMarker && ! $amount) {
            return null;
        }

        $categoryRaw = $this->resolver->extractCategoryNameFromText($text);
        $category = $categoryRaw ? $this->resolver->resolveCategory($userId, $categoryRaw) : null;
        $period = 'monthly';
        if (preg_match('/\b(setiap\s+bulan|bulan\s+ini|monthly)\b/u', $text)) {
            $period = 'monthly';
        }

        $missing = [];
        if (! $amount) {
            $missing[] = 'amount';
        }
        if (! $categoryRaw) {
            $missing[] = 'category_name';
        }

        $entities = [
            'amount' => $amount,
            'category_name' => $category['name'] ?? $categoryRaw,
            'category_id' => $category['id'] ?? null,
            'period' => $period,
        ];

        if (empty($missing)) {
            return [
                'matched' => true,
                'intent' => 'parse_budget',
                'confidence' => self::COMPLETE_CONFIDENCE,
                'requires_action' => true,
                'action_type' => 'create_budget',
                'entities' => $entities,
                'missing_fields' => [],
                'clarifying_question' => null,
                'debug' => ['matched_pattern' => 'budget_complete'],
            ];
        }

        return [
            'matched' => true,
            'intent' => 'parse_budget',
            'confidence' => self::PARTIAL_CONFIDENCE,
            'requires_action' => true,
            'action_type' => 'create_budget',
            'entities' => $entities,
            'missing_fields' => $missing,
            'clarifying_question' => ! $amount ? 'Berapa jumlah budget-nya?' : 'Kategori apa yang mau dibuat budget-nya?',
            'debug' => ['matched_pattern' => 'budget_partial'],
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $normalized
     */
    private function parseRecurring(int $userId, string $text, array $context, array $normalized): ?array
    {
        if (! preg_match('/\b(recurring|berulang|langganan|subscription|tagihan)\b/u', $text)) {
            return null;
        }

        $type = $this->detectTransactionType($text);
        $amount = $this->normalizer->primaryAmount($normalized);
        $wallet = $this->resolveTransactionWallet($userId, $text, $type, $context);
        $frequency = $this->detectFrequency($text);

        $description = null;
        if (preg_match('/\b(?:recurring|langganan|tagihan)\s+([a-z0-9]+)/u', $text, $m)) {
            $description = trim($m[1]);
        }

        $missing = array_values(array_filter([
            ! $amount ? 'amount' : null,
            ! $wallet ? 'wallet_name' : null,
        ]));

        $entities = [
            'transaction_type' => $type,
            'amount' => $amount,
            'wallet_name' => $wallet['name'] ?? null,
            'wallet_id' => $wallet['id'] ?? null,
            'description' => $description,
            'frequency' => $frequency,
            'interval' => 1,
            'date' => now()->format('Y-m-d'),
        ];

        if (empty($missing)) {
            return [
                'matched' => true,
                'intent' => 'parse_recurring',
                'confidence' => self::COMPLETE_CONFIDENCE,
                'requires_action' => true,
                'action_type' => 'create_recurring',
                'entities' => $entities,
                'missing_fields' => [],
                'clarifying_question' => null,
                'debug' => ['matched_pattern' => 'recurring_complete'],
            ];
        }

        return [
            'matched' => true,
            'intent' => 'parse_recurring',
            'confidence' => self::PARTIAL_CONFIDENCE,
            'requires_action' => true,
            'action_type' => 'create_recurring',
            'entities' => $entities,
            'missing_fields' => $missing,
            'clarifying_question' => 'Sebutkan jumlah, wallet, dan frekuensi. Contoh: recurring netflix 150 ribu tiap bulan dari gopay.',
            'debug' => ['matched_pattern' => 'recurring_partial'],
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function parseSpecificWalletBalance(int $userId, string $text, array $context): ?array
    {
        if (! preg_match('/\b(saldo|balance)\b/u', $text)) {
            return null;
        }

        $walletName = null;
        if (preg_match('/\bsaldo\s+([a-z0-9]+)/u', $text, $m)) {
            $walletName = trim($m[1]);
        } elseif (preg_match('/\bberapa\s+saldo(?:\s+wallet)?\s+([a-z0-9]+)/u', $text, $m)) {
            $walletName = trim($m[1]);
        } elseif (preg_match('/\b([a-z0-9]+)\s+(?:saldo|balance)\b/u', $text, $m)) {
            $walletName = trim($m[1]);
        }

        if (! $walletName || in_array($walletName, ['berapa', 'wallet', 'dompet', 'saya', 'gue', 'ku', 'total', 'semua'], true)) {
            return null;
        }

        $wallet = $this->resolver->resolveWallet($userId, $walletName, $context);
        if (! $wallet) {
            return null;
        }

        return [
            'matched' => true,
            'intent' => 'ask_specific_wallet_balance',
            'confidence' => self::COMPLETE_CONFIDENCE,
            'requires_action' => false,
            'action_type' => null,
            'entities' => ['wallet_name' => $wallet['name'], 'wallet_id' => $wallet['id']],
            'missing_fields' => [],
            'clarifying_question' => null,
            'debug' => ['matched_pattern' => 'specific_wallet_balance'],
        ];
    }

    private function parseAllWalletBalance(string $text): ?array
    {
        if ($text === '/wallets') {
            return [
                'matched' => true,
                'intent' => 'ask_wallet_balance',
                'confidence' => self::COMPLETE_CONFIDENCE,
                'requires_action' => false,
                'action_type' => null,
                'entities' => [],
                'missing_fields' => [],
                'clarifying_question' => null,
                'debug' => ['matched_pattern' => 'all_wallets_command'],
            ];
        }

        if (preg_match('/\b(saldo|balance)\b/u', $text)
            && preg_match('/\b(berapa|total|semua|all|gue|saya|ku)\b/u', $text)) {
            return [
                'matched' => true,
                'intent' => 'ask_wallet_balance',
                'confidence' => self::COMPLETE_CONFIDENCE,
                'requires_action' => false,
                'action_type' => null,
                'entities' => [],
                'missing_fields' => [],
                'clarifying_question' => null,
                'debug' => ['matched_pattern' => 'all_wallet_balance'],
            ];
        }

        return null;
    }

    private function parseForecast(string $text): ?array
    {
        if (! preg_match('/\b(forecast|proyeksi|ramalan)\b/u', $text) && $text !== '/forecast') {
            return null;
        }

        return [
            'matched' => true,
            'intent' => 'ask_forecast',
            'confidence' => self::COMPLETE_CONFIDENCE,
            'requires_action' => false,
            'action_type' => null,
            'entities' => [],
            'missing_fields' => [],
            'clarifying_question' => null,
            'debug' => ['matched_pattern' => 'forecast'],
        ];
    }

    private function parseSummary(string $text): ?array
    {
        if (preg_match('/\b(budget|anggaran|transfer|catat|recurring)\b/u', $text)) {
            return null;
        }

        if (! preg_match('/\b(summary|ringkasan|bulan ini|keuangan)\b/u', $text) && $text !== '/summary') {
            return null;
        }

        return [
            'matched' => true,
            'intent' => 'ask_summary',
            'confidence' => self::COMPLETE_CONFIDENCE,
            'requires_action' => false,
            'action_type' => null,
            'entities' => [],
            'missing_fields' => [],
            'clarifying_question' => null,
            'debug' => ['matched_pattern' => 'summary'],
        ];
    }

    private function parseBudgetStatus(string $text): ?array
    {
        if (! preg_match('/\b(budget|anggaran)\b/u', $text)) {
            return null;
        }
        if (preg_match('/\b(set|buat|buatkan|catat|atur|batas|batasin)\b/u', $text)) {
            return null;
        }

        return [
            'matched' => true,
            'intent' => 'ask_budget_status',
            'confidence' => self::COMPLETE_CONFIDENCE,
            'requires_action' => false,
            'action_type' => null,
            'entities' => [],
            'missing_fields' => [],
            'clarifying_question' => null,
            'debug' => ['matched_pattern' => 'budget_status'],
        ];
    }

    private function parseConfirm(string $text): ?array
    {
        if (! preg_match('/^(yes|ya|y|confirm|ok|setuju)$/u', $text)) {
            return null;
        }

        return [
            'matched' => true,
            'intent' => 'confirm',
            'confidence' => 1.0,
            'requires_action' => false,
            'action_type' => null,
            'entities' => [],
            'missing_fields' => [],
            'clarifying_question' => null,
            'debug' => ['matched_pattern' => 'confirm'],
        ];
    }

    private function parseCancel(string $text): ?array
    {
        if (! preg_match('/^(cancel|batal|no|tidak)$/u', $text)) {
            return null;
        }

        return [
            'matched' => true,
            'intent' => 'cancel',
            'confidence' => 1.0,
            'requires_action' => false,
            'action_type' => null,
            'entities' => [],
            'missing_fields' => [],
            'clarifying_question' => null,
            'debug' => ['matched_pattern' => 'cancel'],
        ];
    }

    private function parseHelp(string $text): ?array
    {
        if (! preg_match('/\b(help|bantuan|\/help)\b/u', $text)) {
            return null;
        }

        return [
            'matched' => true,
            'intent' => 'help',
            'confidence' => self::COMPLETE_CONFIDENCE,
            'requires_action' => false,
            'action_type' => null,
            'entities' => [],
            'missing_fields' => [],
            'clarifying_question' => null,
            'debug' => ['matched_pattern' => 'help'],
        ];
    }

    private function detectTransactionType(string $text): string
    {
        if (preg_match('/\b(income|pemasukan|gaji|terima|masuk|freelance|bonus)\b/u', $text)) {
            return 'income';
        }

        return 'expense';
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{id: int, name: string}|null
     */
    private function resolveTransactionWallet(int $userId, string $text, string $type, array $context): ?array
    {
        if ($type === 'income') {
            if (preg_match('/\b(?:ke|masuk\s+ke)\s+([a-z0-9]+)/u', $text, $m)) {
                return $this->resolver->resolveWallet($userId, trim($m[1]), $context);
            }
            if (preg_match('/\bdari\s+([a-z0-9]+)/u', $text, $m)) {
                return $this->resolver->resolveWallet($userId, trim($m[1]), $context);
            }
        }

        if (preg_match('/\b(?:dari|pakai|pake)\s+([a-z0-9]+)/u', $text, $m)) {
            return $this->resolver->resolveWallet($userId, trim($m[1]), $context);
        }

        return $this->resolver->findWalletMention($userId, $text, $context);
    }

    private function detectFrequency(string $text): string
    {
        return match (true) {
            preg_match('/\b(harian|tiap\s+hari|daily)\b/u', $text) => 'daily',
            preg_match('/\b(mingguan|tiap\s+minggu|weekly)\b/u', $text) => 'weekly',
            preg_match('/\b(tahunan|tiap\s+tahun|yearly)\b/u', $text) => 'yearly',
            default => 'monthly',
        };
    }

    /**
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $normalized
     * @return array<string, mixed>
     */
    private function finalize(array $result, array $normalized): array
    {
        return array_merge([
            'matched' => false,
            'intent' => 'unknown',
            'confidence' => 0.0,
            'language' => 'id',
            'requires_action' => false,
            'action_type' => null,
            'entities' => [],
            'missing_fields' => [],
            'clarifying_question' => null,
            'safety_flags' => [],
            'model_called' => false,
            'source' => 'rules',
        ], $result, [
            'debug' => array_merge([
                'normalized_text' => $normalized['normalized_text'],
                'amount_candidates' => $this->normalizer->amountValues($normalized),
            ], $result['debug'] ?? []),
        ]);
    }
}
