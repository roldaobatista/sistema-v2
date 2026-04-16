<?php

namespace Tests\Feature\Api\V1\Journey;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TechnicianCertificationControllerTest extends TestCase
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

    public function test_store_rejects_cross_tenant_user(): void
    {
        $otherUser = $this->createForeignUser();

        $response = $this->postJson('/api/v1/journey/certifications', [
            'user_id' => $otherUser->id,
            'type' => 'nr10',
            'name' => 'NR-10 Segurança em Instalações Elétricas',
            'number' => 'CERT-12345',
            'issued_at' => now()->subMonth()->toDateString(),
            'expires_at' => now()->addYear()->toDateString(),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);

        $this->assertDatabaseMissing('technician_certifications', [
            'tenant_id' => $this->tenant->id,
            'user_id' => $otherUser->id,
            'type' => 'nr10',
        ]);
    }

    public function test_check_eligibility_rejects_cross_tenant_user(): void
    {
        $otherUser = $this->createForeignUser();

        $response = $this->postJson('/api/v1/journey/certifications/check-eligibility', [
            'user_id' => $otherUser->id,
            'service_type' => 'eletrica',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }

    private function createForeignUser(): User
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        $otherUser->tenants()->attach($otherTenant->id, ['is_default' => true]);

        return $otherUser;
    }
}
