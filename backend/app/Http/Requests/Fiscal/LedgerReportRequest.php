<?php

namespace App\Http\Requests\Fiscal;

use Illuminate\Foundation\Http\FormRequest;

class LedgerReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('fiscal.note.view');
    }

    public function rules(): array
    {
        return [
            'inicio' => 'required|date',
            'fim' => 'required|date|after_or_equal:inicio',
        ];
    }
}
