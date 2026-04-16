<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;

class DealsMarkWonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.deal.update');
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
