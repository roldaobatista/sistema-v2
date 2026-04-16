<?php

namespace App\Http\Requests\HR;

use Illuminate\Validation\Rule;

class HRClockHistoryRequest extends HRAdvancedFilterRequest
{
    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
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
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
