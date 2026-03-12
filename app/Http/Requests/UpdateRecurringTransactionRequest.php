<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRecurringTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('recurring_transaction')->user_id === $this->user()->id;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'wallet_id' => [
                'sometimes',
                'required',
                'exists:wallets,id',
                Rule::exists('wallets', 'id')->where('user_id', $this->user()->id),
            ],
            'category_id' => ['sometimes', 'required', 'exists:categories,id'],
            'type' => ['sometimes', 'required', 'string', 'in:income,expense'],
            'amount' => ['sometimes', 'required', 'numeric', 'gt:0'],
            'description' => ['nullable', 'string'],
            'frequency' => ['sometimes', 'required', 'string', 'in:daily,weekly,monthly'],
            'interval' => ['sometimes', 'required', 'integer', 'min:1'],
            'start_date' => ['sometimes', 'required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
        ];
    }
}
