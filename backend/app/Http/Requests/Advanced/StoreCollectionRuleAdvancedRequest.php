<?php

namespace App\Http\Requests\Advanced;

use App\Models\CollectionRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCollectionRuleAdvancedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('finance.receivable.manage');
    }

    public function rules(): array
    {
        $channels = implode(',', CollectionRule::CHANNELS);

        return [
            'name' => 'required|string|max:255',
            'steps' => 'required|array|min:1',
            'steps.*.days_offset' => 'required|integer',
            'steps.*.channel' => 'required|in:'.$channels,
            'steps.*.message_template' => 'nullable|string',
        ];
    }
}
