<?php

namespace App\Http\Requests\Crm;

use App\Models\VisitRoute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateVisitRouteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.deal.update');
    }

    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(array_keys(VisitRoute::STATUSES))],
            'name' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ];
    }
}
