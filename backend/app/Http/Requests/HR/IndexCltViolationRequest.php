<?php

declare(strict_types=1);

namespace App\Http\Requests\HR;

use Illuminate\Foundation\Http\FormRequest;

class IndexCltViolationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [
            'resolved' => ['sometimes', 'boolean'],
            'severity' => ['sometimes', 'string', 'in:critical,high,medium,low'],
            'violation_type' => ['sometimes', 'string', 'max:100'],
            'user_id' => ['sometimes', 'integer', 'min:1'],
            'start_date' => ['sometimes', 'date'],
            'end_date' => ['sometimes', 'date', 'after_or_equal:start_date'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ];
    }
}
