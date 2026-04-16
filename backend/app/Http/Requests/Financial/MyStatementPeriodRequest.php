<?php

namespace App\Http\Requests\Financial;

use Illuminate\Foundation\Http\FormRequest;

class MyStatementPeriodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('finance.manage');
    }

    public function rules(): array
    {
        return [
            'period' => ['required', 'string', 'size:7', 'regex:/^\d{4}-\d{2}$/'],
        ];
    }
}
