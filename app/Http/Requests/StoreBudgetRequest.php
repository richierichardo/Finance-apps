<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class StoreBudgetRequest extends FormRequest
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
            'category_id' => ['required', 'exists:categories,id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'period' => ['required', 'string', 'in:monthly,weekly'],
            'start_date' => ['required', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (!$this->has('start_date') && $this->filled('period')) {
            $start = $this->period === 'weekly'
                ? Carbon::now()->startOfWeek()->format('Y-m-d')
                : Carbon::now()->startOfMonth()->format('Y-m-d');
            $this->merge(['start_date' => $start]);
        }
    }
}
