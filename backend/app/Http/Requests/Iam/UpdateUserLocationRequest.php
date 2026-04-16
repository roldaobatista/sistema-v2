<?php

namespace App\Http\Requests\Iam;

use Illuminate\Foundation\Http\FormRequest;

class UpdateUserLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user) {
            return false;
        }

        if ($user->hasAnyRole(['super_admin', 'admin', 'gerente', 'tecnico'])) {
            return true;
        }

        return $user->hasAnyPermission([
            'os.work_order.view',
            'os.work_order.update',
            'technicians.schedule.view',
            'technicians.time_entry.view',
            'route.plan.view',
        ]);
    }

    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ];
    }
}
