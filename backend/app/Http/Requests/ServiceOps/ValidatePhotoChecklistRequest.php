<?php

namespace App\Http\Requests\ServiceOps;

use Illuminate\Foundation\Http\FormRequest;

class ValidatePhotoChecklistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('os.work_order.view');
    }

    public function rules(): array
    {
        return [
            'step' => 'required|string|in:before,during,after',
            'photos' => 'required|array|min:1',
            'photos.*.file' => 'required|image|mimes:jpg,jpeg,png,webp|max:10240',
            'photos.*.description' => 'nullable|string|max:255',
            'photos.*.checklist_item_id' => 'nullable|integer',
        ];
    }
}
