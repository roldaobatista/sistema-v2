<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $tableNames = config('permission.table_names');

        if (empty($tableNames)) {
            throw new Exception('Error: config/permission.php not loaded. Run [php artisan config:clear] and try again.');
        }

        Schema::table($tableNames['roles'], function (Blueprint $table) use ($tableNames) {
            if (! Schema::hasColumn($tableNames['roles'], 'tenant_id')) {
                $table->foreignId('tenant_id')->nullable()
                    ->constrained('tenants')->nullOnDelete();
            }
        });

        // Remove unique constraint antiga e adiciona nova com tenant_id
        // Wrapped separately to handle cases where index names differ (Teams mode vs standard)
        try {
            Schema::table($tableNames['roles'], function (Blueprint $table) {
                $table->dropUnique(['name', 'guard_name']);
            });
        } catch (Throwable $e) {
            Log::info('add_tenant_id_to_roles: dropUnique [name,guard_name] skipped (may not exist in Teams mode)', ['error' => $e->getMessage()]);
        }

        try {
            Schema::table($tableNames['roles'], function (Blueprint $table) {
                $table->unique(['name', 'guard_name', 'tenant_id']);
            });
        } catch (Throwable $e) {
            Log::info('add_tenant_id_to_roles: unique constraint already exists', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableNames = config('permission.table_names');

        if (empty($tableNames)) {
            throw new Exception('Error: config/permission.php not loaded. Run [php artisan config:clear] and try again.');
        }

        Schema::table($tableNames['roles'], function (Blueprint $table) {
            $table->dropUnique(['name', 'guard_name', 'tenant_id']);
            $table->dropConstrainedForeignId('tenant_id');
            $table->unique(['name', 'guard_name']);
        });
    }
};
