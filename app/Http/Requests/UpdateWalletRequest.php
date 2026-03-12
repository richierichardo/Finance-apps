<?php

namespace App\Http\Requests;

use App\Enums\WalletType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWalletRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:191'],
            'type' => ['sometimes', 'required', Rule::enum(WalletType::class)],
            'initial_balance' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
