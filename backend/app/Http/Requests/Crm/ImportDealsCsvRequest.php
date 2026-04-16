<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;

class ImportDealsCsvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.deal.create');
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:csv,txt|max:5120',
        ];
    }
}
