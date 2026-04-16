<?php

use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\TenantSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Migrate company_logo_url from system_settings (global) to tenant_settings (per-tenant).
     */
    public function up(): void
    {
        if (! Schema::hasTable('system_settings') || ! Schema::hasTable('tenant_settings')) {
            return;
        }

        $logoSetting = SystemSetting::where('key', 'company_logo_url')
            ->whereNotNull('value')
            ->where('value', '!=', '')
            ->first();

        if (! $logoSetting) {
            return;
        }

        $logoUrl = $logoSetting->value;

        // Copy the logo URL to all active tenants that don't already have one
        $tenantIds = Tenant::pluck('id');

        foreach ($tenantIds as $tenantId) {
            $existing = TenantSetting::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenantId)
                ->where('key', 'company_logo_url')
                ->first();

            if (! $existing) {
                TenantSetting::withoutGlobalScope('tenant')->create([
                    'tenant_id' => $tenantId,
                    'key' => 'company_logo_url',
                    'value_json' => $logoUrl,
                ]);
            }
        }
    }

    /**
     * Reverse: move logo back to system_settings (no data loss — keep tenant_settings entries).
     */
    public function down(): void
    {
        // No destructive rollback — tenant_settings entries are kept.
    }
};
