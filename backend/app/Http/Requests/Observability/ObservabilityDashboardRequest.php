<?php

declare(strict_types=1);

namespace App\Http\Requests\Observability;

use Illuminate\Foundation\Http\FormRequest;

class ObservabilityDashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('platform.settings.view');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
