<?php

namespace App\Policies;

use App\Models\CommissionCampaign;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class CommissionCampaignPolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'commissions.campaign.view');
    }

    public function view(User $user, CommissionCampaign $commissionCampaign): bool
    {
        return (int) ($user->current_tenant_id ?? $user->tenant_id) === (int) $commissionCampaign->tenant_id
            && $this->safeHasPermission($user, 'commissions.campaign.view');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'commissions.campaign.create');
    }

    public function update(User $user, CommissionCampaign $commissionCampaign): bool
    {
        return (int) ($user->current_tenant_id ?? $user->tenant_id) === (int) $commissionCampaign->tenant_id
            && $this->safeHasPermission($user, 'commissions.campaign.update');
    }

    public function delete(User $user, CommissionCampaign $commissionCampaign): bool
    {
        return (int) ($user->current_tenant_id ?? $user->tenant_id) === (int) $commissionCampaign->tenant_id
            && $this->safeHasPermission($user, 'commissions.campaign.delete');
    }
}
