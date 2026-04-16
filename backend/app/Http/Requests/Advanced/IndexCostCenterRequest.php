<?php

namespace App\Http\Requests\Advanced;

use Illuminate\Foundation\Http\FormRequest;

class IndexCostCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('advanced.cost_center.view');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
