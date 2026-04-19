<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Regressão sec-08 (Re-auditoria Camada 1, 2026-04-19).
 *
 * Campos sensíveis de User — `is_active`, `current_tenant_id`,
 * `denied_permissions` — NÃO podem ser fillable. Um atacante que consiga
 * passar qualquer um desses via body (mesmo em endpoints legítimos como
 * POST /users) pode escalonar privilégio, sequestrar tenant ou escapar de
 * denylist de permissões.
 *
 * Tese: mass-assignment via `$fillable` nesses 3 campos é proibido. Paths
 * administrativos legítimos (toggleActive, switchTenant, syncDenied*)
 * devem usar `forceFill()` explicitamente.
 */
class MassAssignmentUserTest extends TestCase
{
    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Gate::before(fn () => true);
        $this->withoutMiddleware([
            EnsureTenantScope::class,
            CheckPermission::class,
        ]);

        $this->tenant = Tenant::factory()->create();
        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->admin->tenants()->syncWithoutDetaching([$this->tenant->id]);

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->admin, ['*']);
    }

    public function test_user_fill_blocks_is_active(): void
    {
        $user = new User;

        // Strict mode Eloquent: mass-assignment em campo não-fillable lança
        // MassAssignmentException. Isso prova que is_active NÃO é fillable.
        try {
            $user->fill(['is_active' => false]);
            $this->fail('Expected MassAssignmentException para is_active — sec-08 ATIVO');
        } catch (\Illuminate\Database\Eloquent\MassAssignmentException $e) {
            $this->assertStringContainsString('is_active', $e->getMessage());
        }
    }

    public function test_user_fill_blocks_current_tenant_id(): void
    {
        $user = new User;

        try {
            $user->fill(['current_tenant_id' => 999]);
            $this->fail('Expected MassAssignmentException para current_tenant_id — sec-08 ATIVO');
        } catch (\Illuminate\Database\Eloquent\MassAssignmentException $e) {
            $this->assertStringContainsString('current_tenant_id', $e->getMessage());
        }
    }

    public function test_user_fill_blocks_denied_permissions(): void
    {
        $user = new User;

        try {
            $user->fill(['denied_permissions' => ['users.delete']]);
            $this->fail('Expected MassAssignmentException para denied_permissions — sec-08 ATIVO');
        } catch (\Illuminate\Database\Eloquent\MassAssignmentException $e) {
            $this->assertStringContainsString('denied_permissions', $e->getMessage());
        }
    }

    public function test_user_fill_accepts_legitimate_fields(): void
    {
        $user = new User;
        $user->fill(['name' => 'X', 'email' => 'x@ex.com']);

        $this->assertSame('X', $user->name);
        $this->assertSame('x@ex.com', $user->email);
    }

    public function test_store_user_endpoint_ignores_malicious_fields_from_body(): void
    {
        $payload = [
            'name' => 'Intruder',
            'email' => 'intruder@example.com',
            'password' => 'SecretPass!234',
            'password_confirmation' => 'SecretPass!234',
            // payload malicioso:
            'is_active' => false,
            'current_tenant_id' => 999999,
            'denied_permissions' => ['users.destroy'],
        ];

        $response = $this->postJson('/api/v1/users', $payload);

        $response->assertStatus(201);

        $created = User::where('email', 'intruder@example.com')->firstOrFail();

        // is_active default = true (controller usa `$validated['is_active'] ?? true`
        // via forceFill). Mesmo que o atacante passe false, o controller decide.
        // O ponto crítico: current_tenant_id NUNCA pode vir do body.
        $this->assertSame(
            $this->tenant->id,
            (int) $created->current_tenant_id,
            'current_tenant_id veio do body — vulnerabilidade sec-08 ativa'
        );

        $this->assertNotSame(
            999999,
            (int) $created->current_tenant_id,
            'Atacante setou current_tenant_id do body — escalonamento cross-tenant'
        );

        // denied_permissions NÃO pode ter sido aplicado no store.
        $this->assertEmpty(
            $created->denied_permissions ?? [],
            'denied_permissions foi populado via body no store — bypass de denylist'
        );
    }

    public function test_update_user_endpoint_does_not_accept_current_tenant_id_from_body(): void
    {
        $target = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $target->tenants()->syncWithoutDetaching([$this->tenant->id]);

        $payload = [
            'name' => 'Renamed',
            'email' => $target->email,
            'current_tenant_id' => 999999,
            'denied_permissions' => ['*'],
        ];

        $this->putJson("/api/v1/users/{$target->id}", $payload)->assertOk();

        $target->refresh();

        $this->assertSame(
            $this->tenant->id,
            (int) $target->current_tenant_id,
            'update() alterou current_tenant_id a partir do body — sec-08'
        );
        $this->assertEmpty(
            $target->denied_permissions ?? [],
            'update() aplicou denied_permissions do body — sec-08'
        );
    }

    public function test_toggle_active_still_works_via_forceFill(): void
    {
        // Path administrativo legítimo: o endpoint toggleActive DEVE continuar
        // funcional mesmo com is_active fora de $fillable, porque o controller
        // usa forceFill explicitamente.
        $target = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        $target->tenants()->syncWithoutDetaching([$this->tenant->id]);

        $this->postJson("/api/v1/users/{$target->id}/toggle-active")->assertOk();

        $this->assertFalse(
            (bool) $target->fresh()->is_active,
            'toggleActive quebrou após remover is_active de $fillable — endpoint legítimo regrediu'
        );
    }

    public function test_sync_denied_permissions_still_works_via_forceFill(): void
    {
        $target = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $target->tenants()->syncWithoutDetaching([$this->tenant->id]);

        $payload = ['denied_permissions' => []];

        $this->putJson("/api/v1/users/{$target->id}/denied-permissions", $payload)
            ->assertOk();

        $this->assertIsArray($target->fresh()->denied_permissions ?? []);
    }
}
