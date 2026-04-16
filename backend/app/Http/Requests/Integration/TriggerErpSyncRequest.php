<?php

namespace App\Http\Requests\Integration;

use Illuminate\Foundation\Http\FormRequest;

class TriggerErpSyncRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('admin.integration.manage');
    }

    public function rules(): array
    {
        return [
            'provider' => 'required|in:conta_azul,omie,bling,tiny',
            'modules' => 'required|array|min:1',
            'modules.*' => 'in:customers,products,invoices,payments',
        ];
    }
}
