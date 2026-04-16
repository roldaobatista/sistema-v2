<?php

declare(strict_types=1);

namespace App\Http\Requests\Analytics;

use App\Models\Expense;
use Illuminate\Foundation\Http\FormRequest;

class ExpenseAnalyticsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Expense::class) ?? false;
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [
            'date_from' => ['sometimes', 'date'],
            'date_to' => ['sometimes', 'date', 'after_or_equal:date_from'],
        ];
    }
}
