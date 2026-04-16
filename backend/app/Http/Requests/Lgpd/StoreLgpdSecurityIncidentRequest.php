<?php

namespace App\Http\Requests\Lgpd;

use Illuminate\Foundation\Http\FormRequest;

class StoreLgpdSecurityIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('lgpd.incident.create');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [
            'severity' => ['required', 'string', 'in:low,medium,high,critical'],
            'description' => ['required', 'string', 'max:5000'],
            'affected_data' => ['required', 'string', 'max:2000'],
            'affected_holders_count' => ['required', 'integer', 'min:0'],
            'measures_taken' => ['nullable', 'string', 'max:5000'],
            'detected_at' => ['required', 'date'],
        ];
    }
}
