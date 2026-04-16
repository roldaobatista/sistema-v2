<?php

namespace App\Actions\ServiceCall;

use App\Models\Role;
use App\Models\User;
use App\Support\ApiResponse;

class AssigneesServiceCallAction extends BaseServiceCallAction
{
    /**
     * @return mixed
     */
    public function execute(User $user, int $tenantId)
    {

        $tenantId = $tenantId;

        $users = User::query()
            ->where('is_active', true)
            ->where(function ($query) use ($tenantId) {
                $query
                    ->where('tenant_id', $tenantId)
                    ->orWhere('current_tenant_id', $tenantId)
                    ->orWhereHas('tenants', fn ($tenantQuery) => $tenantQuery->where('tenants.id', $tenantId));
            })
            ->whereHas('roles', fn ($query) => $query->whereIn('name', [Role::TECNICO, Role::MOTORISTA]))
            ->with('roles:id,name')
            ->orderBy('name')
            ->get(['id', 'name', 'email']);

        $toPayload = fn (User $user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
        ];

        return ApiResponse::data([
            'technicians' => $users
                ->filter(fn (User $user) => $user->roles->contains('name', Role::TECNICO))
                ->values()
                ->map($toPayload),
            'drivers' => $users
                ->filter(fn (User $user) => $user->roles->contains('name', Role::MOTORISTA))
                ->values()
                ->map($toPayload),
        ]);
    }
}
