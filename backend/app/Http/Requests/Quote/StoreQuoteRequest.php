<?php

namespace App\Http\Requests\Quote;

use App\Enums\PaymentTerms;
use App\Models\Lookups\PaymentTerm;
use App\Models\Lookups\QuoteSource;
use App\Models\Quote;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class StoreQuoteRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $cleaned = [];
        foreach (['source', 'payment_terms', 'payment_terms_detail'] as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $cleaned[$field] = null;
            }
        }

        if (! $this->filled('observations')) {
            if ($this->filled('description')) {
                $cleaned['observations'] = $this->input('description');
            } elseif ($this->filled('title')) {
                $cleaned['observations'] = $this->input('title');
            }
        }

        if ($cleaned) {
            $this->merge($cleaned);
        }
    }

    public function authorize(): bool
    {
        return $this->user()->can('quotes.quote.create');
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function ($v) {
            $pct = (float) ($this->discount_percentage ?? 0);
            $amt = (float) ($this->discount_amount ?? 0);
            if ($pct > 0 && $amt > 0) {
                $v->errors()->add('discount_amount', 'Não é possivel informar desconto percentual e valor fixo ao mesmo tempo.');
            }

            $hasLegacyDraftText = $this->filled('title')
                || $this->filled('description')
                || $this->filled('observations');

            $equipments = $this->input('equipments');
            $hasEquipments = is_array($equipments) && count($equipments) > 0;

            if (! $hasLegacyDraftText && ! $hasEquipments) {
                $v->errors()->add('equipments', 'O campo equipments é obrigatório.');
            }
        });
    }

    public function rules(): array
    {
        $tenantId = $this->tenantId();

        return [
            'source' => ['nullable', 'string', Rule::in($this->allowedValues(QuoteSource::class, Quote::SOURCES))],
            'customer_id' => ['required', Rule::exists('customers', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'seller_id' => ['nullable', Rule::exists('users', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'template_id' => ['nullable', Rule::exists('quote_templates', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'valid_until' => 'nullable|date|after_or_equal:today',
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'displacement_value' => 'nullable|numeric|min:0',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'observations' => 'nullable|string',
            'internal_notes' => 'nullable|string',
            'payment_terms' => ['nullable', 'string', 'max:50', Rule::in($this->allowedPaymentTerms())],
            'payment_terms_detail' => 'nullable|string|max:1000',
            'general_conditions' => 'nullable|string|max:5000',
            'equipments' => 'sometimes|array',
            'equipments.*.equipment_id' => ['required', Rule::exists('equipments', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'equipments.*.description' => 'nullable|string',
            'equipments.*.items' => 'required|array|min:1',
            'equipments.*.items.*.type' => 'required|in:product,service',
            'equipments.*.items.*.product_id' => ['nullable', 'required_if:equipments.*.items.*.type,product', Rule::exists('products', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'equipments.*.items.*.service_id' => ['nullable', 'required_if:equipments.*.items.*.type,service', Rule::exists('services', 'id')->where(fn ($q) => $q->where('tenant_id', $tenantId))],
            'equipments.*.items.*.custom_description' => 'nullable|string',
            'equipments.*.items.*.quantity' => 'required|numeric|min:0.01',
            'equipments.*.items.*.original_price' => 'required|numeric|min:0',
            'equipments.*.items.*.unit_price' => 'required|numeric|min:0',
            'equipments.*.items.*.discount_percentage' => 'nullable|numeric|min:0|max:100',
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
        $fallbackValues = array_values(array_unique([
            ...array_keys($fallback),
            ...array_values($fallback),
        ]));

        $table = (new $modelClass)->getTable();
        if (! Schema::hasTable($table)) {
            return $fallbackValues;
        }

        $lookupValues = $modelClass::query()
            ->where('tenant_id', $this->tenantId())
            ->where('is_active', true)
            ->get(['slug', 'name'])
            ->flatMap(fn ($item) => [$item->slug, $item->name])
            ->filter(fn (mixed $value): bool => is_string($value) && trim($value) !== '')
            ->map(fn (string $value): string => trim($value))
            ->values()
            ->all();

        return array_values(array_unique([
            ...$lookupValues,
            ...$fallbackValues,
        ]));
    }

    /**
     * @return array<int, string>
     */
    private function allowedPaymentTerms(): array
    {
        $fallback = [];
        foreach (PaymentTerms::cases() as $case) {
            $fallback[$case->value] = $case->label();
        }

        return $this->allowedValues(PaymentTerm::class, $fallback);
    }
}
