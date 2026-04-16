<?php

namespace Tests\Feature\Api\V1\Lgpd;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\LgpdDpoConfig;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LgpdDpoConfigControllerTest extends TestCase
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

    public function test_show_returns_404_when_dpo_not_configured(): void
    {
        $response = $this->getJson('/api/v1/lgpd/dpo');

        $response->assertStatus(404)
            ->assertJsonPath('message', 'DPO não configurado.');
    }

    public function test_show_returns_config_when_exists(): void
    {
        LgpdDpoConfig::create([
            'tenant_id' => $this->tenant->id,
            'dpo_name' => 'Fulano da Silva',
            'dpo_email' => 'dpo@empresa.com',
            'updated_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/lgpd/dpo');

        $response->assertOk()
            ->assertJsonPath('data.dpo_name', 'Fulano da Silva')
            ->assertJsonPath('data.dpo_email', 'dpo@empresa.com');
    }

    public function test_upsert_validates_required_dpo_name_and_email(): void
    {
        $response = $this->putJson('/api/v1/lgpd/dpo', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['dpo_name', 'dpo_email']);
    }

    public function test_upsert_rejects_invalid_email(): void
    {
        $response = $this->putJson('/api/v1/lgpd/dpo', [
            'dpo_name' => 'Beltrano',
            'dpo_email' => 'not-an-email',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['dpo_email']);
    }

    public function test_upsert_creates_dpo_config_on_first_call(): void
    {
        $this->assertDatabaseMissing('lgpd_dpo_configs', [
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->putJson('/api/v1/lgpd/dpo', [
            'dpo_name' => 'Novo DPO',
            'dpo_email' => 'dpo@novo.com',
            'dpo_phone' => '+55 11 99999-0000',
            'is_public' => true,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('lgpd_dpo_configs', [
            'tenant_id' => $this->tenant->id,
            'dpo_name' => 'Novo DPO',
            'dpo_email' => 'dpo@novo.com',
            'updated_by' => $this->user->id,
        ]);
    }

    public function test_upsert_updates_existing_config(): void
    {
        LgpdDpoConfig::create([
            'tenant_id' => $this->tenant->id,
            'dpo_name' => 'Original',
            'dpo_email' => 'original@test.com',
            'updated_by' => $this->user->id,
        ]);

        $response = $this->putJson('/api/v1/lgpd/dpo', [
            'dpo_name' => 'Atualizado',
            'dpo_email' => 'atualizado@test.com',
        ]);

        $response->assertOk();

        $count = LgpdDpoConfig::where('tenant_id', $this->tenant->id)->count();
        $this->assertSame(1, $count, 'Upsert nao deve criar duplicata');

        $config = LgpdDpoConfig::where('tenant_id', $this->tenant->id)->first();
        $this->assertSame('Atualizado', $config->dpo_name);
    }
}
