<?php

namespace App\Http\Requests\Camera;

use App\Rules\StreamUrl;
use Illuminate\Foundation\Http\FormRequest;

class TestConnectionCameraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('tv.camera.manage');
    }

    public function rules(): array
    {
        return [
            'stream_url' => ['required', 'string', 'max:500', new StreamUrl],
        ];
    }
}
