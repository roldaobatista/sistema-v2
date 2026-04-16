<?php

namespace Tests\Feature\Api\V1\Iam;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserControllerTest extends TestCase
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

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_index_returns_only_current_tenant_users(): void
    {
        // UserController::index usa whereHas('tenants') — users precisam
        // ser attached ao tenant via pivot user_tenants, nao apenas tenant_id.

        // Attach o user atual ao tenant
        $this->user->tenants()->syncWithoutDetaching([$this->tenant->id]);

        // 3 usuarios adicionais do tenant atual
        $currentTenantUsers = User::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        foreach ($currentTenantUsers as $u) {
            $u->tenants()->syncWithoutDetaching([$this->tenant->id]);
        }

        // 5 usuarios de OUTRO tenant (nao podem vazar)
        $otherTenant = Tenant::factory()->create();
        $otherTenantUsers = User::factory()->count(5)->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        foreach ($otherTenantUsers as $u) {
            $u->tenants()->syncWithoutDetaching([$otherTenant->id]);
        }

        $response = $this->getJson('/api/v1/users');

        $response->assertOk()
            ->assertJsonStructure(['data']);

        $data = $response->json('data');
        $this->assertIsArray($data);

        // Deve conter os 4 users do tenant atual (1 setUp + 3 criados)
        $this->assertCount(4, $data, 'Listagem deve conter exatamente os 4 users do tenant atual');

        // Nenhum dos ids de users do OUTRO tenant pode aparecer
        $responseUserIds = collect($data)->pluck('id')->all();
        foreach ($otherTenantUsers as $foreign) {
            $this->assertNotContains(
                $foreign->id,
                $responseUserIds,
                "User {$foreign->id} de outro tenant vazou — CROSS-TENANT P0"
            );
        }
    }

    public function test_show_returns_404_for_cross_tenant_user(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreignUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);

        $response = $this->getJson("/api/v1/users/{$foreignUser->id}");

        // Nunca 200 — cross-tenant nao pode vazar detalhes de user
        $this->assertNotEquals(
            200,
            $response->status(),
            'Dados de user de outro tenant expostos via show — P0 leak'
        );
        $this->assertContains($response->status(), [403, 404]);
    }

    public function test_store_validates_required_email(): void
    {
        $response = $this->postJson('/api/v1/users', [
            'name' => 'Sem email',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_store_rejects_duplicate_email_in_same_tenant(): void
    {
        User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'duplicado@example.com',
        ]);

        $response = $this->postJson('/api/v1/users', [
            'name' => 'Duplicado',
            'email' => 'duplicado@example.com',
            'password' => 'Senha12345!',
            'password_confirmation' => 'Senha12345!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_stats_endpoint_returns_data(): void
    {
        User::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/users/stats');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }
}
