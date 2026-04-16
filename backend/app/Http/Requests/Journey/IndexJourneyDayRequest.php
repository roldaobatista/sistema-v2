<?php

namespace App\Http\Requests\Journey;

use App\Http\Requests\Journey\Concerns\ValidatesTenantUser;
use Illuminate\Foundation\Http\FormRequest;

class IndexJourneyDayRequest extends FormRequest
{
    use ValidatesTenantUser;

    public function authorize(): bool
    {
        return $this->user()->can('hr.clock.view');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'integer', $this->tenantUserExistsRule()],
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
            'approval_status' => 'nullable|in:pending,approved,rejected',
            'is_closed' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}
