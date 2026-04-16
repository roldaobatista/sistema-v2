<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tableNames = config('permission.table_names');

        throw_if(empty($tableNames), Exception::class, 'Error: config/permission.php not loaded.');

        // ── model_has_roles ──
        if (! Schema::hasColumn($tableNames['model_has_roles'], 'tenant_id')) {
            Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($tableNames) {
                $table->dropForeign([$tableNames['model_has_roles'] === 'model_has_roles' ? 'role_id' : 'role_id']);
            });

            Schema::table($tableNames['model_has_roles'], function (Blueprint $table) {
                $table->dropPrimary('model_has_roles_role_model_type_primary');
            });

            Schema::table($tableNames['model_has_roles'], function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->default(0);
                $table->index('tenant_id', 'model_has_roles_team_foreign_key_index');
            });

            DB::statement("
                UPDATE {$tableNames['model_has_roles']} mhr
                INNER JOIN users u ON u.id = mhr.model_id AND mhr.model_type = ?
                SET mhr.tenant_id = COALESCE(u.current_tenant_id, u.tenant_id, 0)
            ", ['App\\Models\\User']);

            $multiTenantRows = DB::select("
                SELECT DISTINCT mhr.role_id, mhr.model_id, mhr.model_type, ut.tenant_id
                FROM {$tableNames['model_has_roles']} mhr
                INNER JOIN user_tenants ut ON ut.user_id = mhr.model_id
                WHERE mhr.model_type = ?
                  AND ut.tenant_id != mhr.tenant_id
                  AND NOT EXISTS (
                      SELECT 1 FROM {$tableNames['model_has_roles']} mhr2
                      WHERE mhr2.role_id = mhr.role_id
                        AND mhr2.model_id = mhr.model_id
                        AND mhr2.model_type = mhr.model_type
                        AND mhr2.tenant_id = ut.tenant_id
                  )
            ", ['App\\Models\\User']);

            foreach ($multiTenantRows as $row) {
                DB::table($tableNames['model_has_roles'])->insert([
                    'role_id' => $row->role_id,
                    'model_id' => $row->model_id,
                    'model_type' => $row->model_type,
                    'tenant_id' => $row->tenant_id,
                ]);
            }

            Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($tableNames) {
                $table->primary(
                    ['tenant_id', 'role_id', 'model_id', 'model_type'],
                    'model_has_roles_role_model_type_primary'
                );
                $table->foreign('role_id')
                    ->references('id')
                    ->on($tableNames['roles'])
                    ->onDelete('cascade');
            });
        }

        // ── model_has_permissions ──
        if (! Schema::hasColumn($tableNames['model_has_permissions'], 'tenant_id')) {
            Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) {
                $table->dropForeign(['permission_id']);
            });

            Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) {
                $table->dropPrimary('model_has_permissions_permission_model_type_primary');
            });

            Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) {
                $table->unsignedBigInteger('tenant_id')->default(0);
                $table->index('tenant_id', 'model_has_permissions_team_foreign_key_index');
            });

            DB::statement("
                UPDATE {$tableNames['model_has_permissions']} mhp
                INNER JOIN users u ON u.id = mhp.model_id AND mhp.model_type = ?
                SET mhp.tenant_id = COALESCE(u.current_tenant_id, u.tenant_id, 0)
            ", ['App\\Models\\User']);

            $multiTenantPerms = DB::select("
                SELECT DISTINCT mhp.permission_id, mhp.model_id, mhp.model_type, ut.tenant_id
                FROM {$tableNames['model_has_permissions']} mhp
                INNER JOIN user_tenants ut ON ut.user_id = mhp.model_id
                WHERE mhp.model_type = ?
                  AND ut.tenant_id != mhp.tenant_id
                  AND NOT EXISTS (
                      SELECT 1 FROM {$tableNames['model_has_permissions']} mhp2
                      WHERE mhp2.permission_id = mhp.permission_id
                        AND mhp2.model_id = mhp.model_id
                        AND mhp2.model_type = mhp.model_type
                        AND mhp2.tenant_id = ut.tenant_id
                  )
            ", ['App\\Models\\User']);

            foreach ($multiTenantPerms as $row) {
                DB::table($tableNames['model_has_permissions'])->insert([
                    'permission_id' => $row->permission_id,
                    'model_id' => $row->model_id,
                    'model_type' => $row->model_type,
                    'tenant_id' => $row->tenant_id,
                ]);
            }

            Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($tableNames) {
                $table->primary(
                    ['tenant_id', 'permission_id', 'model_id', 'model_type'],
                    'model_has_permissions_permission_model_type_primary'
                );
                $table->foreign('permission_id')
                    ->references('id')
                    ->on($tableNames['permissions'])
                    ->onDelete('cascade');
            });
        }

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    public function down(): void
    {
        $tableNames = config('permission.table_names');

        throw_if(empty($tableNames), Exception::class, 'Error: config/permission.php not loaded.');

        if (Schema::hasColumn($tableNames['model_has_roles'], 'tenant_id')) {
            Schema::table($tableNames['model_has_roles'], function (Blueprint $table) {
                $table->dropForeign(['role_id']);
            });

            Schema::table($tableNames['model_has_roles'], function (Blueprint $table) {
                $table->dropPrimary('model_has_roles_role_model_type_primary');
                $table->dropIndex('model_has_roles_team_foreign_key_index');
            });

            DB::statement("
                DELETE t1 FROM {$tableNames['model_has_roles']} t1
                INNER JOIN {$tableNames['model_has_roles']} t2
                    ON t1.role_id = t2.role_id
                   AND t1.model_id = t2.model_id
                   AND t1.model_type = t2.model_type
                   AND t1.tenant_id > t2.tenant_id
            ");

            Schema::table($tableNames['model_has_roles'], function (Blueprint $table) use ($tableNames) {
                $table->dropColumn('tenant_id');
                $table->primary(
                    ['role_id', 'model_id', 'model_type'],
                    'model_has_roles_role_model_type_primary'
                );
                $table->foreign('role_id')
                    ->references('id')
                    ->on($tableNames['roles'])
                    ->onDelete('cascade');
            });
        }

        if (Schema::hasColumn($tableNames['model_has_permissions'], 'tenant_id')) {
            Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) {
                $table->dropForeign(['permission_id']);
            });

            Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) {
                $table->dropPrimary('model_has_permissions_permission_model_type_primary');
                $table->dropIndex('model_has_permissions_team_foreign_key_index');
            });

            DB::statement("
                DELETE t1 FROM {$tableNames['model_has_permissions']} t1
                INNER JOIN {$tableNames['model_has_permissions']} t2
                    ON t1.permission_id = t2.permission_id
                   AND t1.model_id = t2.model_id
                   AND t1.model_type = t2.model_type
                   AND t1.tenant_id > t2.tenant_id
            ");

            Schema::table($tableNames['model_has_permissions'], function (Blueprint $table) use ($tableNames) {
                $table->dropColumn('tenant_id');
                $table->primary(
                    ['permission_id', 'model_id', 'model_type'],
                    'model_has_permissions_permission_model_type_primary'
                );
                $table->foreign('permission_id')
                    ->references('id')
                    ->on($tableNames['permissions'])
                    ->onDelete('cascade');
            });
        }

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }
};
