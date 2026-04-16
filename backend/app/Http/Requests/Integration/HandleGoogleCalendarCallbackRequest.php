<?php

namespace App\Http\Requests\Integration;

use Illuminate\Foundation\Http\FormRequest;

class HandleGoogleCalendarCallbackRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('platform.settings.manage');
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string'],
        ];
    }
}
