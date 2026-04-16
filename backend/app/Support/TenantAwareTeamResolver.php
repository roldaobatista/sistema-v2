<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Contracts\PermissionsTeamResolver;

/**
 * Resolve o team_id do Spatie Permissions a partir do container quando
 * setPermissionsTeamId() não foi chamado explicitamente.
 *
 * Isto garante que assignRole()/hasRole() funcione corretamente
 * desde que current_tenant_id esteja no container.
 */
class TenantAwareTeamResolver implements PermissionsTeamResolver
{
    protected int|string|null $teamId = null;

    public function setPermissionsTeamId($id): void
    {
        if ($id instanceof Model) {
            $id = $id->getKey();
        }
        $this->teamId = $id;
    }

    public function getPermissionsTeamId(): int|string|null
    {
        // Se foi definido explicitamente, usa
        if ($this->teamId !== null) {
            return $this->teamId;
        }

        // Fallback: tenta ler do container (usado em testes e jobs)
        if (app()->bound('current_tenant_id')) {
            return app('current_tenant_id');
        }

        return null;
    }
}
