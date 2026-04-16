<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    /**
     * Rename all 'central.*' permissions to 'agenda.*' in the permissions table.
     */
    public function up(): void
    {
        DB::table('permissions')
            ->where('name', 'like', 'central.%')
            ->get()
            ->each(function ($permission) {
                DB::table('permissions')
                    ->where('id', $permission->id)
                    ->update([
                        'name' => str_replace('central.', 'agenda.', $permission->name),
                    ]);
            });

        // Clear Spatie permission cache
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    /**
     * Reverse: rename 'agenda.*' back to 'central.*'
     */
    public function down(): void
    {
        DB::table('permissions')
            ->where('name', 'like', 'agenda.%')
            ->get()
            ->each(function ($permission) {
                DB::table('permissions')
                    ->where('id', $permission->id)
                    ->update([
                        'name' => str_replace('agenda.', 'central.', $permission->name),
                    ]);
            });

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
