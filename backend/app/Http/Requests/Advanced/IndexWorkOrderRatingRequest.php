<?php

namespace App\Http\Requests\Advanced;

use Illuminate\Foundation\Http\FormRequest;

class IndexWorkOrderRatingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('os.work_order.view');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [
            'per_page' => 'nullable|integer|min:1|max:100',
        ];
    }
}
