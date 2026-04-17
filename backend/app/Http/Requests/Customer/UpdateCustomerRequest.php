<?php

namespace App\Http\Requests\Customer;

use App\Models\Customer;
use App\Models\Lookups\ContractType;
use App\Models\Lookups\CustomerCompanySize;
use App\Models\Lookups\CustomerRating;
use App\Models\Lookups\CustomerSegment;
use App\Models\Lookups\LeadSource;
use App\Rules\CpfCnpj;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('cadastros.customer.update');
    }

    protected function prepareForValidation(): void
    {
        $stringFields = [
            'name', 'trade_name', 'email', 'phone', 'phone2', 'notes',
            'address_street', 'address_number', 'address_complement',
            'address_neighborhood', 'address_city', 'address_state', 'address_zip',
        ];

        $cleaned = [];
        foreach ($stringFields as $field) {
            if ($this->has($field) && is_string($this->input($field))) {
                $cleaned[$field] = strip_tags($this->input($field));
            }
        }

        if ($cleaned) {
            $this->merge($cleaned);
        }

        if ($this->has('type')) {
            $type = strtoupper((string) $this->input('type', ''));
            if ($type === 'COMPANY') {
                $type = 'PJ';
            } elseif ($type === 'PERSON') {
                $type = 'PF';
            }

            $this->merge(['type' => $type]);
        }
    }

    public function rules(): array
    {
        $customer = $this->route('customer');
        $tenantId = $this->tenantId();

        return [
            'type' => 'sometimes|in:PF,PJ',
            'name' => 'sometimes|string|max:255',
            'trade_name' => 'nullable|string|max:255',
            'document' => [
                'nullable', 'string', 'max:20',
                new CpfCnpj,
                // `document` é encrypted (cast `encrypted`) — Wave 1B usa `document_hash`
                // (HMAC-SHA256 determinístico) para garantir unicidade.
                function (string $attribute, mixed $value, \Closure $fail) use ($tenantId, $customer): void {
                    if (! is_string($value) || $value === '') {
                        return;
                    }
                    $hash = Customer::hashSearchable('document', $value);
                    $ignoreId = $customer instanceof Customer ? $customer->id : (int) $customer;
                    $exists = Customer::query()
                        ->withoutGlobalScope('tenant')
                        ->where('tenant_id', $tenantId)
                        ->whereNull('deleted_at')
                        ->where('document_hash', $hash)
                        ->where('id', '!=', $ignoreId)
                        ->exists();
                    if ($exists) {
                        $fail('já existe um cliente com este documento.');
                    }
                },
            ],
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:20',
            'phone2' => 'nullable|string|max:20',
            'address_zip' => 'nullable|string|max:10',
            'address_street' => 'nullable|string|max:255',
            'address_number' => 'nullable|string|max:20',
            'address_complement' => 'nullable|string|max:100',
            'address_neighborhood' => 'nullable|string|max:100',
            'address_city' => 'nullable|string|max:100',
            'address_state' => 'nullable|string|max:2',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'google_maps_link' => 'nullable|url|max:500',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
            'source' => ['nullable', 'string', Rule::in($this->allowedValues(LeadSource::class, Customer::SOURCES))],
            'segment' => ['nullable', 'string', Rule::in($this->allowedValues(CustomerSegment::class, Customer::SEGMENTS))],
            'company_size' => ['nullable', 'string', Rule::in($this->allowedValues(CustomerCompanySize::class, Customer::COMPANY_SIZES))],
            'annual_revenue_estimate' => 'nullable|numeric|min:0',
            'contract_type' => ['nullable', 'string', Rule::in($this->allowedValues(ContractType::class, Customer::CONTRACT_TYPES))],
            'contract_start' => 'nullable|date',
            'contract_end' => 'nullable|date|after_or_equal:contract_start',
            'rating' => ['nullable', 'string', Rule::in($this->allowedValues(CustomerRating::class, Customer::RATINGS))],
            'assigned_seller_id' => [
                'nullable',
                Rule::exists('users', 'id')->where('tenant_id', $tenantId),
            ],
            // Enrichment fields
            'state_registration' => 'nullable|string|max:30',
            'municipal_registration' => 'nullable|string|max:30',
            'cnae_code' => 'nullable|string|max:10',
            'cnae_description' => 'nullable|string|max:255',
            'legal_nature' => 'nullable|string|max:255',
            'capital' => 'nullable|numeric|min:0',
            'simples_nacional' => 'nullable|boolean',
            'mei' => 'nullable|boolean',
            'company_status' => 'nullable|string|max:50',
            'opened_at' => 'nullable|date',
            'is_rural_producer' => 'boolean',
            'partners' => 'nullable|array',
            'partners.*.name' => 'nullable|string|max:255',
            'partners.*.role' => 'nullable|string|max:255',
            'partners.*.document' => 'nullable|string|max:20',
            'secondary_activities' => 'nullable|array',
            'secondary_activities.*.code' => 'nullable|string|max:10',
            'secondary_activities.*.description' => 'nullable|string|max:255',
            'enrichment_data' => 'nullable|array',
            'enriched_at' => 'nullable|date',

            'tags' => 'nullable|array',
            'tags.*' => 'string|max:50',
            'contacts' => 'nullable|array',
            'contacts.*.id' => [
                'nullable',
                Rule::exists('customer_contacts', 'id')
                    ->where('tenant_id', $tenantId)
                    ->where('customer_id', $customer instanceof Customer ? $customer->id : $customer),
            ],
            'contacts.*.name' => 'required|string|max:255',
            'contacts.*.role' => 'nullable|string|max:100',
            'contacts.*.phone' => 'nullable|string|max:20',
            'contacts.*.email' => 'nullable|email|max:255',
            'contacts.*.is_primary' => 'boolean',
        ];
    }

    private function tenantId(): int
    {
        return (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string, string>  $fallback
     * @return array<int, string>
     */
    private function allowedValues(string $modelClass, array $fallback): array
    {
        $values = $modelClass::query()
            ->where('tenant_id', $this->tenantId())
            ->where('is_active', true)
            ->pluck('slug')
            ->filter(fn (mixed $value): bool => is_string($value) && trim($value) !== '')
            ->values()
            ->all();

        if (! empty($values)) {
            return $values;
        }

        return array_keys($fallback);
    }
}
