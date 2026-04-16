<?php

namespace App\Http\Requests\Customer;

use App\Models\Customer;
use App\Models\Lookups\CustomerRating;
use App\Models\Lookups\CustomerSegment;
use App\Models\Lookups\LeadSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('cadastros.customer.view');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('type')) {
            $rawType = strtoupper((string) $this->input('type'));

            if (in_array($rawType, ['COMPANY', 'PJ'], true)) {
                $this->merge(['type' => 'PJ']);
            } elseif (in_array($rawType, ['PERSON', 'INDIVIDUAL', 'PF'], true)) {
                $this->merge(['type' => 'PF']);
            }
        }

        if (! $this->has('is_active')) {
            return;
        }

        $rawValue = $this->input('is_active');
        if (is_string($rawValue)) {
            $normalizedValue = filter_var($rawValue, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if ($normalizedValue !== null) {
                $this->merge(['is_active' => $normalizedValue]);
            }
        }
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'search' => 'nullable|string|max:255',
            'type' => ['nullable', Rule::in(['PF', 'PJ'])],
            'is_active' => 'nullable|boolean',
            'segment' => ['nullable', 'string', Rule::in($this->allowedValues(CustomerSegment::class, Customer::SEGMENTS))],
            'rating' => ['nullable', 'string', Rule::in($this->allowedValues(CustomerRating::class, Customer::RATINGS))],
            'source' => ['nullable', 'string', Rule::in($this->allowedValues(LeadSource::class, Customer::SOURCES))],
            'assigned_seller_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('tenant_id', $tenantId),
            ],
            'sort' => ['nullable', Rule::in(['name', 'created_at', 'health_score', 'last_contact_at', 'rating'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
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
