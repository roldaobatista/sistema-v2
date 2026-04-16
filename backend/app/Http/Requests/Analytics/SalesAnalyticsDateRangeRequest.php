<?php

declare(strict_types=1);

namespace App\Http\Requests\Analytics;

use App\Models\AccountReceivable;
use Illuminate\Foundation\Http\FormRequest;

class SalesAnalyticsDateRangeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', AccountReceivable::class) ?? false;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['sometimes', 'date'],
            'to' => ['sometimes', 'date', 'after_or_equal:from'],
            'months' => ['sometimes', 'integer', 'min:1', 'max:36'],
        ];
    }
}
