<?php

namespace App\Http\Requests\Os;

use Illuminate\Foundation\Http\FormRequest;

class SendWorkOrderChatMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('os.work_order.update');
    }

    public function rules(): array
    {
        return [
            'message' => 'required|string',
            'type' => 'sometimes|string|in:text,file',
            'file' => 'sometimes|file|max:10240|mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,csv,txt,zip,mp4,mov',
        ];
    }
}
