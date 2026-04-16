<?php

namespace Tests\Support;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Permission model para testes que auto-cria permissões inexistentes.
 * Evita PermissionDoesNotExist quando controllers checam $user->can() internamente.
 */
class AutoCreatePermission extends Permission
{
    protected $table = 'permissions';

    public static function findByName(string $name, ?string $guardName = null): \Spatie\Permission\Contracts\Permission
    {
        $guardName = $guardName ?? 'web';

        $permission = static::query()
            ->where('name', $name)
            ->where('guard_name', $guardName)
            ->first();

        if (! $permission) {
            $permission = static::create([
                'name' => $name,
                'guard_name' => $guardName,
            ]);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        return $permission;
    }

    public static function findById(int|string $id, ?string $guardName = null): \Spatie\Permission\Contracts\Permission
    {
        $guardName = $guardName ?? 'web';

        return static::query()
            ->where('id', $id)
            ->where('guard_name', $guardName)
            ->firstOrFail();
    }

    public static function findOrCreate(string $name, ?string $guardName = null): \Spatie\Permission\Contracts\Permission
    {
        $guardName = $guardName ?? 'web';

        $permission = static::query()
            ->where('name', $name)
            ->where('guard_name', $guardName)
            ->first();

        if (! $permission) {
            $permission = static::create([
                'name' => $name,
                'guard_name' => $guardName,
            ]);
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }

        return $permission;
    }
}
