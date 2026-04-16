<?php

namespace App\Http\Requests\SystemImprovements;

use App\Models\CollectionRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCollectionRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('admin.settings.create');
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'days_offset' => 'required|integer',
            'channel' => ['required', Rule::in(CollectionRule::CHANNELS)],
            'template_type' => [Rule::in(array_keys(CollectionRule::TEMPLATE_TYPES))],
            'message_body' => 'nullable|string',
        ];
    }
}
