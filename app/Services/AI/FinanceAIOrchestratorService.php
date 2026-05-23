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

    private const SLOT_FILL_ACTIONS = [
    'create_wallet',
    'create_transfer',
    'create_transaction',
    'create_budget',
    'create_recurring',
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
        protected FinanceAIClarificationService $clarificationService,
        protected FinanceEntityResolverService $entityResolver,
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

        if (! empty($channelContext['only_slot_fill'])) {
            $slotFillResult = $this->trySlotFillFromCollectingDraft(
                $userId,
                $channel,
                $channelContext,
                $conversation,
                $message,
                $normalized,
                $periodKey
            );

            return $slotFillResult ?? $this->error('Tidak ada draft yang sedang dilengkapi.');
        }

        if (empty($channelContext['skip_collecting_draft'])) {
            $slotFillResult = $this->trySlotFillFromCollectingDraft(
                $userId,
                $channel,
                $channelContext,
                $conversation,
                $message,
                $normalized,
                $periodKey
            );
            if ($slotFillResult !== null) {
                return $slotFillResult;
            }
        }

        $intent = $this->resolveIntent($userId, $message, $normalized, $context, $channel, $channelContext);

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
            return $this->handleCancel($userId, $channel, $conversation, $message, $periodKey, $channelContext);
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

        if (! empty($intent['missing_fields'])) {
            $clarification = $intent['clarifying_question']
                ?? $this->clarificationService->buildFieldSpecificClarification(
                    $intent['action_type'] ?? '',
                    $intent['missing_fields'],
                    $intent['entities'] ?? []
                );

            if (($intent['requires_action'] ?? false) && in_array($intent['action_type'] ?? '', self::SLOT_FILL_ACTIONS, true)) {
                return $this->handleCollectingDraft(
                    $userId,
                    $channel,
                    $channelContext,
                    $conversation,
                    $message,
                    $intent,
                    $periodKey,
                    $clarification
                );
            }

            if ($clarification) {
                $this->logTraining($userId, $channel, $message, $periodKey, $intent, null, null, null);

                return [
                    'ok' => true,
                    'type' => 'clarification',
                    'message' => $clarification,
                    'structured' => $intent,
                    'draft_id' => null,
                ];
            }
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
        array $channelContext = [],
    ): array {
        $query = AiActionDraft::query()
            ->where('user_id', $userId)
            ->where('channel', $channel)
            ->whereIn('status', [AiActionDraft::STATUS_PENDING, AiActionDraft::STATUS_COLLECTING])
            ->where('expires_at', '>', now());

        if ($channel === 'telegram' && ! empty($channelContext['telegram_chat_id'])) {
            $query->where('telegram_chat_id', $channelContext['telegram_chat_id']);
        }

        $draft = $query->latest()->first();

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
        $source = $channel === 'telegram' ? 'telegram_ai' : 'web_ai';

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
            $categoryName = $entities['category_name'] ?? null;
            $categoryId = $this->contextService->resolveCategoryId($categoryName);
            if (! $categoryId) {
                $label = $categoryName ? "'{$categoryName}'" : 'itu';

                return ['error' => "Kategori {$label} belum ada. Mau pakai kategori lain atau buat kategori baru di aplikasi web?"];
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
            $normalized = $this->clarificationService->normalizeWalletEntities($entities);
            $name = trim((string) ($normalized['wallet_name'] ?? ''));
            $type = $normalized['wallet_type'] ?? null;
            $missing = array_values(array_filter([
                $name === '' ? 'wallet_name' : null,
                ! $type ? 'wallet_type' : null,
            ]));

            if (! empty($missing)) {
                return [
                    'error' => $this->clarificationService->buildWalletClarifyingQuestion($missing, $normalized),
                ];
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
                'initial_balance' => (float) ($normalized['initial_balance'] ?? $entities['initial_balance'] ?? 0),
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

        if ($this->isBudgetTrackingContext($message)) {
            return false;
        }

        return $this->isInvestmentQuestion($message);
    }

    private function isInvestmentQuestion(string $message): bool
    {
        if ($this->isBudgetTrackingContext($message)) {
            return false;
        }

        if (preg_match('/\b(rekomendasi|recommend)\b.*\b(beli|buy|jual|sell|saham|stock|crypto)\b/ui', $message)) {
            return true;
        }

        return (bool) preg_match(
            '/\b(beli|buy|jual|sell)\b.*\b(saham|stock|crypto|bitcoin|btc|eth|reksadana)\b/ui',
            $message
        ) || (bool) preg_match(
            '/\b(saham|stock|crypto|bitcoin)\b.*\b(beli|buy|jual|sell|sekarang)\b/ui',
            $message
        );
    }

    private function isBudgetTrackingContext(string $message): bool
    {
        return (bool) preg_match('/\b(budget|anggaran|set\s+budget)\b/ui', $message)
            && (bool) preg_match('/\b(kategori|category)\b/ui', $message);
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @param  array<string, mixed>  $context
     * @param  array<string, mixed>  $channelContext
     * @return array<string, mixed>
     */
    private function resolveIntent(
        int $userId,
        string $message,
        array $normalized,
        array $context,
        string $channel,
        array $channelContext,
    ): array {
        $commandParse = $this->commandParser->parse($userId, $message, $context);
        $aiFallbackCalled = false;
        $intent = null;

        $rulesComplete = ($commandParse['matched'] ?? false)
            && ($commandParse['confidence'] ?? 0) >= 0.85
            && empty($commandParse['missing_fields']);

        $rulesPartialResolved = ($commandParse['matched'] ?? false)
            && ($commandParse['confidence'] ?? 0) >= 0.65
            && ! empty($commandParse['missing_fields'])
            && ! empty($commandParse['clarifying_question'])
            && $this->rulesParseHasUsefulEntities($commandParse);

        if ($rulesComplete) {
            $intent = $this->commandResultToIntent($commandParse);
        } elseif ($rulesPartialResolved) {
            $intent = $this->commandResultToIntent($commandParse);
        } else {
            $needsAi = ! ($commandParse['matched'] ?? false)
                || (
                    ($commandParse['confidence'] ?? 0) >= 0.65
                    && ! empty($commandParse['missing_fields'])
                )
                || (($commandParse['matched'] ?? false) && ($commandParse['confidence'] ?? 0) < 0.85);

            if ($needsAi) {
                $aiFallbackCalled = true;
                $parseOptions = [
                    'qwen_options' => array_merge(
                        $this->defaultQwenOptions($channel, forIntent: true),
                        $channelContext['qwen_options'] ?? []
                    ),
                ];
                $intent = $this->intentService->parseWithFallback(
                    $userId,
                    $message,
                    $normalized,
                    $commandParse,
                    $context,
                    $parseOptions
                );
            } else {
                $intent = $this->commandResultToIntent($commandParse);
            }
        }

        if ($intent === null) {
            $intent = $this->commandResultToIntent($commandParse);
        }

        if (! empty($intent['missing_fields']) && empty($intent['clarifying_question'])) {
            $intent['clarifying_question'] = $this->clarificationService->buildFieldSpecificClarification(
                $intent['action_type'] ?? '',
                $intent['missing_fields'],
                $intent['entities'] ?? []
            ) ?? $this->clarificationService->buildGenericExample($intent['action_type'] ?? '');
        }

        Log::info('ai.parser.chain', [
            'channel' => $channel,
            'original' => $message,
            'normalized_text' => $normalized['normalized_text'] ?? null,
            'rules_matched' => $commandParse['matched'] ?? null,
            'rules_intent' => $commandParse['intent'] ?? null,
            'rules_confidence' => $commandParse['confidence'] ?? null,
            'rules_entities' => $commandParse['entities'] ?? [],
            'rules_missing_fields' => $commandParse['missing_fields'] ?? [],
            'ai_fallback_called' => $aiFallbackCalled,
            'final_intent' => $intent['intent'] ?? null,
            'final_entities' => $intent['entities'] ?? [],
            'final_missing_fields' => $intent['missing_fields'] ?? [],
        ]);

        return $intent;
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @param  array<string, mixed>  $channelContext
     * @return array<string, mixed>|null
     */
    private function trySlotFillFromCollectingDraft(
        int $userId,
        string $channel,
        array $channelContext,
        AiConversation $conversation,
        string $message,
        array $normalized,
        string $periodKey,
    ): ?array {
        $draft = $this->findCollectingDraft($userId, $channel, $channelContext);
        if (! $draft) {
            return null;
        }

        if (preg_match('/^(yes|ya|y|confirm|ok|setuju|cancel|batal|no|tidak)$/u', trim($message))) {
            return null;
        }

        $payload = $draft->payload ?? [];
        $missing = $payload['missing_fields'] ?? [];
        $entities = $payload['partial_entities'] ?? [];
        $actionType = $draft->action_type;

        $filled = $this->fillSlotsFromMessage($userId, $message, $normalized, $missing, $entities, $actionType);
        $stillMissing = $filled['missing_fields'];
        $mergedEntities = $filled['entities'];

        if (! empty($stillMissing)) {
            $clarification = $this->clarificationService->buildFieldSpecificClarification(
                $actionType,
                $stillMissing,
                $mergedEntities
            ) ?? 'Mohon lengkapi informasi yang diminta.';

            $draft->update([
                'payload' => array_merge($payload, [
                    'partial_entities' => $mergedEntities,
                    'missing_fields' => $stillMissing,
                ]),
            ]);

            return [
                'ok' => true,
                'type' => 'clarification',
                'message' => $clarification,
                'structured' => [
                    'intent' => 'slot_fill',
                    'action_type' => $actionType,
                    'entities' => $mergedEntities,
                    'missing_fields' => $stillMissing,
                ],
                'draft_id' => $draft->id,
            ];
        }

        $intent = [
            'intent' => match ($actionType) {
                'create_wallet' => 'parse_wallet',
                'create_transfer' => 'parse_transfer',
                'create_transaction' => 'parse_transaction',
                'create_budget' => 'parse_budget',
                'create_recurring' => 'parse_recurring',
                default => 'unknown',
            },
            'confidence' => 0.95,
            'requires_action' => true,
            'action_type' => $actionType,
            'entities' => $mergedEntities,
            'missing_fields' => [],
            'clarifying_question' => null,
            'source' => 'slot_fill',
        ];

        $draft->update(['status' => AiActionDraft::STATUS_CANCELLED]);

        return $this->handleWriteIntent($userId, $channel, $channelContext, $conversation, $message, $intent, $periodKey);
    }

    /**
     * @param  list<string>  $missing
     * @param  array<string, mixed>  $entities
     * @return array{entities: array<string, mixed>, missing_fields: list<string>}
     */
    private function fillSlotsFromMessage(
        int $userId,
        string $message,
        array $normalized,
        array $missing,
        array $entities,
        string $actionType,
    ): array {
        $text = $normalized['normalized_text'] ?? mb_strtolower(trim($message));

        if ($actionType === 'create_wallet') {
            if (in_array('wallet_type', $missing, true)) {
                $type = $this->entityResolver->normalizeWalletType($text)
                    ?? $this->clarificationService->normalizeWalletTypeValue($text);
                if ($type) {
                    $entities['wallet_type'] = $type;
                }
            }
            if (in_array('wallet_name', $missing, true) && trim($message) !== '') {
                $entities['wallet_name'] = trim($message);
            }
            if (in_array('initial_balance', $missing, true)) {
                $amount = $this->normalizer->primaryAmount($normalized);
                if ($amount !== null) {
                    $entities['initial_balance'] = $amount;
                }
            }
            $entities = $this->clarificationService->normalizeWalletEntities($entities);
            $stillMissing = array_values(array_filter([
                empty($entities['wallet_name']) ? 'wallet_name' : null,
                empty($entities['wallet_type']) ? 'wallet_type' : null,
            ]));

            return ['entities' => $entities, 'missing_fields' => $stillMissing];
        }

        if ($actionType === 'create_budget') {
            if (in_array('category_name', $missing, true)) {
                $rawName = trim($message);
                $category = $this->entityResolver->resolveCategory($userId, $rawName);
                if ($category) {
                    $entities['category_name'] = $category['name'];
                    $entities['category_id'] = $category['id'];
                } elseif ($rawName !== '' && ! preg_match('/^\//u', $rawName)) {
                    $entities['category_name'] = $rawName;
                }
            }
            if (in_array('amount', $missing, true)) {
                $amount = $this->normalizer->primaryAmount($normalized);
                if ($amount !== null) {
                    $entities['amount'] = $amount;
                }
            }
            $stillMissing = array_values(array_filter([
                empty($entities['category_name']) ? 'category_name' : null,
                empty($entities['amount']) ? 'amount' : null,
            ]));

            return ['entities' => $entities, 'missing_fields' => $stillMissing];
        }

        if (in_array('amount', $missing, true)) {
            $amount = $this->normalizer->primaryAmount($normalized);
            if ($amount !== null) {
                $entities['amount'] = $amount;
            }
        }

        $stillMissing = array_values(array_filter($missing, function ($field) use ($entities) {
            return empty($entities[$field]) && empty($entities[str_replace('_name', '', $field)]);
        }));

        return ['entities' => $entities, 'missing_fields' => $stillMissing];
    }

    /**
     * @param  array<string, mixed>  $channelContext
     * @param  array<string, mixed>  $intent
     * @return array<string, mixed>
     */
    private function handleCollectingDraft(
        int $userId,
        string $channel,
        array $channelContext,
        AiConversation $conversation,
        string $message,
        array $intent,
        string $periodKey,
        ?string $clarification,
    ): array {
        $actionType = $intent['action_type'] ?? '';
        $entities = $intent['entities'] ?? [];
        $missing = $intent['missing_fields'] ?? [];

        $existing = $this->findCollectingDraft($userId, $channel, $channelContext);
        if ($existing) {
            $existing->update(['status' => AiActionDraft::STATUS_CANCELLED]);
        }

        $payload = [
            'partial_entities' => $entities,
            'missing_fields' => $missing,
            'expected_next_field' => $missing[0] ?? null,
        ];

        if ($actionType === 'create_wallet') {
            $normalized = $this->clarificationService->normalizeWalletEntities($entities);
            $payload['name'] = $normalized['wallet_name'] ?? null;
            $payload['type'] = $normalized['wallet_type'] ?? null;
            $payload['initial_balance'] = $normalized['initial_balance'] ?? 0;
        }

        $draft = AiActionDraft::create([
            'user_id' => $userId,
            'ai_conversation_id' => $conversation->id,
            'channel' => $channel,
            'telegram_chat_id' => $channelContext['telegram_chat_id'] ?? null,
            'action_type' => $actionType,
            'payload' => $payload,
            'preview_text' => null,
            'status' => AiActionDraft::STATUS_COLLECTING,
            'expires_at' => now()->addMinutes((int) config('ai.draft_expiry_minutes', 30)),
        ]);

        $this->logTraining($userId, $channel, $message, $periodKey, $intent, null, null, null);

        return [
            'ok' => true,
            'type' => 'clarification',
            'message' => $clarification ?? 'Mohon lengkapi informasi yang diminta.',
            'structured' => $intent,
            'draft_id' => $draft->id,
        ];
    }

    /**
     * @param  array<string, mixed>  $channelContext
     */
    private function findCollectingDraft(int $userId, string $channel, array $channelContext): ?AiActionDraft
    {
        $query = AiActionDraft::query()
            ->where('user_id', $userId)
            ->where('channel', $channel)
            ->where('status', AiActionDraft::STATUS_COLLECTING)
            ->where('expires_at', '>', now());

        if ($channel === 'telegram' && ! empty($channelContext['telegram_chat_id'])) {
            $query->where('telegram_chat_id', $channelContext['telegram_chat_id']);
        }

        return $query->latest()->first();
    }

    /**
     * @param  array<string, mixed>  $commandParse
     */
    private function rulesParseHasUsefulEntities(array $commandParse): bool
    {
        $entities = $commandParse['entities'] ?? [];
        foreach ($entities as $value) {
            if ($value !== null && $value !== '' && $value !== 0) {
                return true;
            }
        }

        return false;
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
