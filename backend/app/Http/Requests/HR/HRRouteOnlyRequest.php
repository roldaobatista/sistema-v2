<?php

namespace App\Http\Requests\HR;

class HRRouteOnlyRequest extends HRAdvancedFilterRequest
{
    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
