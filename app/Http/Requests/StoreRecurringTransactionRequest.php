<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecurringTransactionRequest extends FormRequest
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
            'wallet_id' => [
                'required',
                'exists:wallets,id',
                Rule::exists('wallets', 'id')->where('user_id', $this->user()->id),
            ],
            'category_id' => ['required', 'exists:categories,id'],
            'type' => ['required', 'string', 'in:income,expense'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string'],
            'frequency' => ['required', 'string', 'in:daily,weekly,monthly'],
            'interval' => ['required', 'integer', 'min:1'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }
}
