<?php

namespace App\Http\Requests;

use App\Enums\TransactionCategoryExpenses;
use App\Enums\TransactionCategoryIncome;
use App\Enums\TransactionSource;
use App\Enums\TransactionType;
use App\Models\Wallet;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransactionRequest extends FormRequest
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
            'wallet_id' => ['required', 'exists:wallets,id'],
            'type' => ['required', Rule::enum(TransactionType::class)],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'category_transaction' => [
                'nullable',
                Rule::in([
                    ...array_column(TransactionCategoryIncome::cases(), 'value'),
                    ...array_column(TransactionCategoryExpenses::cases(), 'value'),
                ]),
            ],
            'description' => ['nullable', 'string', 'max:65535'],
            'occurred_at' => ['required', 'date'],
            'source' => ['nullable', Rule::enum(TransactionSource::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (!$this->has('source')) {
            $this->merge(['source' => 'web']);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $userId = auth()->id();
            if (!$userId || !$this->wallet_id) {
                return;
            }
            $wallet = Wallet::belongsToUser($userId)->find($this->wallet_id);
            if (! $wallet) {
                $validator->errors()->add('wallet_id', 'The wallet does not belong to you.');
                return;
            }
            if (! $wallet->is_active) {
                $validator->errors()->add('wallet_id', 'Transactions are not allowed on inactive wallets.');
            }
        });
    }
}
