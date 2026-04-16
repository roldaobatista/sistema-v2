<?php

namespace App\Http\Requests\Finance;

use Illuminate\Foundation\Http\FormRequest;

class ImportCnabRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('finance.receivable.create');
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:txt,rem,ret|max:10240',
            'layout' => 'required|string|in:cnab240,cnab400',
            'type' => 'required|string|in:retorno,remessa',
        ];
    }
}
