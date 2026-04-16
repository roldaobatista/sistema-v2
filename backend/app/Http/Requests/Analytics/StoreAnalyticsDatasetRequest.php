<?php

declare(strict_types=1);

namespace App\Http\Requests\Analytics;

use Illuminate\Foundation\Http\FormRequest;

class StoreAnalyticsDatasetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('analytics.dataset.manage');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'source_modules' => ['required', 'array', 'min:1'],
            'source_modules.*' => ['string', 'in:work_orders,finance,crm,quality,hr'],
            'query_definition' => ['required', 'array'],
            'refresh_strategy' => ['required', 'string', 'in:manual,hourly,daily,weekly'],
            'cache_ttl_minutes' => ['nullable', 'integer', 'min:5', 'max:10080'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
