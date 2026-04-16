<?php

namespace App\Http\Requests\Finance;

use App\Models\CollectionRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCollectionRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('finance.receivable.create');
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'days_offset' => 'required|integer',
            'channel' => 'required|string|in:'.implode(',', CollectionRule::CHANNELS),
            'template_type' => 'nullable|string|in:'.implode(',', array_keys(CollectionRule::TEMPLATE_TYPES)),
            'message_body' => 'nullable|string',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer',
        ];
    }
}
