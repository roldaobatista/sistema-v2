<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

return new class extends Migration
{
    private array $permissions = [
        'hr.dependent.manage',
        'hr.dependent.view',
        'hr.vacation.manage',
        'hr.vacation.view',
        'hr.hour_bank.manage',
        'hr.hour_bank.view',
        'hr.rescission.approve',
        'hr.rescission.manage',
        'hr.rescission.view',
        'hr.esocial.manage',
        'hr.esocial.view',
        'hr.payroll.manage',
        'hr.payroll.view',
    ];

    public function up(): void
    {
        foreach ($this->permissions as $permission) {
            Permission::firstOrCreate(
                ['name' => $permission, 'guard_name' => 'web']
            );
        }
    }

    public function down(): void
    {
        Permission::whereIn('name', $this->permissions)->delete();
    }
};
