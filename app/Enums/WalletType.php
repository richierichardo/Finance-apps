<?php

namespace App\Enums;

enum WalletType: string
{
    case Bank = 'bank';
    case Ewallet = 'ewallet';
    case Cash = 'cash';
}
