<?php

namespace App\Http\Requests\Advanced;

use Illuminate\Foundation\Http\FormRequest;

class IndexFollowUpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('advanced.follow_up.view');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => 'nullable|string',
            'assigned_to' => 'nullable|integer',
            'search' => 'nullable|string|max:255',
            'overdue' => 'nullable|boolean',
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}
