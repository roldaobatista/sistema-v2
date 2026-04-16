<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

class CreateServiceCallFromPortalRequest extends FormRequest
{
    private const PRIORITY_MAP = [
        'medium' => 'normal',
        'critical' => 'urgent',
    ];

    public function authorize(): bool
    {
        return $this->user() !== null; // Authenticated user — no specific permission required
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('priority') && $this->input('priority') === '') {
            $this->merge(['priority' => null]);
        }
        if ($this->filled('priority')) {
            $priority = strtolower((string) $this->input('priority'));
            $this->merge([
                'priority' => self::PRIORITY_MAP[$priority] ?? $priority,
            ]);
        }
        if ($this->has('equipment_id') && $this->input('equipment_id') === '') {
            $this->merge(['equipment_id' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'priority' => 'nullable|string|in:low,normal,medium,high,urgent,critical',
            'equipment_id' => 'nullable|integer',
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:10240|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,txt',
        ];
    }
}
