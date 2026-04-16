<?php

namespace App\Http\Requests\Equipment;

use Illuminate\Foundation\Http\FormRequest;

class UploadEquipmentDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('equipments.document.create');
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('expires_at') && $this->input('expires_at') === '') {
            $this->merge(['expires_at' => null]);
        }
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|max:10240|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx,csv,txt',
            'type' => 'required|in:certificado,manual,foto,laudo,relatorio',
            'name' => 'required|string|max:150',
            'expires_at' => 'nullable|date',
        ];
    }
}
