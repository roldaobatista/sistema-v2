<?php

declare(strict_types=1);

namespace App\Http\Requests\Analytics;

use Illuminate\Foundation\Http\FormRequest;

class AnalyticsNlQueryRequest extends FormRequest
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
            'query' => ['required', 'string', 'max:500'],
        ];
    }
}
