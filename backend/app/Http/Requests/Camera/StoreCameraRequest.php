<?php

namespace App\Http\Requests\Camera;

use App\Models\Lookups\TvCameraType;
use App\Rules\StreamUrl;
use App\Support\LookupValueResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCameraRequest extends FormRequest
{
    private const CAMERA_TYPE_FALLBACK = [
        'ip' => 'IP',
        'usb' => 'USB',
        'analog' => 'Analogica',
        'wifi' => 'Wi-Fi',
    ];

    public function authorize(): bool
    {
        return $this->user()->can('tv.camera.manage');
    }

    public function rules(): array
    {
        $tenantId = (int) ($this->user()->current_tenant_id ?? $this->user()->tenant_id ?? 0);
        $allowed = LookupValueResolver::allowedValues(
            TvCameraType::class,
            self::CAMERA_TYPE_FALLBACK,
            $tenantId
        );

        return [
            'name' => 'required|string|max:100',
            'stream_url' => ['required', 'string', 'max:500', new StreamUrl],
            'location' => 'nullable|string|max:200',
            'type' => ['nullable', 'string', 'max:50', Rule::in($allowed)],
            'is_active' => 'boolean',
            'position' => 'nullable|integer|min:0',
        ];
    }
}
