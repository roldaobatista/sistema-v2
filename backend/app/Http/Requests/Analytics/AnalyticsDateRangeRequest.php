<?php

declare(strict_types=1);

namespace App\Http\Requests\Analytics;

use Illuminate\Foundation\Http\FormRequest;

class AnalyticsDateRangeRequest extends FormRequest
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
            'from' => ['sometimes', 'date', 'before_or_equal:to'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
        ];
    }
}
