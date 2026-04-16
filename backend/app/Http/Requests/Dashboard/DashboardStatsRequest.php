<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Foundation\Http\FormRequest;

class DashboardStatsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('platform.dashboard.view');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('date_from') && $this->input('date_from') === '') {
            $this->merge(['date_from' => null]);
        }
        if ($this->has('date_to') && $this->input('date_to') === '') {
            $this->merge(['date_to' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'date_from' => 'nullable|date',
            'date_to' => 'nullable|date|after_or_equal:date_from',
        ];
    }
}
