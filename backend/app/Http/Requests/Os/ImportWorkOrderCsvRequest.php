<?php

namespace App\Http\Requests\Os;

use Illuminate\Foundation\Http\FormRequest;

class ImportWorkOrderCsvRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('os.work_order.create');
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:csv,txt|max:5120',
        ];
    }
}
