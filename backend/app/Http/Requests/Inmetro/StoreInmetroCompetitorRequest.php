<?php

namespace App\Http\Requests\Inmetro;

use Illuminate\Foundation\Http\FormRequest;

class StoreInmetroCompetitorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('metrology.competitor.manage');
    }

    protected function prepareForValidation(): void
    {
        $document = preg_replace('/\D+/', '', (string) $this->input('document', $this->input('cnpj', '')));

        $this->merge([
            'cnpj' => $document ?: null,
            'city' => $this->filled('city') ? trim((string) $this->input('city')) : 'Nao informado',
            'state' => strtoupper((string) $this->input('state', 'MT')),
        ]);
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'cnpj' => 'required|string|max:20',
            'phone' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'state' => 'nullable|string|size:2',
        ];
    }
}
