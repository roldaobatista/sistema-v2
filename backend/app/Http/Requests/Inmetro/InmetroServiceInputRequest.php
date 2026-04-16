<?php

namespace App\Http\Requests\Inmetro;

use App\Http\Requests\Concerns\AuthorizesRoutePermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InmetroServiceInputRequest extends FormRequest
{
    use AuthorizesRoutePermission;

    private const INTEGER_FIELDS = [
        'per_page',
        'days_until_due',
        'limit',
    ];

    private const BOOLEAN_FIELDS = [
        'only_leads',
        'only_converted',
        'overdue',
    ];

    public function authorize(): bool
    {
        return $this->authorizeFromRoutePermission();
    }

    protected function prepareForValidation(): void
    {
        $normalized = [];

        foreach (self::INTEGER_FIELDS as $field) {
            if ($this->has($field) && preg_match('/^-?\d+$/', (string) $this->input($field)) === 1) {
                $normalized[$field] = (int) $this->input($field);
            }
        }

        foreach (self::BOOLEAN_FIELDS as $field) {
            $value = $this->input($field);

            if (! $this->has($field) || is_bool($value)) {
                continue;
            }

            $normalizedValue = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($normalizedValue !== null) {
                $normalized[$field] = $normalizedValue;
            }
        }

        if ($this->has('uf') && is_string($this->input('uf'))) {
            $normalized['uf'] = strtoupper($this->input('uf'));
        }

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:255'],
            'lead_status' => ['nullable', Rule::in(['new', 'contacted', 'negotiating', 'converted', 'lost'])],
            'priority' => ['nullable', Rule::in(['critical', 'urgent', 'high', 'normal', 'low'])],
            'city' => ['nullable', 'string', 'max:255'],
            'only_leads' => ['nullable', 'boolean'],
            'only_converted' => ['nullable', 'boolean'],
            'sort_by' => ['nullable', Rule::in(['priority', 'name', 'city', 'state', 'created_at', 'updated_at'])],
            'sort_order' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'status' => ['nullable', 'string', 'max:100'],
            'days_until_due' => ['nullable', 'integer', 'min:0', 'max:3650'],
            'overdue' => ['nullable', 'boolean'],
            'instrument_type' => ['nullable', 'string', 'max:120'],
            'type' => ['nullable', 'string', 'max:50'],
            'owner_ids' => ['nullable', 'array'],
            'owner_ids.*' => ['integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:500'],
            'municipality' => ['nullable', 'string', 'max:255'],
            'uf' => ['nullable', 'string', 'size:2'],
            'message' => ['nullable', 'string', 'max:2000'],
            'results' => ['nullable', 'array'],
        ];
    }
}
