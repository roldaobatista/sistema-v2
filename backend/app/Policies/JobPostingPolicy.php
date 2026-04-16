<?php

namespace App\Policies;

use App\Models\JobPosting;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class JobPostingPolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'hr.recruitment.view');
    }

    public function view(User $user, JobPosting $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'hr.recruitment.view');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'hr.recruitment.manage');
    }

    public function update(User $user, JobPosting $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'hr.recruitment.manage');
    }

    public function delete(User $user, JobPosting $model): bool
    {
        if ((int) $user->current_tenant_id !== (int) $model->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'hr.recruitment.manage');
    }
}
