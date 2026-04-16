<?php

namespace App\Http\Requests\SystemImprovements;

use App\Models\CollectionRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCollectionRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('admin.settings.update');
    }

    public function rules(): array
    {
        return [
            'name' => 'string|max:255',
            'days_offset' => 'integer',
            'channel' => [Rule::in(CollectionRule::CHANNELS)],
            'template_type' => [Rule::in(array_keys(CollectionRule::TEMPLATE_TYPES))],
            'message_body' => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }
}
