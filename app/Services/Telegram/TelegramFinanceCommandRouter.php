<?php

namespace App\Services\Telegram;

use App\Models\AiActionDraft;
use App\Services\AI\FinanceAICommandParserService;
use App\Services\AI\FinanceAIContextService;
use App\Services\AI\FinanceAIOrchestratorService;
use App\Services\AI\FinanceEntityResolverService;
use App\Services\AI\FinanceNLPNormalizerService;
use App\Services\ForecastInsightService;
class TelegramFinanceCommandRouter
{
    public function __construct(
        protected FinanceAIOrchestratorService $orchestrator,
        protected FinanceAICommandParserService $commandParser,
        protected FinanceNLPNormalizerService $normalizer,
        protected FinanceAIContextService $contextService,
        protected FinanceEntityResolverService $entityResolver,
        protected ForecastInsightService $forecastInsightService,
    ) {}

    /**
     * @param  array<string, mixed>  $telegramContext
     * @return array<string, mixed>
     */
    public function handle(int $userId, string $text, array $telegramContext = []): array
    {
        $text = trim($text);
        if ($text === '') {
            return $this->response('error', 'Pesan tidak boleh kosong.');
        }

        $normalized = $this->normalizer->normalize($text);
        $cancelledNote = null;

        $hard = $this->handleHardCommand($userId, $text, $normalized, $telegramContext);
        if ($hard !== null) {
            $this->cancelActiveDrafts($userId, $telegramContext, AiActionDraft::STATUS_COLLECTING);

            return $hard;
        }

        $readOnly = $this->handleReadOnlyQuery($userId, $text, $normalized, $telegramContext);
        if ($readOnly !== null) {
            $this->cancelActiveDrafts($userId, $telegramContext, AiActionDraft::STATUS_COLLECTING);

            return $readOnly;
        }

        $collecting = $this->findCollectingDraft($userId, $telegramContext);

        if ($collecting && $this->looksLikeNewIntent($text, $normalized)) {
            $this->cancelDraft($collecting);
            $cancelledNote = 'Aksi sebelumnya saya batalkan karena kamu mengirim perintah baru.';
        } elseif ($collecting && $this->isValidSlotAnswer($text, $normalized, $collecting)) {
            $slotResult = $this->handlePendingSlot($userId, $text, $normalized, $telegramContext, $collecting);
            if ($slotResult !== null) {
                return $slotResult;
            }
        } elseif ($collecting) {
            $expected = $collecting->payload['expected_next_field'] ?? ($collecting->payload['missing_fields'][0] ?? null);
            $clarification = $this->clarificationForExpectedField($expected, $collecting->action_type, $collecting->payload['partial_entities'] ?? []);

            return $this->response('clarification', $clarification, [
                'intent' => 'slot_fill',
                'action_type' => $collecting->action_type,
                'draft_id' => $collecting->id,
            ]);
        }

        $result = $this->handleNaturalLanguage($userId, $text, $telegramContext);

        if ($cancelledNote && ($result['ok'] ?? false)) {
            $result['message'] = $cancelledNote."\n\n".($result['message'] ?? '');
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @param  array<string, mixed>  $telegramContext
     * @return array<string, mixed>|null
     */
    private function handleHardCommand(int $userId, string $text, array $normalized, array $telegramContext): ?array
    {
        $commandKey = TelegramBotMessages::parseSlashCommand($text);

        if ($commandKey === null) {
            return null;
        }

        if (TelegramBotMessages::isTutorialCommand($commandKey)) {
            $tutorial = TelegramBotMessages::tutorial($commandKey);

            return $this->response('answer', $tutorial ?? TelegramBotMessages::help(), [
                'intent' => 'help',
                'tutorial' => $commandKey,
            ]);
        }

        return match ($commandKey) {
            'start' => $this->response('answer', TelegramBotMessages::welcome()),
            'help' => $this->response('answer', TelegramBotMessages::help()),
            'wallets' => $this->response('answer', $this->formatWalletsMessage($userId), ['intent' => 'ask_wallet_balance']),
            'summary' => $this->response('answer', $this->formatSummaryMessage($userId), ['intent' => 'ask_summary']),
            'forecast' => $this->response('answer', $this->formatForecastMessage($userId), ['intent' => 'ask_forecast']),
            'cancel' => $this->handleCancel($userId, $telegramContext),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @param  array<string, mixed>  $telegramContext
     * @return array<string, mixed>|null
     */
    private function handleReadOnlyQuery(int $userId, string $text, array $normalized, array $telegramContext): ?array
    {
        $norm = $normalized['normalized_text'] ?? mb_strtolower($text);

        if (preg_match('/^(cancel|batal|tidak|no)$/u', $norm)) {
            return $this->handleCancel($userId, $telegramContext);
        }

        $periodKey = $this->contextService->currentPeriodKey();
        $context = $this->contextService->buildDashboardContext($userId, $periodKey);
        $parse = $this->commandParser->parse($userId, $text, $context);

        if (($parse['intent'] ?? '') === 'ask_specific_wallet_balance') {
            $walletName = $parse['entities']['wallet_name'] ?? null;
            if ($walletName) {
                foreach ($context['wallets'] ?? [] as $w) {
                    if ($w['name'] === $walletName) {
                        $fmt = 'Rp '.number_format((float) $w['balance'], 0, ',', '.');

                        return $this->response('answer', "Saldo {$w['name']} saat ini {$fmt}.", [
                            'intent' => 'ask_specific_wallet_balance',
                        ]);
                    }
                }
            }
        }

        if (($parse['intent'] ?? '') === 'ask_wallet_balance' && ($parse['confidence'] ?? 0) >= 0.85) {
            return $this->response('answer', $this->formatWalletsMessage($userId), ['intent' => 'ask_wallet_balance']);
        }

        if ($norm === '/summary' || preg_match('/\b(ringkasan|summary)\b/u', $norm)) {
            return $this->response('answer', $this->formatSummaryMessage($userId), ['intent' => 'ask_summary']);
        }

        if ($norm === '/forecast' || preg_match('/\b(forecast|proyeksi|ramalan)\b/u', $norm)) {
            return $this->response('answer', $this->formatForecastMessage($userId), ['intent' => 'ask_forecast']);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $telegramContext
     * @return array<string, mixed>
     */
    private function handleCancel(int $userId, array $telegramContext): array
    {
        $cancelled = 0;
        foreach ([AiActionDraft::STATUS_COLLECTING, AiActionDraft::STATUS_PENDING] as $status) {
            $drafts = $this->draftQuery($userId, $telegramContext)
                ->where('status', $status)
                ->where('expires_at', '>', now())
                ->get();

            foreach ($drafts as $draft) {
                $this->cancelDraft($draft);
                $cancelled++;
            }
        }

        if ($cancelled === 0) {
            return $this->response('cancelled', 'Tidak ada aksi yang dibatalkan.');
        }

        return $this->response('cancelled', 'Aksi pending dibatalkan.');
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @param  array<string, mixed>  $telegramContext
     * @return array<string, mixed>|null
     */
    private function handlePendingSlot(
        int $userId,
        string $text,
        array $normalized,
        array $telegramContext,
        AiActionDraft $draft,
    ): ?array {
        return $this->orchestrator->handleUserMessage($userId, $text, 'telegram', array_merge($telegramContext, [
            'only_slot_fill' => true,
            'skip_collecting_draft' => false,
        ]));
    }

    /**
     * @param  array<string, mixed>  $telegramContext
     * @return array<string, mixed>
     */
    private function handleNaturalLanguage(int $userId, string $text, array $telegramContext): array
    {
        return $this->orchestrator->handleUserMessage($userId, $text, 'telegram', array_merge($telegramContext, [
            'skip_collecting_draft' => true,
        ]));
    }

    public function formatWalletsMessage(int $userId): string
    {
        $wallets = $this->contextService->buildWalletContext($userId)['wallets'] ?? [];
        $total = array_sum(array_column($wallets, 'balance'));
        $fmt = fn (float $n) => 'Rp '.number_format($n, 0, ',', '.');
        $lines = ["Total saldo: {$fmt($total)}.", 'Rinciannya:'];
        foreach ($wallets as $w) {
            $lines[] = '- '.$w['name'].': '.$fmt((float) $w['balance']);
        }

        return implode("\n", $lines);
    }

    public function formatSummaryMessage(int $userId): string
    {
        $periodKey = $this->contextService->currentPeriodKey();
        $context = $this->contextService->buildDashboardContext($userId, $periodKey);
        $s = $context['summary'] ?? [];
        $fmt = fn (float $n) => 'Rp '.number_format($n, 0, ',', '.');

        return sprintf(
            "Ringkasan bulan %s:\nIncome: %s\nExpense: %s\nNet: %s\nTotal Balance: %s",
            $periodKey,
            $fmt((float) ($s['total_income'] ?? 0)),
            $fmt((float) ($s['total_expense'] ?? 0)),
            $fmt((float) ($s['net_cashflow'] ?? 0)),
            $fmt((float) ($s['total_balance'] ?? 0))
        );
    }

    public function formatForecastMessage(int $userId): string
    {
        $periodKey = $this->contextService->currentPeriodKey();
        $forecast = $this->forecastInsightService->generateForecastInsight($userId, $periodKey);

        if (empty($forecast['forecast_available'])) {
            return $forecast['message'] ?? 'Forecast belum tersedia untuk periode ini.';
        }

        $fmt = fn (float $n) => 'Rp '.number_format($n, 0, ',', '.');

        return sprintf(
            "Forecast (beta) bulan ini:\nIncome: %s\nExpense: %s\nNet: %s\n\n%s",
            $fmt((float) ($forecast['projected_income'] ?? 0)),
            $fmt((float) ($forecast['projected_expense'] ?? 0)),
            $fmt((float) ($forecast['projected_net'] ?? 0)),
            $forecast['message'] ?? 'Perkiraan berdasarkan rata-rata historis.'
        );
    }

  /**
   * @param  array<string, mixed>  $normalized
   */
    public function looksLikeNewIntent(string $text, array $normalized): bool
    {
        if (str_starts_with(trim($text), '/')) {
            return true;
        }

        $norm = $normalized['normalized_text'] ?? mb_strtolower($text);

        return (bool) preg_match(
            '/\b(transfer|tf|trf|pindah)\b/u',
            $norm
        ) || (bool) preg_match(
            '/\b(buat|tambah)\b.*\b(wallet|dompet|rekening)\b/u',
            $norm
        ) || (bool) preg_match(
            '/\b(wallet|dompet|rekening)\s+baru\b/u',
            $norm
        ) || (bool) preg_match(
            '/\b(catat|transaksi|pengeluaran|pemasukan|income|expense|beli|bayar)\b/u',
            $norm
        ) || (bool) preg_match(
            '/\b(budget|anggaran|set\s+budget)\b/u',
            $norm
        ) || (bool) preg_match(
            '/\b(recurring|langganan|berulang|tiap\s+bulan)\b/u',
            $norm
        ) || (bool) preg_match(
            '/\b(saldo|balance)\b/u',
            $norm
        ) || (bool) preg_match(
            '/\b(forecast|proyeksi|ramalan)\b/u',
            $norm
        ) || (bool) preg_match(
            '/\b(summary|ringkasan)\b/u',
            $norm
        );
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    public function isValidSlotAnswer(string $text, array $normalized, AiActionDraft $draft): bool
    {
        if ($this->looksLikeNewIntent($text, $normalized)) {
            return false;
        }

        $expected = $draft->payload['expected_next_field'] ?? ($draft->payload['missing_fields'][0] ?? null);
        $norm = $normalized['normalized_text'] ?? mb_strtolower(trim($text));

        return match ($expected) {
            'category_name' => ! preg_match('/^\//u', $text)
                && ! preg_match('/\b(saldo|transfer|wallet|dompet|budget|anggaran|recurring)\b/u', $norm),
            'wallet_type' => (bool) preg_match('/\b(cash|tunai|bank|rekening|e-wallet|ewallet|e wallet)\b/u', $norm),
            'wallet_name' => trim($text) !== '' && ! preg_match('/^\//u', $text),
            'amount', 'initial_balance' => $this->normalizer->primaryAmount($normalized) !== null,
            'from_wallet', 'from_wallet_name', 'to_wallet', 'to_wallet_name' => trim($text) !== '',
            default => ! $this->looksLikeNewIntent($text, $normalized),
        };
    }

    /**
     * @param  array<string, mixed>  $telegramContext
     */
    private function findCollectingDraft(int $userId, array $telegramContext): ?AiActionDraft
    {
        return $this->draftQuery($userId, $telegramContext)
            ->where('status', AiActionDraft::STATUS_COLLECTING)
            ->where('expires_at', '>', now())
            ->latest()
            ->first();
    }

    /**
     * @param  array<string, mixed>  $telegramContext
     */
    private function draftQuery(int $userId, array $telegramContext): \Illuminate\Database\Eloquent\Builder
    {
        $query = AiActionDraft::query()->where('user_id', $userId)->where('channel', 'telegram');

        if (! empty($telegramContext['telegram_chat_id'])) {
            $query->where('telegram_chat_id', $telegramContext['telegram_chat_id']);
        }

        return $query;
    }

    private function cancelDraft(AiActionDraft $draft): void
    {
        $draft->update(['status' => AiActionDraft::STATUS_CANCELLED]);
    }

    /**
     * @param  array<string, mixed>  $telegramContext
     */
    private function cancelActiveDrafts(int $userId, array $telegramContext, ?string $status = null): void
    {
        $query = $this->draftQuery($userId, $telegramContext)
            ->where('expires_at', '>', now());

        if ($status !== null) {
            $query->where('status', $status);
        } else {
            $query->whereIn('status', [AiActionDraft::STATUS_COLLECTING, AiActionDraft::STATUS_PENDING]);
        }

        foreach ($query->get() as $draft) {
            $this->cancelDraft($draft);
        }
    }

    /**
     * @param  array<string, mixed>  $entities
     */
    private function clarificationForExpectedField(?string $field, string $actionType, array $entities): string
    {
        return match ($field) {
            'category_name' => 'Kategori apa yang mau dibuat budget-nya?',
            'wallet_type' => 'Tipe wallet apa? Pilih: Bank, E-Wallet, atau Cash.',
            'amount' => 'Berapa jumlahnya?',
            'initial_balance' => 'Saldo awalnya mau diisi berapa?',
            default => 'Mohon lengkapi informasi yang diminta.',
        };
    }

    /**
     * @param  array<string, mixed>|null  $structured
     * @return array<string, mixed>
     */
    private function response(string $type, string $message, ?array $structured = null): array
    {
        return [
            'ok' => true,
            'type' => $type,
            'message' => $message,
            'structured' => array_merge([
                'intent' => $structured['intent'] ?? null,
                'action_type' => $structured['action_type'] ?? null,
                'draft_id' => $structured['draft_id'] ?? null,
            ], $structured ?? []),
            'draft_id' => $structured['draft_id'] ?? null,
        ];
    }
}
