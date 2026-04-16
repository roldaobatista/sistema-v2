<?php

namespace App\Http\Requests\Integration;

use Illuminate\Foundation\Http\FormRequest;

class RequestPartnerIntegrationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('admin.integration.create');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('notes') && $this->input('notes') === '') {
            $this->merge(['notes' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'partner_id' => 'required|exists:marketplace_partners,id',
            'notes' => 'nullable|string',
        ];
    }
}
