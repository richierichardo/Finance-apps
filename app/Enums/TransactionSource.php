<?php

namespace App\Enums;

enum TransactionSource: string
{
    /** Recommended VARCHAR length for `transactions.source` (longest case value). */
    public const DB_COLUMN_LENGTH = 32;

    case WebManual = 'web_manual';
    case WebAi = 'web_ai';
    case TelegramManual = 'telegram_manual';
    case TelegramAi = 'telegram_ai';
    case SystemInitialBalance = 'system_initial_balance';
    case SystemRecurring = 'system_recurring';
    case SystemRecalculation = 'sync_recalculation';

    /** @deprecated use WebManual */
    case Web = 'web';

    /** @deprecated use TelegramManual or TelegramAi */
    case Telegram = 'telegram';

    /** @deprecated use SystemRecurring or SystemInitialBalance */
    case System = 'system';

    case Whatsapp = 'whatsapp';
}
