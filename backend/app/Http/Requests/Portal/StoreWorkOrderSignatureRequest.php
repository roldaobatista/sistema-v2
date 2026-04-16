<?php

namespace App\Http\Requests\Portal;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkOrderSignatureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('os.work_order.update');
    }

    public function rules(): array
    {
        return [
            'signer_name' => 'required|string|max:255',
            'signature_data' => 'required|string',
        ];
    }
}
