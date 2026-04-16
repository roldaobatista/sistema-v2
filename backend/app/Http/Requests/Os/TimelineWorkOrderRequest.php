<?php

namespace App\Http\Requests\Os;

use App\Models\WorkOrder;
use Illuminate\Foundation\Http\FormRequest;

class TimelineWorkOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var WorkOrder|null $workOrder */
        $workOrder = $this->route('work_order') ?? $this->route('workOrder');

        return $workOrder && $this->user()->can('view', $workOrder);
    }

    /**
     * @return array<int|string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
