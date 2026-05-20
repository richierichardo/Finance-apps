<?php

namespace App\Services\AI;

use App\Models\AiActionDraft;
use App\Models\AiConversation;
use App\Models\AiMessage;
use App\Models\AiTrainingExample;
use App\Models\Wallet;
use App\Services\AI\Prompts\FinanceAISystemPrompt;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class FinanceAIOrchestratorService
{
    private const READ_ONLY_INTENTS = [
        'ask_summary',
        'ask_wallet_balance',
        'ask_specific_wallet_balance',
        'ask_spending_analysis',
        'ask_budget_status',
        'ask_forecast',
        'help',
    ];

    public function __construct(
        protected QwenAIClientService $qwenClient,
        protected FinanceAIContextService $contextService,
        protected FinanceAIGuardrailService $guardrailService,
        protected FinanceAIIntentService $intentService,
        protected FinanceAIActionExecutorService $actionExecutor,
        protected FinanceNLPNormalizerService $normalizer,
        protected AITokenUsageService $tokenUsageService,
        protected FinanceAICommandParserService $commandParser,
    ) {}

    /**
     * @param  array<string, mixed>  $channelContext
     * @return array<string, mixed>
     */
    public function handleUserMessage(int $userId, string $message, string $channel = 'dashboard', array $channelContext = []): array
    {
        $message = trim($message);
        if ($message === '') {
            return $this->error('Pesan tidak boleh kosong.');
        }

        $conversation = $this->findOrCreateConversation($userId, $channel, $channelContext);
        $periodKey = $this->contextService->currentPeriodKey();
        $normalized = $this->normalizer->normalize($message);

        $this->saveMessage($conversation, $userId, 'user', $channel, $message);

        $preGate = $this->guardrailService->preGate($message);
        if (! $preGate['allowed']) {
            return $this->guardrailBlockedResponse($conversation, $userId, $channel, $message, $periodKey, $preGate);
        }

        if (! empty($preGate['message']) && in_array('out_of_scope_mixed', $preGate['flags'], true)) {
            return $this->guardrailBlockedResponse($conversation, $userId, $channel, $message, $periodKey, $preGate, true);
        }

        $risk = $this->guardrailService->classifyRisk($message);
        if (! $risk['allowed']) {
            return $this->guardrailBlockedResponse($conversation, $userId, $channel, $message, $periodKey, [
                'allowed' => false,
                'message' => $risk['message'],
                'flags' => $risk['flags'],
            ]);
        }

        $context = $this->contextService->buildDashboardContext($userId, $periodKey);

        Log::info('ai.normalized', [
            'original' => $message,
            'normalized_text' => $normalized['normalized_text'] ?? null,
            'amount_candidates' => $this->normalizer->amountValues($normalized),
        ]);

        $commandParse = $this->commandParser->parse($userId, $message, $context);

        Log::info('ai.command_parse', [
            'matched' => $commandParse['matched'] ?? false,
            'intent' => $commandParse['intent'] ?? null,
            'confidence' => $commandParse['confidence'] ?? null,
            'entities' => $commandParse['entities'] ?? [],
            'missing_fields' => $commandParse['missing_fields'] ?? [],
            'model_called' => false,
        ]);

        $useCommandOnly = ($commandParse['matched'] ?? false)
            && ($commandParse['confidence'] ?? 0) >= 0.85
            && empty($commandParse['missing_fields']);

        $useCommandClarification = ($commandParse['matched'] ?? false)
            && ($commandParse['confidence'] ?? 0) >= 0.7
            && ! empty($commandParse['missing_fields']);

        if ($useCommandOnly || $useCommandClarification) {
            $intent = $this->commandResultToIntent($commandParse);
        } else {
            $parseOptions = [
                'qwen_options' => array_merge(
                    $this->defaultQwenOptions($channel, forIntent: true),
                    $channelContext['qwen_options'] ?? []
                ),
            ];
            $intent = $this->intentService->parse($userId, $message, $context, $parseOptions);
        }

        Log::info('ai.rule_parse_result', [
            'intent' => $intent['intent'] ?? null,
            'confidence' => $intent['confidence'] ?? null,
            'entities' => $intent['entities'] ?? [],
            'missing_fields' => $intent['missing_fields'] ?? [],
            'model_called' => $intent['model_called'] ?? false,
            'source' => $intent['source'] ?? null,
        ]);

        if (($intent['intent'] ?? '') === 'out_of_scope') {
            return $this->guardrailBlockedResponse($conversation, $userId, $channel, $message, $periodKey, [
                'allowed' => false,
                'message' => $this->guardrailService->outOfScopeMessage(),
                'flags' => ['out_of_scope'],
            ]);
        }

        if (($intent['intent'] ?? '') === 'confirm') {
            return $this->handleConfirm($userId, $channel, $channelContext, $conversation, $message, $periodKey);
        }

        if (($intent['intent'] ?? '') === 'cancel') {
            return $this->handleCancel($userId, $channel, $conversation, $message, $periodKey);
        }

        $intentValidation = $this->guardrailService->validateIntent($intent);
        if (! $intentValidation['valid']) {
            return [
                'ok' => false,
                'type' => 'blocked',
                'message' => $intentValidation['message'],
                'structured' => ['intent' => $intent['intent'] ?? 'unknown'],
                'draft_id' => null,
            ];
        }

        $flags = array_merge($risk['flags'], $intent['safety_flags'] ?? []);

        if ($this->shouldBlockInvestment($message, $intent, $flags)) {
            $edu = $this->investmentEducationalResponse();

            return $this->answerAndLog($conversation, $userId, $channel, $message, $edu, $intent, $flags, $periodKey, 'blocked');
        }

        if (! empty($intent['missing_fields']) && ! empty($intent['clarifying_question'])) {
            $this->logTraining($userId, $channel, $message, $periodKey, $intent, null, null, null);

            return [
                'ok' => true,
                'type' => 'clarification',
                'message' => $intent['clarifying_question'],
                'structured' => $intent,
                'draft_id' => null,
            ];
        }

        if (($intent['requires_action'] ?? false) && ! empty($intent['action_type'])) {
            return $this->handleWriteIntent($userId, $channel, $channelContext, $conversation, $message, $intent, $periodKey);
        }

        if (in_array($intent['intent'] ?? '', self::READ_ONLY_INTENTS, true)) {
            return $this->handleReadOnly($userId, $channel, $conversation, $message, $intent, $flags, $periodKey, $channelContext);
        }

        return $this->handleReadOnly($userId, $channel, $conversation, $message, $intent, $flags, $periodKey, $channelContext);
    }

    /**
     * @return array<string, mixed>
     */
    public function confirmDraft(int $userId, AiActionDraft $draft): array
    {
        if ($draft->user_id !== $userId) {
            return $this->error('Draft tidak ditemukan.');
        }

        if ($draft->isExpired()) {
            $draft->update(['status' => AiActionDraft::STATUS_EXPIRED]);

            return $this->error('Konfirmasi sudah kedaluwarsa. Silakan ulangi permintaan.');
        }

        if (! $draft->isPending()) {
            return $this->error('Draft tidak dalam status pending.');
        }

        $validation = $this->guardrailService->validateActionPayload(
            $userId,
            $draft->action_type,
            $draft->payload ?? []
        );

        if (! $validation['valid']) {
            return $this->error($validation['message'] ?? 'Payload tidak valid.');
        }

        $draft->update([
            'status' => AiActionDraft::STATUS_CONFIRMED,
            'confirmed_at' => now(),
        ]);

        $result = $this->actionExecutor->execute($userId, $draft->action_type, $draft->payload ?? []);

        $draft->update([
            'status' => $result['ok'] ? AiActionDraft::STATUS_EXECUTED : AiActionDraft::STATUS_FAILED,
            'executed_at' => now(),
        ]);

        AiTrainingExample::where('user_id', $userId)
            ->latest()
            ->first()
            ?->update([
                'was_confirmed' => true,
                'was_successful' => $result['ok'],
                'final_action_payload' => $draft->payload,
            ]);

        if ($draft->ai_conversation_id) {
            $this->saveMessage(
                $draft->conversation,
                $userId,
                'assistant',
                $draft->channel,
                $result['message'],
                null,
                ['action_type' => $draft->action_type, 'executed' => $result['ok']]
            );
        }

        return [
            'ok' => $result['ok'],
            'type' => $result['ok'] ? 'action_executed' : 'error',
            'message' => $result['message'],
            'structured' => ['action_type' => $draft->action_type],
            'draft_id' => $draft->id,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function cancelDraft(int $userId, AiActionDraft $draft): array
    {
        if ($draft->user_id !== $userId || ! $draft->isPending()) {
            return $this->error('Tidak ada draft yang bisa dibatalkan.');
        }

        $draft->update(['status' => AiActionDraft::STATUS_CANCELLED]);

        AiTrainingExample::where('user_id', $userId)->latest()->first()?->update(['was_confirmed' => false]);

        return [
            'ok' => true,
            'type' => 'answer',
            'message' => 'Aksi dibatalkan.',
            'structured' => null,
            'draft_id' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $channelContext
     * @param  array<string, mixed>  $intent
     * @return array<string, mixed>
     */
    private function handleWriteIntent(
        int $userId,
        string $channel,
        array $channelContext,
        AiConversation $conversation,
        string $message,
        array $intent,
        string $periodKey,
    ): array {
        $context = $this->contextService->buildDashboardContext($userId, $periodKey);
        $payload = $this->buildPayloadFromIntent($userId, $intent, $context, $channel);

        if (isset($payload['error'])) {
            return [
                'ok' => true,
                'type' => 'clarification',
                'message' => $payload['error'],
                'structured' => $intent,
                'draft_id' => null,
            ];
        }

        $actionType = $intent['action_type'];
        $validation = $this->guardrailService->validateActionPayload($userId, $actionType, $payload);

        if (! $validation['valid']) {
            return [
                'ok' => true,
                'type' => 'clarification',
                'message' => $validation['message'],
                'structured' => $intent,
                'draft_id' => null,
            ];
        }

        $preview = $this->buildPreviewText($actionType, $payload, $context, $validation['warnings']);

        $draft = AiActionDraft::create([
            'user_id' => $userId,
            'ai_conversation_id' => $conversation->id,
            'channel' => $channel,
            'telegram_chat_id' => $channelContext['telegram_chat_id'] ?? null,
            'action_type' => $actionType,
            'payload' => $payload,
            'preview_text' => $preview,
            'status' => AiActionDraft::STATUS_PENDING,
            'expires_at' => now()->addMinutes((int) config('ai.draft_expiry_minutes', 30)),
        ]);

        $confirmMessage = $preview."\n\nBalas *yes* untuk simpan atau *cancel* untuk batal.";

        $this->saveMessage($conversation, $userId, 'assistant', $channel, $confirmMessage, $intent, [
            'type' => 'confirmation_required',
            'draft_id' => $draft->id,
        ]);

        $this->logTraining($userId, $channel, $message, $periodKey, $intent, $payload, null, null);

        return [
            'ok' => true,
            'type' => 'confirmation_required',
            'message' => $confirmMessage,
            'structured' => [
                'intent' => $intent['intent'],
                'action_type' => $actionType,
                'payload' => $payload,
            ],
            'draft_id' => $draft->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $intent
     * @param  list<string>  $flags
     * @return array<string, mixed>
     */
    /**
     * @param  array<string, mixed>  $channelContext
     */
    private function handleReadOnly(
        int $userId,
        string $channel,
        AiConversation $conversation,
        string $message,
        array $intent,
        array $flags,
        string $periodKey,
        array $channelContext = [],
    ): array {
        if (($intent['intent'] ?? '') === 'help') {
            $help = $this->helpMessage();

            return $this->answerAndLog($conversation, $userId, $channel, $message, $help, $intent, $flags, $periodKey);
        }

        if (($intent['intent'] ?? '') === 'ask_wallet_balance') {
            $text = $this->walletBalanceAnswer($userId);

            return $this->answerAndLog($conversation, $userId, $channel, $message, $text, $intent, $flags, $periodKey);
        }

        if (($intent['intent'] ?? '') === 'ask_specific_wallet_balance') {
            $text = $this->specificWalletBalanceAnswer($userId, $intent['entities']['wallet_name'] ?? null);

            return $this->answerAndLog($conversation, $userId, $channel, $message, $text, $intent, $flags, $periodKey);
        }

        if (($intent['intent'] ?? '') === 'ask_forecast') {
            $forecast = $this->contextService->buildForecastContext($userId, $periodKey)['forecast'] ?? [];
            $text = $this->forecastAnswer($forecast);

            return $this->answerAndLog($conversation, $userId, $channel, $message, $text, $intent, $flags, $periodKey);
        }

        if (($intent['intent'] ?? '') === 'ask_budget_status') {
            $budgets = $this->contextService->buildBudgetContext($userId, $periodKey)['budgets'] ?? [];
            $text = $this->budgetAnswer($budgets);

            return $this->answerAndLog($conversation, $userId, $channel, $message, $text, $intent, $flags, $periodKey);
        }

        $context = $this->contextService->buildDashboardContext($userId, $periodKey);
        $contextJson = json_encode($context, JSON_UNESCAPED_UNICODE);
        $messages = [
            ['role' => 'system', 'content' => FinanceAISystemPrompt::responseGeneration($contextJson)],
            ['role' => 'user', 'content' => $message],
        ];

        $qwenOptions = $this->defaultQwenOptions($channel, forIntent: false);

        $response = $this->qwenClient->chat($messages, $qwenOptions);

        if (! $response['ok'] || ! $response['content']) {
            $fallback = $this->summaryFallback($context);

            return $this->answerAndLog($conversation, $userId, $channel, $message, $fallback, $intent, $flags, $periodKey);
        }

        $content = $this->guardrailService->sanitizeAssistantResponse($response['content'], $flags);

        $assistantMessage = $this->saveMessage($conversation, $userId, 'assistant', $channel, $content, $intent, [
            'intent' => $intent['intent'],
            'model_called' => true,
        ], $response['latency_ms']);

        $this->tokenUsageService->attachToMessage($assistantMessage, $response);
        $this->logTraining($userId, $channel, $message, $periodKey, $intent, null, null, null, $response);

        return [
            'ok' => true,
            'type' => 'answer',
            'message' => $content,
            'structured' => [
                'intent' => $intent['intent'],
                'confidence' => $intent['confidence'] ?? null,
                'safety_flags' => $flags,
                'model_called' => true,
                'token_usage' => $response['usage'],
            ],
            'draft_id' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $channelContext
     * @return array<string, mixed>
     */
    private function handleConfirm(
        int $userId,
        string $channel,
        array $channelContext,
        AiConversation $conversation,
        string $message,
        string $periodKey,
    ): array {
        $draft = $this->findPendingDraft($userId, $channel, $channelContext);

        if (! $draft) {
            return [
                'ok' => true,
                'type' => 'clarification',
                'message' => 'Tidak ada aksi yang menunggu konfirmasi.',
                'structured' => null,
                'draft_id' => null,
            ];
        }

        return $this->confirmDraft($userId, $draft);
    }

    /**
     * @return array<string, mixed>
     */
    private function handleCancel(
        int $userId,
        string $channel,
        AiConversation $conversation,
        string $message,
        string $periodKey,
    ): array {
        $draft = AiActionDraft::query()
            ->where('user_id', $userId)
            ->where('channel', $channel)
            ->where('status', AiActionDraft::STATUS_PENDING)
            ->latest()
            ->first();

        if (! $draft) {
            return [
                'ok' => true,
                'type' => 'answer',
                'message' => 'Tidak ada aksi yang dibatalkan.',
                'structured' => null,
                'draft_id' => null,
            ];
        }

        return $this->cancelDraft($userId, $draft);
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function buildPayloadFromIntent(int $userId, array $intent, array $context, string $channel): array
    {
        $entities = $intent['entities'] ?? [];
        $actionType = $intent['action_type'];
        $source = $channel === 'telegram' ? 'telegram_ai' : 'web';

        if ($actionType === 'create_transaction') {
            $walletId = $this->contextService->resolveWalletId($userId, $entities['wallet_name'] ?? null, $context);
            if (! $walletId) {
                return ['error' => 'Wallet tidak ditemukan. Pastikan nama wallet sesuai daftar kamu.'];
            }

            $type = $entities['transaction_type'] ?? 'expense';
            $category = $this->contextService->resolveCategoryTransactionEnum(
                $entities['category_name'] ?? null,
                $type
            );

            return [
                'type' => $type,
                'wallet_id' => $walletId,
                'category_transaction' => $category,
                'amount' => (float) ($entities['amount'] ?? 0),
                'description' => $entities['description'] ?? null,
                'transaction_date' => $this->contextService->parseOccurredAt(
                    $entities['date'] ?? null,
                    $entities['time'] ?? null
                ),
                'source' => $source,
            ];
        }

        if ($actionType === 'create_transfer') {
            $fromId = $this->contextService->resolveWalletId($userId, $entities['from_wallet_name'] ?? null, $context);
            $toId = $this->contextService->resolveWalletId($userId, $entities['to_wallet_name'] ?? null, $context);

            if (! $fromId || ! $toId) {
                return ['error' => 'Wallet asal atau tujuan tidak ditemukan.'];
            }

            $fromWallet = Wallet::belongsToUser($userId)->find($fromId);
            $amount = (float) ($entities['amount'] ?? 0);
            $balanceWarning = $fromWallet && (float) $fromWallet->balance < $amount;

            return [
                'from_wallet_id' => $fromId,
                'to_wallet_id' => $toId,
                'amount' => $amount,
                'description' => $entities['description'] ?? null,
                'transaction_date' => $this->contextService->parseOccurredAt(
                    $entities['date'] ?? null,
                    $entities['time'] ?? null
                ),
                'source' => $source,
                'balance_warning' => $balanceWarning,
            ];
        }

        if ($actionType === 'create_budget') {
            $categoryId = $this->contextService->resolveCategoryId($entities['category_name'] ?? null);
            if (! $categoryId) {
                return ['error' => 'Kategori tidak ditemukan.'];
            }

            return [
                'category_id' => $categoryId,
                'amount' => (float) ($entities['amount'] ?? 0),
                'period' => $entities['period'] ?? 'monthly',
                'start_date' => now()->startOfMonth()->format('Y-m-d'),
            ];
        }

        if ($actionType === 'create_recurring') {
            $walletId = $this->contextService->resolveWalletId($userId, $entities['wallet_name'] ?? null, $context);
            $categoryId = $this->contextService->resolveCategoryId($entities['category_name'] ?? null);

            if (! $walletId) {
                return ['error' => 'Wallet tidak ditemukan.'];
            }

            return [
                'wallet_id' => $walletId,
                'category_id' => $categoryId,
                'type' => $entities['transaction_type'] ?? 'expense',
                'amount' => (float) ($entities['amount'] ?? 0),
                'description' => $entities['description'] ?? null,
                'frequency' => $entities['frequency'] ?? 'monthly',
                'interval' => (int) ($entities['interval'] ?? 1),
                'start_date' => $entities['date'] ?? now()->format('Y-m-d'),
                'end_date' => null,
            ];
        }

        if ($actionType === 'create_wallet') {
            $name = trim((string) ($entities['wallet_name'] ?? ''));
            $type = $entities['wallet_type'] ?? null;

            if ($name === '' || ! $type) {
                return ['error' => 'Nama dan tipe wallet wajib diisi.'];
            }

            $exists = Wallet::belongsToUser($userId)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
                ->exists();

            if ($exists) {
                return ['error' => 'Wallet dengan nama itu sudah ada.'];
            }

            return [
                'name' => $name,
                'type' => $type,
                'initial_balance' => (float) ($entities['initial_balance'] ?? 0),
                'source' => $source,
            ];
        }

        return ['error' => 'Aksi tidak dikenali.'];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $context
     * @param  list<string>  $warnings
     */
    private function buildPreviewText(string $actionType, array $payload, array $context, array $warnings = []): string
    {
        $fmt = fn (float $n) => 'Rp '.number_format($n, 0, ',', '.');

        if ($actionType === 'create_transaction') {
            $wallet = collect($context['wallets'] ?? [])->firstWhere('id', $payload['wallet_id']);
            $type = $payload['type'] === 'income' ? 'Income' : 'Expense';

            return "Konfirmasi ya:\n{$type} {$fmt((float) $payload['amount'])}\nWallet: ".($wallet['name'] ?? '-')
                ."\nDescription: ".($payload['description'] ?? '-');
        }

        if ($actionType === 'create_transfer') {
            $from = collect($context['wallets'] ?? [])->firstWhere('id', $payload['from_wallet_id']);
            $to = collect($context['wallets'] ?? [])->firstWhere('id', $payload['to_wallet_id']);
            $warn = in_array('balance_insufficient', $warnings, true) || ! empty($payload['balance_warning'])
                ? "\n⚠ Saldo wallet asal mungkin tidak cukup."
                : '';

            return "Konfirmasi transfer:\n{$fmt((float) $payload['amount'])}\nDari: ".($from['name'] ?? '-')
                .' (saldo: '.$fmt((float) ($from['balance'] ?? 0)).")\nKe: ".($to['name'] ?? '-').$warn;
        }

        if ($actionType === 'create_budget') {
            return 'Konfirmasi budget: '.$fmt((float) $payload['amount']).' per '.($payload['period'] ?? 'monthly');
        }

        if ($actionType === 'create_wallet') {
            $typeLabel = match ($payload['type'] ?? '') {
                'cash' => 'Cash',
                'bank' => 'Bank',
                'ewallet' => 'E-Wallet',
                default => (string) ($payload['type'] ?? '-'),
            };

            return "Konfirmasi buat wallet baru:\nNama: {$payload['name']}\nType: {$typeLabel}\nSaldo awal: ".$fmt((float) ($payload['initial_balance'] ?? 0));
        }

        return 'Konfirmasi aksi: '.$actionType;
    }

    /**
     * @param  array<string, mixed>  $channelContext
     */
    private function findOrCreateConversation(int $userId, string $channel, array $channelContext): AiConversation
    {
        $query = AiConversation::query()
            ->where('user_id', $userId)
            ->where('channel', $channel)
            ->where('status', 'active');

        if ($channel === 'telegram' && ! empty($channelContext['telegram_chat_id'])) {
            $query->where('telegram_chat_id', $channelContext['telegram_chat_id']);
        }

        return $query->first() ?? AiConversation::create([
            'user_id' => $userId,
            'channel' => $channel,
            'telegram_chat_id' => $channelContext['telegram_chat_id'] ?? null,
            'status' => 'active',
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $structuredInput
     * @param  array<string, mixed>|null  $structuredOutput
     */
    private function saveMessage(
        AiConversation $conversation,
        int $userId,
        string $role,
        string $channel,
        string $content,
        ?array $structuredInput = null,
        ?array $structuredOutput = null,
        ?int $latencyMs = null,
    ): AiMessage {
        return AiMessage::create([
            'ai_conversation_id' => $conversation->id,
            'user_id' => $userId,
            'role' => $role,
            'channel' => $channel,
            'content' => $content,
            'structured_input' => $structuredInput,
            'structured_output' => $structuredOutput,
            'intent' => $structuredInput['intent'] ?? ($structuredOutput['intent'] ?? null),
            'action_type' => $structuredInput['action_type'] ?? ($structuredOutput['action_type'] ?? null),
            'model' => config('ai.model'),
            'latency_ms' => $latencyMs,
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $intent
     * @param  array<string, mixed>|null  $payload
     */
    private function logTraining(
        int $userId,
        string $channel,
        string $rawInput,
        string $periodKey,
        ?array $intent,
        ?array $payload,
        ?bool $wasConfirmed,
        ?bool $wasSuccessful,
        ?array $aiResult = null,
        bool $guardrailBlocked = false,
    ): void {
        $normalized = $this->normalizer->normalize($rawInput);

        $example = AiTrainingExample::create([
            'user_id' => $userId,
            'source_channel' => $channel,
            'raw_user_input' => $rawInput,
            'normalized_input' => [
                'language' => 'id',
                'clean_text' => $normalized['normalized_text'],
                'original' => $normalized['original'],
                'channel' => $channel,
                'period_key' => $periodKey,
                'amount_candidates' => $this->normalizer->amountValues($normalized),
            ],
            'expected_intent' => $intent['intent'] ?? null,
            'expected_action_type' => $intent['action_type'] ?? null,
            'model_output' => $intent,
            'final_action_payload' => $payload,
            'was_confirmed' => $wasConfirmed,
            'was_successful' => $wasSuccessful,
            'model_called' => $aiResult !== null,
            'guardrail_blocked' => $guardrailBlocked,
        ]);

        if ($aiResult) {
            $this->tokenUsageService->attachToTrainingExample($example, $aiResult);
        } elseif ($guardrailBlocked) {
            $this->tokenUsageService->attachToTrainingExample($example, [
                'usage' => $this->tokenUsageService->zeroUsage(),
                'model' => null,
                'provider' => config('ai.provider'),
                'latency_ms' => 0,
                'model_called' => false,
                'guardrail_blocked' => true,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $channelContext
     */
    private function findPendingDraft(int $userId, string $channel, array $channelContext): ?AiActionDraft
    {
        $query = AiActionDraft::query()
            ->where('user_id', $userId)
            ->where('channel', $channel)
            ->where('status', AiActionDraft::STATUS_PENDING)
            ->where('expires_at', '>', now());

        if ($channel === 'telegram' && ! empty($channelContext['telegram_chat_id'])) {
            $query->where('telegram_chat_id', $channelContext['telegram_chat_id']);
        }

        return $query->latest()->first();
    }

    /**
     * @param  array<string, mixed>  $intent
     * @param  list<string>  $flags
     * @return array<string, mixed>
     */
    private function answerAndLog(
        AiConversation $conversation,
        int $userId,
        string $channel,
        string $message,
        string $content,
        array $intent,
        array $flags,
        string $periodKey,
        string $type = 'answer',
    ): array {
        $content = $this->guardrailService->sanitizeAssistantResponse($content, $flags);
        $this->saveMessage($conversation, $userId, 'assistant', $channel, $content, $intent);
        $this->logTraining($userId, $channel, $message, $periodKey, $intent, null, null, null);

        return [
            'ok' => $type !== 'blocked',
            'type' => $type,
            'message' => $content,
            'structured' => ['intent' => $intent['intent'], 'safety_flags' => $flags],
            'draft_id' => null,
        ];
    }

    private function walletBalanceAnswer(int $userId): string
    {
        $wallets = $this->contextService->buildWalletContext($userId)['wallets'] ?? [];
        $total = array_sum(array_column($wallets, 'balance'));
        $fmt = fn (float $n) => 'Rp '.number_format($n, 0, ',', '.');
        $lines = ["Total saldo wallet kamu saat ini {$fmt($total)}.", 'Rinciannya:'];
        foreach ($wallets as $w) {
            $lines[] = '- '.$w['name'].': '.$fmt((float) $w['balance']);
        }

        return implode("\n", $lines);
    }

    private function specificWalletBalanceAnswer(int $userId, ?string $walletName): string
    {
        if (! $walletName) {
            return 'Wallet tidak ditemukan. Sebutkan nama wallet yang ada di akun kamu.';
        }

        $wallets = $this->contextService->buildWalletContext($userId)['wallets'] ?? [];
        $matched = $this->contextService->matchWalletName($walletName, ['wallets' => $wallets]);

        foreach ($wallets as $w) {
            if ($w['name'] === $matched) {
                $fmt = 'Rp '.number_format((float) $w['balance'], 0, ',', '.');

                return "Saldo {$w['name']} saat ini {$fmt}.";
            }
        }

        return "Wallet {$walletName} tidak ditemukan.";
    }

    /**
     * @param  array<string, mixed>  $preGate
     * @return array<string, mixed>
     */
    private function guardrailBlockedResponse(
        AiConversation $conversation,
        int $userId,
        string $channel,
        string $message,
        string $periodKey,
        array $preGate,
        bool $mixed = false,
    ): array {
        $blockedMessage = $preGate['message'] ?? $this->guardrailService->outOfScopeMessage();
        $flags = $preGate['flags'] ?? ['out_of_scope'];
        $intent = ['intent' => 'out_of_scope', 'safety_flags' => $flags];

        $assistantMessage = $this->saveMessage($conversation, $userId, 'assistant', $channel, $blockedMessage, $intent, [
            'intent' => 'out_of_scope',
            'guardrail_blocked' => true,
            'model_called' => false,
            'token_usage' => $this->tokenUsageService->zeroUsage(),
        ]);

        $this->tokenUsageService->attachToMessage($assistantMessage, [
            'usage' => $this->tokenUsageService->zeroUsage(),
            'model' => null,
            'provider' => config('ai.provider'),
            'latency_ms' => 0,
        ]);

        $this->logTraining($userId, $channel, $message, $periodKey, $intent, null, false, false, null, true);

        return [
            'ok' => $mixed,
            'type' => $mixed ? 'answer' : 'blocked',
            'message' => $blockedMessage,
            'structured' => [
                'intent' => 'out_of_scope',
                'guardrail_blocked' => true,
                'model_called' => false,
                'safety_flags' => $flags,
                'token_usage' => $this->tokenUsageService->zeroUsage(),
            ],
            'draft_id' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultQwenOptions(string $channel, bool $forIntent = false): array
    {
        if ($forIntent) {
            return [
                'max_tokens' => (int) config('ai.intent_max_tokens', 400),
                'timeout' => (int) config('ai.intent_timeout_seconds', 8),
                'temperature' => 0.1,
            ];
        }

        $maxTokens = $channel === 'telegram'
            ? (int) config('ai.telegram_max_tokens', 500)
            : (int) config('ai.dashboard_max_tokens', 800);

        return [
            'max_tokens' => $maxTokens,
            'timeout' => (int) config('ai.response_timeout_seconds', 15),
            'temperature' => 0.2,
        ];
    }

    /**
     * @param  array<string, mixed>  $forecast
     */
    private function forecastAnswer(array $forecast): string
    {
        if (empty($forecast['forecast_available'])) {
            return $forecast['message'] ?? 'Forecast belum tersedia untuk periode ini.';
        }

        $fmt = fn (float $n) => 'Rp '.number_format($n, 0, ',', '.');

        return sprintf(
            "Forecast (beta) bulan ini:\nIncome: %s\nExpense: %s\nNet: %s\n\n%s",
            $fmt((float) ($forecast['projected_income'] ?? 0)),
            $fmt((float) ($forecast['projected_expense'] ?? 0)),
            $fmt((float) ($forecast['projected_net'] ?? 0)),
            $forecast['message'] ?? 'Ini perkiraan berdasarkan rata-rata historis.'
        );
    }

    /**
     * @param  list<array<string, mixed>>  $budgets
     */
    private function budgetAnswer(array $budgets): string
    {
        if (empty($budgets)) {
            return 'Belum ada budget yang diatur untuk periode ini.';
        }

        $lines = ['Status budget:'];
        foreach ($budgets as $b) {
            $lines[] = sprintf(
                '- %s: terpakai %s%% (%s / %s)',
                $b['category'] ?? '-',
                round((float) ($b['percentage'] ?? 0), 1),
                number_format((float) ($b['spent'] ?? 0), 0, ',', '.'),
                number_format((float) ($b['budget_amount'] ?? 0), 0, ',', '.')
            );
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function summaryFallback(array $context): string
    {
        $s = $context['summary'] ?? [];
        $fmt = fn (float $n) => 'Rp '.number_format($n, 0, ',', '.');

        return sprintf(
            "Ringkasan bulan %s:\nIncome: %s\nExpense: %s\nNet cashflow: %s\nTotal saldo wallet: %s",
            $context['period_key'] ?? '-',
            $fmt((float) ($s['total_income'] ?? 0)),
            $fmt((float) ($s['total_expense'] ?? 0)),
            $fmt((float) ($s['net_cashflow'] ?? 0)),
            $fmt((float) ($s['total_balance'] ?? 0))
        );
    }

    private function helpMessage(): string
    {
        return "Perintah yang bisa kamu gunakan:\n"
            ."- Tanya: saldo, saldo GOPAY, ringkasan bulan ini, forecast, status budget\n"
            ."- Catat: \"catat pengeluaran 25000 dari GOPAY untuk kopi\"\n"
            ."- Transfer: \"transfer 20 ribu dari GOPAY ke SHOPEEPAY\"\n"
            ."- Buat wallet: \"buat wallet cash nama uang dompet saldo 50 ribu\"\n"
            ."- Konfirmasi: yes / cancel";
    }

    private function investmentEducationalResponse(): string
    {
        return 'Aku tidak bisa memberi rekomendasi final untuk buy/sell saham atau crypto. '
            .'Aku bisa bantu menjelaskan risiko, cashflow kamu, dan kerangka analisis secara edukatif. '
            .'Keputusan investasi sepenuhnya tanggung jawab kamu.';
    }

    /**
     * @param  array<string, mixed>  $intent
     * @param  list<string>  $flags
     */
    private function shouldBlockInvestment(string $message, array $intent, array $flags): bool
    {
        if (in_array('investment_advice', $flags, true)) {
            return true;
        }

        $highConfidenceFinance = in_array($intent['intent'] ?? '', [
            'parse_transaction',
            'parse_transfer',
            'parse_wallet',
            'parse_budget',
            'parse_recurring',
        ], true) && ($intent['confidence'] ?? 0) >= 0.85;

        if ($highConfidenceFinance) {
            return false;
        }

        return $this->isInvestmentQuestion($message);
    }

    private function isInvestmentQuestion(string $message): bool
    {
        if (preg_match('/\b(rekomendasi|investasi|portfolio)\b/ui', $message)) {
            return true;
        }

        return (bool) preg_match(
            '/\b(beli|buy|jual|sell)\b.*\b(saham|stock|crypto|bitcoin|btc|eth|reksadana)\b/ui',
            $message
        ) || (bool) preg_match('/\b(saham|stock|crypto|bitcoin)\b/ui', $message);
    }

    /**
     * @param  array<string, mixed>  $commandParse
     * @return array<string, mixed>
     */
    private function commandResultToIntent(array $commandParse): array
    {
        return [
            'intent' => $commandParse['intent'] ?? 'unknown',
            'confidence' => $commandParse['confidence'] ?? 0.0,
            'language' => 'id',
            'requires_action' => $commandParse['requires_action'] ?? false,
            'action_type' => $commandParse['action_type'] ?? null,
            'entities' => $commandParse['entities'] ?? [],
            'missing_fields' => $commandParse['missing_fields'] ?? [],
            'clarifying_question' => $commandParse['clarifying_question'] ?? null,
            'safety_flags' => $commandParse['safety_flags'] ?? [],
            'model_called' => false,
            'source' => 'rules',
            'matched' => $commandParse['matched'] ?? true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function error(string $message): array
    {
        return [
            'ok' => false,
            'type' => 'error',
            'message' => $message,
            'structured' => null,
            'draft_id' => null,
        ];
    }
}
