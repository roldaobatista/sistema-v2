<?php

namespace App\Http\Requests\HR;

use App\Http\Requests\Concerns\AuthorizesRoutePermission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class HRAdvancedFilterRequest extends FormRequest
{
    use AuthorizesRoutePermission;

    private const INTEGER_FIELDS = [
        'year',
        'per_page',
        'user_id',
        'days',
        'limit',
    ];

    private const BOOLEAN_FIELDS = [
        'active_only',
        'expiring',
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

        if ($normalized !== []) {
            $this->merge($normalized);
        }
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'date' => ['nullable', 'date'],
            'month' => ['nullable', 'regex:/^(\d{4}-\d{2}|0?[1-9]|1[0-2])$/'],
            'year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'active_only' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', 'max:100'],
            'search' => ['nullable', 'string', 'max:255'],
            'role' => ['nullable', 'string', 'max:255'],
            'user_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where(
                    fn ($query) => $query->where('tenant_id', $tenantId)
                        ->orWhereIn(
                            'id',
                            fn ($subquery) => $subquery
                                ->select('user_id')
                                ->from('user_tenants')
                                ->where('tenant_id', $tenantId)
                        )
                ),
            ],
            'type' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', 'string', 'max:100'],
            'expiring' => ['nullable', 'boolean'],
            'days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'action' => ['nullable', 'string', 'max:100'],
            'method' => ['nullable', 'string', 'max:50'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:300'],
        ];
    }
}
