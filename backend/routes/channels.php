<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('tenant.{tenantId}.notifications', function ($user, $tenantId) {
    $activeTenantId = $user->current_tenant_id ?? $user->tenant_id;

    return (int) $activeTenantId === (int) $tenantId;
});

Broadcast::channel('user.{userId}.notifications', function ($user, $userId) {
    return (int) $user->id === (int) $userId;
});

Broadcast::channel('tenant.{tenantId}.tv.kpis', function ($user, $tenantId) {
    $activeTenantId = $user->current_tenant_id ?? $user->tenant_id;

    return (int) $activeTenantId === (int) $tenantId && $user->can('tv.dashboard.view');
});
