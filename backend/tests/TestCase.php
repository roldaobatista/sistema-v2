<?php

namespace Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\PermissionRegistrar;

abstract class TestCase extends BaseTestCase
{
    use LazilyRefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Hash::driver('bcrypt')->setRounds(4);

        $this->seedRolesIfNeeded();
    }

    protected function tearDown(): void
    {
        Model::reguard();

        parent::tearDown();
    }

    protected static bool $rolesSeeded = false;

    protected function seedRolesIfNeeded(): void
    {
        if (static::$rolesSeeded) {
            $roleTable = config('permission.table_names.roles', 'roles');
            if (DB::table($roleTable)->count() === 0) {
                static::$rolesSeeded = false;
            } else {
                return;
            }
        }

        $guard = (string) config('auth.defaults.guard', 'web');
        $roleTable = config('permission.table_names.roles', 'roles');

        $roles = [
            'super_admin', 'admin', 'gerente', 'coordenador', 'tecnico',
            'financeiro', 'comercial', 'vendedor', 'tecnico_vendedor',
            'atendimento', 'rh', 'estoquista', 'qualidade', 'visualizador', 'viewer',
            'motorista', 'monitor',
        ];

        $now = now();
        $rows = array_map(fn ($name) => [
            'name' => $name,
            'guard_name' => $guard,
            'created_at' => $now,
            'updated_at' => $now,
        ], $roles);

        DB::table($roleTable)->insertOrIgnore($rows);

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        static::$rolesSeeded = true;
    }

    protected function setTenantContext(int $tenantId): void
    {
        app()->instance('current_tenant_id', $tenantId);
        setPermissionsTeamId($tenantId);
    }
}
