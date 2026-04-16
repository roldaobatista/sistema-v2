<?php

namespace App\Http\Requests\Export;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ExportCustomersRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('cadastros.customer.view');
    }

    public function rules(): array
    {
        return [
            'format' => ['nullable', 'string', 'in:csv'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json([
                'message' => 'Parâmetros inválidos para exportação de clientes.',
                'errors' => $validator->errors(),
            ], 422)
        );
    }
}
