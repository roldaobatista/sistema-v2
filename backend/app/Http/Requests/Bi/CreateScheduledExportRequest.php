<?php

namespace App\Http\Requests\Bi;

use Illuminate\Foundation\Http\FormRequest;

class CreateScheduledExportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('reports.analytics.view');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('filters') && $this->input('filters') === '') {
            $this->merge(['filters' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'report_type' => 'required|string|in:financial,os,stock,crm,productivity',
            'format' => 'required|string|in:xlsx,csv,pdf',
            'frequency' => 'required|string|in:daily,weekly,monthly',
            'recipients' => 'required|array|min:1',
            'recipients.*' => 'email',
            'filters' => 'nullable|array',
        ];
    }
}
