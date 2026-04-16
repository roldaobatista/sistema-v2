<?php

namespace App\Http\Requests\Crm;

use App\Models\ImportantDate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateImportantDateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.deal.update');
    }

    public function rules(): array
    {
        return [
            'title' => 'string|max:255',
            'type' => [Rule::in(array_keys(ImportantDate::TYPES))],
            'date' => 'date',
            'recurring_yearly' => 'boolean',
            'remind_days_before' => 'integer|min:1|max:60',
            'contact_name' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }
}
