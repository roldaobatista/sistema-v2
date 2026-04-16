<?php

namespace App\Http\Requests\Os;

use Illuminate\Foundation\Http\FormRequest;

class UploadChecklistPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('os.work_order.update');
    }

    public function rules(): array
    {
        return [
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            'checklist_item_id' => ['nullable', 'string'],
            'step' => ['nullable', 'in:before,during,after'],
        ];
    }
}
