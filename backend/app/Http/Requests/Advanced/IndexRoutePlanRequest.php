<?php

namespace App\Http\Requests\Advanced;

use Illuminate\Foundation\Http\FormRequest;

class IndexRoutePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('advanced.route_plan.view');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [
            'technician_id' => 'nullable|integer',
            'date' => 'nullable|date',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}
