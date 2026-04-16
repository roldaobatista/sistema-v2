<?php

namespace App\Http\Requests\RemainingModules;

use Illuminate\Foundation\Http\FormRequest;

class CreatePrintJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('admin.print.create');
    }

    public function rules(): array
    {
        return [
            'document_type' => 'required|in:certificate,label,receipt,report',
            'document_id' => 'required|integer',
            'printer_type' => 'required|in:bluetooth,wifi,usb',
            'copies' => 'integer|min:1|max:10',
        ];
    }
}
