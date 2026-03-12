<?php

namespace App\Http\Requests;

use App\Models\Wallet;
use Illuminate\Foundation\Http\FormRequest;

class TransferTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'from_wallet_id' => ['required', 'exists:wallets,id', 'different:to_wallet_id'],
            'to_wallet_id' => ['required', 'exists:wallets,id', 'different:from_wallet_id'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'description' => ['nullable', 'string', 'max:65535'],
            'occurred_at' => ['required', 'date'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $userId = auth()->id();
            if (! $userId || ! $this->from_wallet_id || ! $this->to_wallet_id) {
                return;
            }

            // Prevent transfer across users: both wallets must belong to auth user
            $userWalletCount = Wallet::belongsToUser($userId)
                ->whereIn('id', [$this->from_wallet_id, $this->to_wallet_id])
                ->count();

            if ($userWalletCount !== 2) {
                $validator->errors()->add('from_wallet_id', 'Both wallets must belong to you. Cannot transfer across different users.');
                return;
            }

            $fromWallet = Wallet::belongsToUser($userId)->find($this->from_wallet_id);
            $toWallet = Wallet::belongsToUser($userId)->find($this->to_wallet_id);

            if ($fromWallet && ! $fromWallet->is_active) {
                $validator->errors()->add('from_wallet_id', 'Transactions are not allowed on inactive wallets.');
            }
            if ($toWallet && ! $toWallet->is_active) {
                $validator->errors()->add('to_wallet_id', 'Transactions are not allowed on inactive wallets.');
            }
            if ($fromWallet && $toWallet && (! $fromWallet->is_active || ! $toWallet->is_active)) {
                return;
            }

            if ($fromWallet && (float) $this->amount > (float) $fromWallet->balance) {
                $validator->errors()->add('amount', 'Insufficient balance in source wallet.');
            }
        });
    }
}
