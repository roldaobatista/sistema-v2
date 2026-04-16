<?php

namespace App\Http\Requests\Journey;

use Illuminate\Foundation\Http\FormRequest;

class SubmitExpenseReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('hr.clock.view');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [
            'items' => 'required|array|min:1',
            'items.*.type' => 'required|string|in:alimentacao,transporte,hospedagem,pedagio,combustivel,outros',
            'items.*.description' => 'required|string|max:255',
            'items.*.amount' => 'required|numeric|min:0.01',
            'items.*.expense_date' => 'required|date',
            'items.*.receipt_path' => 'nullable|string|max:500',
        ];
    }
}
