<?php

namespace App\Actions\ServiceCall;

use App\Http\Resources\ServiceCallResource;
use App\Models\ServiceCall;
use App\Models\User;
use App\Support\ApiResponse;

class IndexServiceCallAction extends BaseServiceCallAction
{
    /**
     * @param  array<string, mixed>  $data
     * @return mixed
     */
    public function execute(array $data, User $user, int $tenantId, bool $isScoped = false)
    {

        $tenantId = $tenantId;
        $query = ServiceCall::with(['customer:id,name', 'technician:id,name', 'driver:id,name'])
            ->withCount('equipments')
            ->where('tenant_id', $tenantId);

        if (($data['my'] ?? null)) {
            $userId = $user->id;
            $query->where(function ($q) use ($userId) {
                $q->where('technician_id', $userId)
                    ->orWhere('driver_id', $userId)
                    ->orWhere('created_by', $userId);
            });
        } elseif ($isScoped) {
            $userId = $user->id;
            $query->where(function ($q) use ($userId) {
                $q->where('technician_id', $userId)
                    ->orWhere('driver_id', $userId)
                    ->orWhere('created_by', $userId);
            });
        }

        if ($s = ($data['search'] ?? null)) {
            $s = str_replace(['%', '_'], ['\\%', '\\_'], $s);
            $query->where(function ($q) use ($s) {
                $q->where('call_number', 'like', "%$s%")
                    ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%$s%"));
            });
        }
        if ($status = ($data['status'] ?? null)) {
            $query->where('status', $status);
        }
        if ($techId = ($data['technician_id'] ?? null)) {
            $query->where('technician_id', $techId);
        }
        if ($priority = ($data['priority'] ?? null)) {
            $query->where('priority', $priority);
        }
        if ($dateFrom = ($data['date_from'] ?? null)) {
            $query->where('created_at', '>=', $dateFrom);
        }
        if ($dateTo = ($data['date_to'] ?? null)) {
            $query->where('created_at', '<=', $dateTo.' 23:59:59');
        }

        return ApiResponse::paginated($query->orderByDesc('created_at')->paginate(min((int) ($data['per_page'] ?? 30), 100)), resourceClass: ServiceCallResource::class);
    }
}
