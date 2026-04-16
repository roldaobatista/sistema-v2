<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Adiciona permissões quotes.quote.export e quotes.quote.invoice
 * usando a API do Spatie para compatibilidade com qualquer driver (MySQL, SQLite).
 */
return new class extends Migration
{
    public function up(): void
    {
        $newPermissions = [
            'quotes.quote.export',
            'quotes.quote.invoice',
        ];

        foreach ($newPermissions as $permName) {
            Permission::findOrCreate($permName, 'web');
        }

        app()->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::whereIn('name', [
            'quotes.quote.export',
            'quotes.quote.invoice',
        ])->delete();

        app()->make(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
