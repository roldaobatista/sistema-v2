<?php

namespace App\Http\Requests\Finance;

use App\Models\CollectionRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCollectionRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('finance.receivable.update');
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'days_offset' => 'sometimes|integer',
            'channel' => 'sometimes|string|in:'.implode(',', CollectionRule::CHANNELS),
            'template_type' => 'nullable|string|in:'.implode(',', array_keys(CollectionRule::TEMPLATE_TYPES)),
            'message_body' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
        ];
    }
}
