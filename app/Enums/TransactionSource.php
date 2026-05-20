<?php

namespace App\Enums;

enum TransactionSource: string
{
    case Web = 'web';
    case Telegram = 'telegram';
    case TelegramAi = 'telegram_ai';
    case Whatsapp = 'whatsapp';
    case System = 'system';
}
