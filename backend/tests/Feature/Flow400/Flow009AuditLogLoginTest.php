<?php

namespace Tests\Feature\Flow400;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Fluxo 9: Audit Log — ações de login registradas com IP e timestamp.
 */
class Flow009AuditLogLoginTest extends TestCase
{
    public function test_fluxo9_login_registra_audit_log(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'email' => 'user@flow9.test',
            'password' => Hash::make('password'),
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
        ]);
        $user->tenants()->sync([$tenant->id => ['is_default' => true]]);

        $this->postJson('/api/v1/login', ['email' => 'user@flow9.test', 'password' => 'password'])->assertOk();

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'login',
        ]);
        $log = AuditLog::where('action', 'login')->latest()->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('user@flow9.test', $log->description ?? '');
    }
}
