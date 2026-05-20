<?php

namespace App\Services\AI;

class FinanceAIGuardrailService
{
    private const ALLOWED_INTENTS = [
        'ask_summary',
        'ask_wallet_balance',
        'ask_specific_wallet_balance',
        'ask_spending_analysis',
        'ask_budget_status',
        'ask_forecast',
        'parse_transaction',
        'parse_transfer',
        'parse_budget',
        'parse_recurring',
        'parse_wallet',
        'help',
        'unknown',
        'out_of_scope',
        'confirm',
        'cancel',
    ];

    private const WRITE_ACTIONS = [
        'create_transaction',
        'create_transfer',
        'create_budget',
        'create_recurring',
        'create_wallet',
    ];

    private const FINANCE_KEYWORDS = [
        'saldo', 'balance', 'wallet', 'dompet', 'rekening', 'e-wallet', 'ewallet',
        'transaksi', 'transaction', 'pengeluaran', 'expense', 'pemasukan', 'income',
        'transfer', 'pindah', 'budget', 'anggaran', 'recurring', 'berulang', 'tagihan',
        'cashflow', 'forecast', 'proyeksi', 'laporan', 'ringkasan', 'summary',
        'kategori', 'category', 'catat', 'tambah', 'simpan', 'hutang', 'piutang',
        'buat wallet', 'tambah wallet', 'dompet baru', 'cash', 'uang tunai', 'tunai',
    ];

    private const OUT_OF_SCOPE_KEYWORDS = [
        'resep', 'nasi goreng', 'masak', 'makanan', 'kuliner',
        'coding', 'program', 'python', 'javascript', 'laravel',
        'hack', 'hacking', 'politik', 'politician', 'hiburan', 'film', 'musik',
        'kesehatan', 'medis', 'obat', 'relationship', 'pacaran', 'travel', 'liburan',
        'matematika umum', 'soal matematika', 'random', 'cerita lucu',
    ];

    public function __construct(
        protected FinanceNLPNormalizerService $normalizer,
    ) {}

    /**
     * Fast rule-based pre-gate before LLM or response generation.
     *
     * @return array{allowed: bool, reason: string, risk_level: string, message: string|null, max_tokens: int, flags: list<string>}
     */
    public function preGate(string $input): array
    {
        $normalized = $this->normalizer->normalize($input);
        $text = $normalized['normalized_text'];
        $maxTokens = (int) config('ai.out_of_scope_max_tokens', 300);

        $risk = $this->classifyRisk($input);
        if (! $risk['allowed']) {
            return [
                'allowed' => false,
                'reason' => $this->mapFlagToReason($risk['flags']),
                'risk_level' => $risk['risk_level'],
                'message' => $risk['message'],
                'max_tokens' => $maxTokens,
                'flags' => $risk['flags'],
            ];
        }

        $hasFinance = $this->containsAny($text, self::FINANCE_KEYWORDS);
        $hasOos = $this->containsAny($text, self::OUT_OF_SCOPE_KEYWORDS);

        if ($hasOos && ! $hasFinance) {
            return [
                'allowed' => false,
                'reason' => 'out_of_scope',
                'risk_level' => 'low',
                'message' => $this->outOfScopeMessage(),
                'max_tokens' => $maxTokens,
                'flags' => ['out_of_scope'],
            ];
        }

        if ($hasOos && $hasFinance) {
            return [
                'allowed' => true,
                'reason' => 'ambiguous',
                'risk_level' => 'low',
                'message' => $this->mixedScopeMessage(),
                'max_tokens' => $maxTokens,
                'flags' => ['out_of_scope_mixed'],
            ];
        }

        return [
            'allowed' => true,
            'reason' => 'finance_context',
            'risk_level' => 'low',
            'message' => null,
            'max_tokens' => $maxTokens,
            'flags' => $risk['flags'],
        ];
    }

    /**
     * @return array{allowed: bool, risk_level: string, requires_confirmation: bool, flags: list<string>, message: string|null}
     */
    public function classifyRisk(string $input): array
    {
        $lower = mb_strtolower($input);
        $flags = [];

        if (preg_match('/\b(api[_\s]?key|openai|dashscope|secret|token|password|\.env|env\s*variable|system\s*prompt)\b/ui', $lower)) {
            return $this->blocked('low', ['secret_request'], 'Saya tidak bisa membagikan informasi internal atau kredensial sistem.');
        }

        if (preg_match('/\b(hapus|delete)\b.*\b(semua|all)\b.*\b(transaksi|transaction)/ui', $lower)) {
            return $this->blocked('high', ['destructive_action'], 'Penghapusan massal transaksi tidak didukung melalui asisten AI.');
        }

        if (preg_match('/\b(hapus|delete)\b.*\b(transaksi|transaction|wallet|budget)/ui', $lower)) {
            return $this->blocked('high', ['delete_not_supported'], 'Penghapusan data melalui AI belum didukung. Gunakan aplikasi web.');
        }

        if (preg_match('/\b(beli|buy|jual|sell)\b.*\b(saham|stock|crypto|bitcoin|btc|eth)\b/ui', $lower)
            || preg_match('/\b(rekomendasi|recommend).*\b(beli|buy|jual|sell)/ui', $lower)) {
            $flags[] = 'investment_advice';

            return [
                'allowed' => true,
                'risk_level' => 'medium',
                'requires_confirmation' => false,
                'flags' => $flags,
                'message' => null,
            ];
        }

        if (preg_match('/\b(user\s*id|data\s*user\s*lain|akun\s*orang\s*lain)/ui', $lower)) {
            return $this->blocked('high', ['cross_user_access'], 'Saya hanya bisa mengakses data keuangan akun kamu yang terhubung.');
        }

        return [
            'allowed' => true,
            'risk_level' => 'low',
            'requires_confirmation' => false,
            'flags' => $flags,
            'message' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $structuredIntent
     * @return array{valid: bool, message: string|null, flags: list<string>}
     */
    public function validateIntent(array $structuredIntent): array
    {
        $intent = $structuredIntent['intent'] ?? 'unknown';

        if (! in_array($intent, self::ALLOWED_INTENTS, true)) {
            return ['valid' => false, 'message' => 'Intent tidak dikenali.', 'flags' => []];
        }

        if ($intent === 'out_of_scope') {
            return ['valid' => false, 'message' => $this->outOfScopeMessage(), 'flags' => ['out_of_scope']];
        }

        if (str_starts_with($intent, 'delete_')) {
            return ['valid' => false, 'message' => 'Aksi hapus tidak didukung.', 'flags' => ['delete_not_supported']];
        }

        return ['valid' => true, 'message' => null, 'flags' => $structuredIntent['safety_flags'] ?? []];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{valid: bool, message: string|null, warnings: list<string>}
     */
    public function validateActionPayload(int $userId, string $actionType, array $payload): array
    {
        $warnings = [];

        if (! in_array($actionType, self::WRITE_ACTIONS, true)) {
            return ['valid' => false, 'message' => 'Tipe aksi tidak didukung.', 'warnings' => []];
        }

        if ($actionType === 'create_transaction') {
            if (empty($payload['wallet_id']) || empty($payload['type']) || empty($payload['amount'])) {
                return ['valid' => false, 'message' => 'Wallet, tipe, dan jumlah wajib diisi.', 'warnings' => []];
            }
            if ((float) $payload['amount'] <= 0) {
                return ['valid' => false, 'message' => 'Jumlah harus lebih dari 0.', 'warnings' => []];
            }
        }

        if ($actionType === 'create_transfer') {
            if (empty($payload['from_wallet_id']) || empty($payload['to_wallet_id']) || empty($payload['amount'])) {
                return ['valid' => false, 'message' => 'Wallet asal, tujuan, dan jumlah wajib diisi.', 'warnings' => []];
            }
            if ($payload['from_wallet_id'] === $payload['to_wallet_id']) {
                return ['valid' => false, 'message' => 'Wallet asal dan tujuan harus berbeda.', 'warnings' => []];
            }
            if (! empty($payload['balance_warning'])) {
                $warnings[] = 'balance_insufficient';
            }
        }

        if ($actionType === 'create_budget') {
            if (empty($payload['category_id']) || empty($payload['amount'])) {
                return ['valid' => false, 'message' => 'Kategori dan jumlah budget wajib diisi.', 'warnings' => []];
            }
        }

        if ($actionType === 'create_recurring') {
            if (empty($payload['wallet_id']) || empty($payload['amount']) || empty($payload['start_date'])) {
                return ['valid' => false, 'message' => 'Wallet, jumlah, dan tanggal mulai wajib diisi.', 'warnings' => []];
            }
        }

        if ($actionType === 'create_wallet') {
            if (empty($payload['name']) || empty($payload['type'])) {
                return ['valid' => false, 'message' => 'Nama dan tipe wallet wajib diisi.', 'warnings' => []];
            }
            if (isset($payload['initial_balance']) && (float) $payload['initial_balance'] < 0) {
                return ['valid' => false, 'message' => 'Saldo awal tidak boleh negatif.', 'warnings' => []];
            }
        }

        return ['valid' => true, 'message' => null, 'warnings' => $warnings];
    }

    public function sanitizeAssistantResponse(string $content, array $flags = []): string
    {
        $content = preg_replace('/\b(sk-[a-zA-Z0-9]{10,})\b/', '[redacted]', $content) ?? $content;

        if (in_array('investment_advice', $flags, true) && ! str_contains(mb_strtolower($content), 'bukan saran investasi')) {
            $content .= "\n\nCatatan: Ini bukan saran investasi resmi. Keputusan investasi sepenuhnya tanggung jawab kamu.";
        }

        return trim($content);
    }

    public function requiresConfirmation(string $actionType): bool
    {
        return in_array($actionType, self::WRITE_ACTIONS, true);
    }

    public function outOfScopeMessage(): string
    {
        return 'Aku hanya bisa membantu hal yang berkaitan dengan Flowlet: saldo, wallet, transaksi, budget, recurring, dan forecast. '
            .'Untuk pertanyaan di luar itu, aku tidak bisa membantu di sini. Mau lanjut bahas anggaran atau transaksi kamu?';
    }

    public function mixedScopeMessage(): string
    {
        return 'Aku bisa bantu bagian finance-nya, tapi tidak untuk topik di luar Flowlet. '
            .'Mau saya bantu buat budget, cek saldo, atau catat transaksi?';
    }

    /**
     * @param  list<string>  $flags
     */
    private function mapFlagToReason(array $flags): string
    {
        if (in_array('secret_request', $flags, true)) {
            return 'secret_request';
        }
        if (in_array('delete_not_supported', $flags, true) || in_array('destructive_action', $flags, true)) {
            return 'unsafe';
        }
        if (in_array('cross_user_access', $flags, true)) {
            return 'unsafe';
        }

        return 'unsafe';
    }

    /**
     * @param  list<string>  $needles
     */
    private function containsAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($text, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<string>  $flags
     * @return array{allowed: bool, risk_level: string, requires_confirmation: bool, flags: list<string>, message: string}
     */
    private function blocked(string $riskLevel, array $flags, string $message): array
    {
        return [
            'allowed' => false,
            'risk_level' => $riskLevel,
            'requires_confirmation' => false,
            'flags' => $flags,
            'message' => $message,
        ];
    }
}
