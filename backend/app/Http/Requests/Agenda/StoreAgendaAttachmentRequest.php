<?php

namespace App\Http\Requests\Agenda;

use Illuminate\Foundation\Http\FormRequest;

class StoreAgendaAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('agenda.item.view');
    }

    public function rules(): array
    {
        return [
            'file' => 'required|file|max:10240|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,csv,txt,zip,rar',
        ];
    }

    public function messages(): array
    {
        return [
            'file.required' => 'O arquivo é obrigatório.',
            'file.max' => 'O arquivo não pode ter mais de 10 MB.',
        ];
    }
}
