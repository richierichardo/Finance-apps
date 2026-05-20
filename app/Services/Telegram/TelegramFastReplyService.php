<?php

namespace App\Services\Telegram;

use App\Services\AI\FinanceAICommandParserService;
use App\Services\AI\FinanceAIContextService;
use App\Services\AI\FinanceAIOrchestratorService;
use App\Services\AI\FinanceNLPNormalizerService;
use App\Services\ForecastInsightService;
use App\Services\InsightDataService;

class TelegramFastReplyService
{
    public function __construct(
        protected FinanceAIContextService $contextService,
        protected InsightDataService $insightDataService,
        protected ForecastInsightService $forecastInsightService,
        protected FinanceAIOrchestratorService $orchestrator,
        protected FinanceAICommandParserService $commandParser,
        protected FinanceNLPNormalizerService $normalizer,
    ) {}

    /**
     * Return a formatted reply without calling Qwen, or null to use full orchestrator.
     */
    public function tryFastReply(int $userId, string $text, string $telegramChatId): ?string
    {
        $periodKey = $this->contextService->currentPeriodKey();
        $context = $this->contextService->buildDashboardContext($userId, $periodKey);
        $commandParse = $this->commandParser->parse($userId, $text, $context);

        if (($commandParse['intent'] ?? '') === 'ask_specific_wallet_balance') {
            $walletName = $commandParse['entities']['wallet_name'] ?? null;
            if ($walletName) {
                foreach ($context['wallets'] ?? [] as $w) {
                    if ($w['name'] === $walletName) {
                        $fmt = 'Rp '.number_format((float) $w['balance'], 0, ',', '.');

                        return "Saldo {$w['name']} saat ini {$fmt}.";
                    }
                }
            }
        }

        if (($commandParse['intent'] ?? '') === 'ask_wallet_balance' && ($commandParse['confidence'] ?? 0) >= 0.85) {
            return $this->formatWallets($userId);
        }

        $normalizedText = $this->normalizer->normalize($text)['normalized_text'];

        if ($normalizedText === '/summary' || $this->isSummaryQuery($normalizedText)) {
            return $this->formatSummary($userId);
        }

        if ($normalizedText === '/forecast' || $this->isForecastQuery($normalizedText)) {
            return $this->formatForecast($userId);
        }

        if ($normalizedText === '/cancel' || preg_match('/^(cancel|batal|tidak|no)$/u', $normalizedText)) {
            $result = $this->orchestrator->handleUserMessage($userId, 'cancel', 'telegram', [
                'telegram_chat_id' => $telegramChatId,
            ]);

            return $result['message'] ?? null;
        }

        if (($commandParse['requires_action'] ?? false)
            && ($commandParse['confidence'] ?? 0) >= 0.85
            && empty($commandParse['missing_fields'])) {
            $result = $this->orchestrator->handleUserMessage($userId, $text, 'telegram', [
                'telegram_chat_id' => $telegramChatId,
                'prefer_rules_first' => true,
            ]);

            if (in_array($result['type'] ?? '', ['confirmation_required', 'clarification', 'answer'], true)) {
                return $result['message'] ?? null;
            }
        }

        return null;
    }

    private function isSummaryQuery(string $text): bool
    {
        return $text === '/summary'
            || preg_match('/\b(ringkasan|summary)\b/u', $text);
    }

    private function isForecastQuery(string $text): bool
    {
        return preg_match('/\b(forecast|proyeksi|ramalan)\b/u', $text);
    }

    public function formatWallets(int $userId): string
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

    public function formatSummary(int $userId): string
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

    public function formatForecast(int $userId): string
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
}
