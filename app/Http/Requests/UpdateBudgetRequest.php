<?php

namespace App\Http\Requests;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class UpdateBudgetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('budget'));
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['sometimes', 'required', 'exists:categories,id'],
            'amount' => ['sometimes', 'required', 'numeric', 'gt:0'],
            'period' => ['sometimes', 'required', 'string', 'in:monthly,weekly'],
            'start_date' => ['sometimes', 'required', 'date'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('period') && !$this->has('start_date') && $this->route('budget')) {
            $start = $this->period === 'weekly'
                ? Carbon::now()->startOfWeek()->format('Y-m-d')
                : Carbon::now()->startOfMonth()->format('Y-m-d');
            $this->merge(['start_date' => $start]);
        }
    }
}
