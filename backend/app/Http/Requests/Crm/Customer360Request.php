<?php

namespace App\Http\Requests\Crm;

use Illuminate\Foundation\Http\FormRequest;

class Customer360Request extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('crm.deal.view');
    }

    /**
     * Customer 360 does not accept query filters; route parameters identify the customer.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
