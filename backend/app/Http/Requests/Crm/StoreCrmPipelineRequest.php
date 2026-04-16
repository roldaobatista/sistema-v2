<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreCrmPipelineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.pipeline.create');
    }

    protected function prepareForValidation(): void
    {
        $nullable = ['color'];
        $cleaned = [];
        foreach ($nullable as $field) {
            if ($this->has($field) && $this->input($field) === '') {
                $cleaned[$field] = null;
            }
        }
        if ($cleaned) {
            $this->merge($cleaned);
        }

        $name = trim((string) $this->input('name', ''));
        if ($name !== '' && ! $this->filled('slug')) {
            $this->merge([
                'slug' => Str::slug($name),
            ]);
        }

        if ($name !== '' && ! $this->has('stages')) {
            $this->merge([
                'stages' => [
                    ['name' => 'Entrada', 'probability' => 10],
                    ['name' => 'Proposta', 'probability' => 60],
                    ['name' => 'Fechado', 'probability' => 100, 'is_won' => true],
                ],
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'slug' => 'nullable|string|max:50',
            'color' => 'nullable|string|max:20',
            'stages' => 'required|array|min:1',
            'stages.*.name' => 'required|string|max:100',
            'stages.*.color' => 'nullable|string|max:20',
            'stages.*.probability' => 'integer|min:0|max:100',
            'stages.*.is_won' => 'boolean',
            'stages.*.is_lost' => 'boolean',
        ];
    }
}
