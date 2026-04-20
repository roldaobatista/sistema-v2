<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuditLogTest extends TestCase
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
            'is_active' => true,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_index_returns_only_tenant_logs(): void
    {
        // Log do tenant atual
        AuditLog::forceCreate([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'action' => 'created',
            'description' => 'Log do tenant atual',
            'created_at' => now(),
        ]);

        // Log de outro tenant
        $otherTenant = Tenant::factory()->create();
        AuditLog::forceCreate([
            'tenant_id' => $otherTenant->id,
            'user_id' => User::factory()->create(['tenant_id' => $otherTenant->id])->id,
            'action' => 'created',
            'description' => 'Log de outro tenant',
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/audit-logs');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['description' => 'Log do tenant atual'])
            ->assertJsonMissing(['description' => 'Log de outro tenant']);
    }

    public function test_show_returns_log_details_with_diff(): void
    {
        $log = AuditLog::forceCreate([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'action' => 'updated',
            'description' => 'Atualização',
            'old_values' => ['name' => 'Antigo'],
            'new_values' => ['name' => 'Novo'],
            'created_at' => now(),
        ]);

        $response = $this->getJson("/api/v1/audit-logs/{$log->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $log->id)
            ->assertJsonPath('data.diff.0.field', 'name')
            ->assertJsonPath('data.diff.0.old', 'Antigo')
            ->assertJsonPath('data.diff.0.new', 'Novo');
    }

    public function test_show_fails_for_other_tenant_log(): void
    {
        $otherTenant = Tenant::factory()->create();
        $log = AuditLog::forceCreate([
            'tenant_id' => $otherTenant->id,
            'user_id' => User::factory()->create(['tenant_id' => $otherTenant->id])->id,
            'action' => 'created',
            'description' => 'Log alheio',
            'created_at' => now(),
        ]);

        $response = $this->getJson("/api/v1/audit-logs/{$log->id}");

        $response->assertNotFound();
    }

    public function test_filters_work_correctly(): void
    {
        AuditLog::forceCreate([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'action' => 'created',
            'description' => 'Busca1',
            'created_at' => now()->subDay(),
        ]);

        AuditLog::forceCreate([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'action' => 'updated',
            'description' => 'Busca2',
            'created_at' => now(),
        ]);

        // Filtro por action
        $response = $this->getJson('/api/v1/audit-logs?action=created');
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['description' => 'Busca1']);

        // Filtro por search
        $response = $this->getJson('/api/v1/audit-logs?search=Busca2');
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonFragment(['description' => 'Busca2']);
    }

    public function test_export_generates_csv(): void
    {
        AuditLog::forceCreate([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'action' => 'created',
            'description' => 'CSV Export Test',
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/audit-logs/export');

        $response->assertOk();
        $this->assertTrue($response->headers->contains('content-type', 'text/csv; charset=UTF-8'));

        $content = $response->streamedContent();
        $this->assertStringContainsString('CSV Export Test', $content);
        $this->assertStringContainsString('Data;Usuário;Ação', $content);
    }

    public function test_index_validation_fails_for_invalid_date_format(): void
    {
        $response = $this->getJson('/api/v1/audit-logs?from=invalid-date');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['from']);
    }

    public function test_export_validation_fails_for_invalid_date_format(): void
    {
        $response = $this->postJson('/api/v1/audit-logs/export', [
            'from' => '2023-13-45',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['from']);
    }
}
