<?php

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePerformanceReviewRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $period = $this->input('period');

        if ($period === null && $this->filled('cycle') && $this->filled('year')) {
            $period = sprintf('%s-%s', $this->input('year'), $this->input('cycle'));
        }

        $merge = [];

        if ($period !== null) {
            $merge['period'] = (string) $period;
        }

        if (! $this->has('scores') && $this->filled('score')) {
            $merge['scores'] = [(float) $this->input('score')];
        }

        if ($merge !== []) {
            $this->merge($merge);
        }
    }

    public function authorize(): bool
    {
        return $this->user()->can('hr.performance.manage');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id);

        return [
            'user_id' => ['required', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'reviewer_id' => ['required', Rule::exists('users', 'id')->where('tenant_id', $tenantId)],
            'period' => 'required_without_all:title,cycle,year,type|string|max:50',
            'scores' => 'required_without_all:title,cycle,year,type|array|min:1',
            'scores.*' => 'required|numeric|min:0|max:10',
            'comments' => 'nullable|string',
            'goals' => 'nullable|array',
            'goals.*' => 'required|string|max:255',
            'title' => 'required_without_all:period,scores|string',
            'cycle' => 'required_without_all:period,scores|string',
            'deadline' => 'nullable|date',
            'year' => 'required_without_all:period,scores|integer',
            'type' => 'required_without_all:period,scores|in:360,manager,peer,self',
            'status' => 'nullable|string',
        ];
    }
}
