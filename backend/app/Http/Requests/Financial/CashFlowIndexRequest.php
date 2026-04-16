<?php

namespace App\Http\Requests\Financial;

use App\Support\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class CashFlowIndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('finance.cashflow.view') ?? false;
    }

    public function rules(): array
    {
        return [
            'months' => ['nullable', 'integer', 'min:1', 'max:36'],
            'os_number' => ['nullable', 'string', 'max:100'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::message('Parâmetros inválidos para fluxo de caixa.', 422, ['errors' => $validator->errors()])
        );
    }
}
