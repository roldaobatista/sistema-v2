<?php

namespace App\Http\Requests\Features;

use Illuminate\Foundation\Http\FormRequest;

class UploadDocumentFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('quality.document.create');
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|max:20480|mimes:pdf,doc,docx,xls,xlsx,csv,txt,jpg,jpeg,png,gif,webp,zip,rar,ppt,pptx',
        ];
    }
}
