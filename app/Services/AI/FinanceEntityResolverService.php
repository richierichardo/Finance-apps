<?php

namespace App\Services\AI;

use App\Enums\WalletType;
use App\Models\Category;
use App\Models\Wallet;

class FinanceEntityResolverService
{
    /** @var array<string, string> */
    private const WALLET_ALIASES = [
        'shoppepay' => 'shopeepay',
        'shopee pay' => 'shopeepay',
        'go pay' => 'gopay',
    ];

    public function normalizeKey(string $value): string
    {
        $key = mb_strtolower(trim($value));
        $key = preg_replace('/[\s\-_]+/u', '', $key) ?? $key;

        return self::WALLET_ALIASES[$key] ?? $key;
    }

    /**
     * @return array{id: int, name: string, type: string|null, balance: float}|null
     */
    public function resolveWallet(int $userId, string $name, array $context = []): ?array
    {
        $wallets = $this->walletsFromContext($userId, $context);
        $needle = $this->normalizeKey($name);

        $exact = [];
        $contains = [];

        foreach ($wallets as $wallet) {
            $haystack = $this->normalizeKey($wallet['name']);
            if ($haystack === $needle) {
                $exact[] = $wallet;
            } elseif (str_contains($haystack, $needle) || str_contains($needle, $haystack)) {
                $contains[] = $wallet;
            }
        }

        if (count($exact) === 1) {
            return $exact[0];
        }
        if (count($exact) > 1) {
            return $exact[0];
        }
        if (count($contains) === 1) {
            return $contains[0];
        }

        return null;
    }

    /**
     * @return array{id: int, name: string, type: string|null, balance: float}|null
     */
    public function findWalletMention(int $userId, string $text, array $context = []): ?array
    {
        $textKey = $this->normalizeKey($text);
        $best = null;
        $bestLen = 0;

        foreach ($this->walletsFromContext($userId, $context) as $wallet) {
            $key = $this->normalizeKey($wallet['name']);
            if ($key !== '' && str_contains($textKey, $key) && strlen($key) > $bestLen) {
                $best = $wallet;
                $bestLen = strlen($key);
            }
        }

        return $best;
    }

    /**
     * @return array{from: array{id: int, name: string}|null, to: array{id: int, name: string}|null, ambiguous: list<string>}
     */
    public function findWalletsInTransfer(int $userId, string $text, array $context = []): array
    {
        $wallets = $this->walletsFromContext($userId, $context);
        $textKey = $this->normalizeKey($text);
        $found = [];

        foreach ($wallets as $wallet) {
            $key = $this->normalizeKey($wallet['name']);
            if ($key !== '' && str_contains($textKey, $key)) {
                $found[] = ['wallet' => $wallet, 'pos' => mb_strpos($textKey, $key)];
            }
        }

        usort($found, fn ($a, $b) => $a['pos'] <=> $b['pos']);

        $from = null;
        $to = null;

        if (preg_match('/\b(?:dari|from)\s+([a-z0-9]+)/u', $text, $m)) {
            $from = $this->resolveWallet($userId, trim($m[1]), $context);
        }
        if (preg_match('/\b(?:ke|to)\s+([a-z0-9]+)/u', $text, $m)) {
            $to = $this->resolveWallet($userId, trim($m[1]), $context);
        }

        if ((! $from || ! $to) && count($found) >= 2) {
            $from ??= $found[0]['wallet'];
            $to ??= $found[1]['wallet'];
        }

        if ((! $from || ! $to) && preg_match('/\b([a-z0-9]+)\s+ke\s+([a-z0-9]+)/u', $text, $m)) {
            $from ??= $this->resolveWallet($userId, trim($m[1]), $context);
            $to ??= $this->resolveWallet($userId, trim($m[2]), $context);
        }

        return [
            'from' => $from,
            'to' => $to,
            'ambiguous' => [],
        ];
    }

    public function matchWalletName(?string $walletName, array $context): ?string
    {
        if (! $walletName) {
            return null;
        }

        $userId = (int) ($context['user_id'] ?? 0);
        $resolved = $this->resolveWallet($userId, $walletName, $context);

        return $resolved['name'] ?? null;
    }

    /**
     * @return array{id: int, name: string, slug: string}|null
     */
    public function resolveCategory(int $userId, string $name): ?array
    {
        $needle = mb_strtolower(trim($name));

        $category = Category::query()
            ->whereRaw('LOWER(name) = ?', [$needle])
            ->orWhereRaw('LOWER(slug) = ?', [$needle])
            ->first();

        if (! $category) {
            if (str_contains($needle, 'makan') || str_contains($needle, 'food')) {
                $category = Category::query()->whereRaw('LOWER(name) LIKE ?', ['%makan%'])->first();
            }
            if (str_contains($needle, 'transport')) {
                $category = Category::query()->whereRaw('LOWER(name) LIKE ?', ['%transport%'])->first();
            }
        }

        if (! $category) {
            return null;
        }

        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug,
        ];
    }

    /**
     * @return array{id: int, name: string, slug: string}|null
     */
    public function findCategoryMention(int $userId, string $text): ?array
    {
        $rawName = $this->extractCategoryNameFromText($text);
        if ($rawName === null) {
            return null;
        }

        return $this->resolveCategory($userId, $rawName);
    }

    public function extractCategoryNameFromText(string $text): ?string
    {
        $patterns = [
            '/\bset\s+budget\s+(?:buat\s+)?kategori\s+([a-z0-9]+)/u',
            '/\bbuat\s+kategori\s+([a-z0-9]+)/u',
            '/\b(?:category|kategori)\s+([a-z0-9]+)/u',
            '/\b(?:budget|anggaran|batas(?:in)?)\s+([a-z0-9]+)/u',
            '/\b(?:budget|anggaran|batas(?:in)?)\s+(?:untuk\s+)?([a-z0-9]+?)(?:\s+\d|\s+jt|\s+juta|\s+ribu|\s+setiap|\s+bulan|\s+monthly|$)/u',
            '/\b(?:untuk|kategori)\s+([a-z0-9]+?)(?:\s+\d|\s+jt|\s+juta|\s+ribu|\s+setiap|\s+bulan|$)/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $text, $m)) {
                $name = trim($m[1]);
                if ($name !== '' && ! in_array($name, ['budget', 'anggaran', 'set', 'buat', 'category', 'kategori'], true)) {
                    return $name;
                }
            }
        }

        return null;
    }

    public function normalizeWalletType(string $text): ?string
    {
        $explicit = $this->detectExplicitWalletType($text);
        if ($explicit !== null) {
            return $explicit;
        }

        $key = mb_strtolower(trim($text));

        if (preg_match('/\b(gopay|shopeepay|ovo|dana|linkaja)\b/u', $key)) {
            return WalletType::Ewallet->value;
        }

        return null;
    }

    /**
     * Detect wallet type from explicit type keywords only (not e-wallet brand names).
     */
    public function detectExplicitWalletType(string $text): ?string
    {
        $key = mb_strtolower(trim($text));

        if (preg_match('/\b(cash|tunai|uang\s*tunai|dompet\s*fisik|uang\s*cash)\b/u', $key)) {
            return WalletType::Cash->value;
        }
        if (preg_match('/\b(?:e-wallet|e wallet|ewallet)\b/u', $key)) {
            return WalletType::Ewallet->value;
        }
        if (preg_match('/\b(bank|rekening)\b/u', $key)) {
            return WalletType::Bank->value;
        }

        return match ($key) {
            'cash', 'tunai' => WalletType::Cash->value,
            'bank', 'rekening' => WalletType::Bank->value,
            'ewallet', 'e-wallet' => WalletType::Ewallet->value,
            default => null,
        };
    }

    /**
     * @return list<array{id: int, name: string, type: string|null, balance: float}>
     */
    private function walletsFromContext(int $userId, array $context): array
    {
        if (! empty($context['wallets'])) {
            return array_map(fn ($w) => [
                'id' => (int) $w['id'],
                'name' => $w['name'],
                'type' => $w['type'] ?? null,
                'balance' => (float) ($w['balance'] ?? 0),
            ], $context['wallets']);
        }

        return Wallet::belongsToUser($userId)
            ->get(['id', 'name', 'type', 'balance'])
            ->map(fn (Wallet $w) => [
                'id' => $w->id,
                'name' => $w->name,
                'type' => $w->type?->value ?? (string) $w->type,
                'balance' => (float) $w->balance,
            ])
            ->values()
            ->all();
    }
}
