<?php

namespace App\Http\Requests\Advanced;

use App\Models\CollectionRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCollectionRuleAdvancedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('finance.receivable.manage');
    }

    public function rules(): array
    {
        $channels = implode(',', CollectionRule::CHANNELS);

        return [
            'name' => 'sometimes|string|max:255',
            'steps' => 'sometimes|array|min:1',
            'steps.*.channel' => 'required_with:steps|in:'.$channels,
            'is_active' => 'nullable|boolean',
        ];
    }
}
