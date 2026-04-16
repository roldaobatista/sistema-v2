<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\EmailLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailLogControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Gate::before(fn () => true);
        $this->withoutMiddleware([
            EnsureTenantScope::class,
            CheckPermission::class,
        ]);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    private function createLog(?int $tenantId = null, string $subject = 'Assunto'): EmailLog
    {
        return EmailLog::create([
            'tenant_id' => $tenantId ?? $this->tenant->id,
            'to' => 'destino@example.com',
            'subject' => $subject,
            'body' => 'Corpo do email',
            'status' => 'sent',
            'sent_at' => now(),
        ]);
    }

    public function test_index_returns_only_current_tenant_logs(): void
    {
        $mine = $this->createLog();

        $otherTenant = Tenant::factory()->create();
        $foreign = $this->createLog($otherTenant->id, 'LEAK assunto');

        $response = $this->getJson('/api/v1/email-logs');

        $response->assertOk();
        $json = json_encode($response->json());
        $this->assertStringNotContainsString('LEAK assunto', $json);
    }

    public function test_show_returns_log(): void
    {
        $log = $this->createLog();

        $response = $this->getJson("/api/v1/email-logs/{$log->id}");

        $response->assertOk();
    }

    public function test_show_rejects_cross_tenant_log(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreign = $this->createLog($otherTenant->id, 'Foreign');

        $response = $this->getJson("/api/v1/email-logs/{$foreign->id}");

        $this->assertContains($response->status(), [403, 404]);
    }
}
