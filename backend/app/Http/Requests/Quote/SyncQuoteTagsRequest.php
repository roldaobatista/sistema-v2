<?php

namespace App\Http\Requests\Quote;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SyncQuoteTagsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('quotes.quote.update');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id ?? 0);

        return [
            'tag_ids' => 'required|array',
            'tag_ids.*' => ['integer', Rule::exists('quote_tags', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
        ];
    }
}
