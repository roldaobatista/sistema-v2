<?php

namespace App\Http\Requests\HR;

use Illuminate\Validation\Rule;

class HRComprovanteRequest extends HRAdvancedFilterRequest
{
    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [
            'format' => ['nullable', Rule::in(['pdf'])],
        ];
    }
}
