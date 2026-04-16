<?php

namespace App\Policies;

use App\Models\Quote;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class QuotePolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'quotes.quote.view');
    }

    public function view(User $user, Quote $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'quotes.quote.view');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'quotes.quote.create');
    }

    public function update(User $user, Quote $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'quotes.quote.update');
    }

    public function delete(User $user, Quote $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'quotes.quote.delete');
    }

    public function send(User $user, Quote $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'quotes.quote.send');
    }

    public function approve(User $user, Quote $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'quotes.quote.approve');
    }

    public function internalApprove(User $user, Quote $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'quotes.quote.internal_approve');
    }

    public function convert(User $user, Quote $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'quotes.quote.convert');
    }
}
