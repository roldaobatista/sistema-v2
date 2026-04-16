<?php

namespace App\Http\Requests\Lgpd;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLgpdSecurityIncidentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('lgpd.incident.update');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'string', 'in:open,investigating,contained,resolved'],
            'measures_taken' => ['sometimes', 'string', 'max:5000'],
            'anpd_notification' => ['sometimes', 'string', 'max:5000'],
            'holders_notified' => ['sometimes', 'boolean'],
        ];
    }
}
