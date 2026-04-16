<?php

declare(strict_types=1);

namespace App\Http\Requests\Analytics;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDataExportJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('analytics.export.create');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'analytics_dataset_id' => ['required', 'integer', Rule::exists('analytics_datasets', 'id')->where('tenant_id', $this->user()?->current_tenant_id)],
            'filters' => ['nullable', 'array'],
            'output_format' => ['required', 'string', 'in:csv,xlsx,json'],
            'scheduled_cron' => ['nullable', 'string', 'max:100'],
        ];
    }
}
