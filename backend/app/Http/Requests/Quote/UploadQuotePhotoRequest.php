<?php

namespace App\Http\Requests\Quote;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadQuotePhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('quotes.quote.update');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'caption' => $this->caption === '' ? null : $this->caption,
        ]);
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id ?? 0);
        $quoteId = $this->route('quote')?->id ?? 0;

        return [
            'file' => 'required|image|mimes:jpg,jpeg,png,webp|max:10240',
            'quote_equipment_id' => [
                'required',
                Rule::exists('quote_equipments', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId)->where('quote_id', $quoteId)),
            ],
            'caption' => 'nullable|string|max:255',
        ];
    }
}
