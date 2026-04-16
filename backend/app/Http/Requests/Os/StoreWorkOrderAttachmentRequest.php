<?php

namespace App\Http\Requests\Os;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkOrderAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('os.work_order.update');
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'description' => $this->description === '' ? null : $this->description,
        ]);
    }

    public function rules(): array
    {
        return [
            'file' => [
                'required',
                'file',
                'max:51200',
                'mimetypes:image/jpeg,image/png,image/webp,image/gif,application/pdf,video/mp4,video/quicktime,video/x-msvideo,video/x-matroska',
            ],
            'description' => 'nullable|string|max:255',
        ];
    }
}
