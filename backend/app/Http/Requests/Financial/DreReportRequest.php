<?php

namespace App\Http\Requests\Financial;

use App\Support\ApiResponse;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class DreReportRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'date_from' => $this->input('date_from', $this->input('from')),
            'date_to' => $this->input('date_to', $this->input('to')),
        ]);
    }

    public function authorize(): bool
    {
        return $this->user()->can('finance.dre.view');
    }

    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'os_number' => ['nullable', 'string', 'max:100'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::message('Parâmetros inválidos para DRE.', 422, ['errors' => $validator->errors()])
        );
    }
}
