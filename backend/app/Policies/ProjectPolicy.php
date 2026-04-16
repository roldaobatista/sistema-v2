<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;
use App\Policies\Concerns\SafePermissionCheck;

class ProjectPolicy
{
    use SafePermissionCheck;

    public function viewAny(User $user): bool
    {
        return $this->safeHasPermission($user, 'projects.project.view');
    }

    public function view(User $user, Project $project): bool
    {
        if ((int) $user->current_tenant_id !== (int) $project->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'projects.project.view');
    }

    public function create(User $user): bool
    {
        return $this->safeHasPermission($user, 'projects.project.create');
    }

    public function update(User $user, Project $project): bool
    {
        if ((int) $user->current_tenant_id !== (int) $project->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'projects.project.update');
    }

    public function delete(User $user, Project $project): bool
    {
        if ((int) $user->current_tenant_id !== (int) $project->tenant_id) {
            return false;
        }

        return $this->safeHasPermission($user, 'projects.project.delete');
    }
}
