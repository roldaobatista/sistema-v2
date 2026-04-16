<?php

namespace App\Http\Requests\Inmetro;

use Illuminate\Foundation\Http\FormRequest;

class StoreInmetroOwnerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('inmetro.intelligence.convert');
    }

    protected function prepareForValidation(): void
    {
        $document = preg_replace('/\D+/', '', (string) $this->input('document', ''));

        $this->merge([
            'document' => $document ?: null,
            'type' => $this->input('type', strlen($document) > 11 ? 'PJ' : 'PF'),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'document' => 'required|string|max:20',
            'trade_name' => 'nullable|string|max:255',
            'type' => 'required|in:PF,PJ',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'notes' => 'nullable|string',
            'state' => 'nullable|string|size:2',
            'city' => 'nullable|string|max:255',
        ];
    }
}
