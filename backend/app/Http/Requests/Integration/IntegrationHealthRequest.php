<?php

namespace App\Http\Requests\Integration;

use Illuminate\Foundation\Http\FormRequest;

class IntegrationHealthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasPermissionTo('admin.system.view') ?? false;
    }

    public function rules(): array
    {
        return [];
    }
}
