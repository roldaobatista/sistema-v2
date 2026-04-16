<?php

namespace App\Http\Requests\Quote;

use App\Enums\QuoteStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListQuotesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('quotes.quote.view');
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'search' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', Rule::in(array_map(
                fn (QuoteStatus $status): string => $status->value,
                QuoteStatus::cases(),
            ))],
            'seller_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where(
                fn ($query) => $query->where('tenant_id', $tenantId),
            )],
            'tag_id' => ['nullable', 'integer', Rule::exists('quote_tags', 'id')->where(
                fn ($query) => $query->where('tenant_id', $tenantId),
            )],
            'customer_id' => ['nullable', 'integer', Rule::exists('customers', 'id')->where(
                fn ($query) => $query->where('tenant_id', $tenantId),
            )],
            'source' => ['nullable', 'string', 'max:50'],
            'total_min' => ['nullable', 'numeric', 'min:0'],
            'total_max' => ['nullable', 'numeric', 'min:0'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }
}
