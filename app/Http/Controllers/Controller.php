<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Wallet;
use App\Models\Transaction;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    use AuthorizesRequests;

    public function getUserWallets()
    {
        
        // Cara 3: dengan kondisi tambahan
        $wallets = Wallet::belongsToUser(auth()->id())
                    ->where('is_active', true)
                    ->get();
        
        return $wallets;
    }

    
}
