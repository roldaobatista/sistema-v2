<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Skill;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SkillsControllerTest extends TestCase
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

    private function createSkill(?int $tenantId = null, string $name = 'Calibração'): Skill
    {
        return Skill::create([
            'tenant_id' => $tenantId ?? $this->tenant->id,
            'name' => $name,
            'category' => 'tecnica',
            'description' => 'Skill de teste',
        ]);
    }

    public function test_index_returns_only_current_tenant_skills(): void
    {
        $mine = $this->createSkill();

        $otherTenant = Tenant::factory()->create();
        $foreign = $this->createSkill($otherTenant->id, 'Foreign');

        $response = $this->getJson('/api/v1/hr/skills');

        $response->assertOk();
        $rows = $response->json('data.data') ?? $response->json('data');
        $ids = collect($rows)->pluck('id')->all();
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/hr/skills', []);

        $response->assertStatus(422);
    }

    public function test_store_creates_skill(): void
    {
        $response = $this->postJson('/api/v1/hr/skills', [
            'name' => 'ISO 17025',
            'category' => 'tecnica',
            'description' => 'Conhecimento em norma ISO 17025',
        ]);

        $this->assertContains($response->status(), [200, 201]);
        $this->assertDatabaseHas('skills', [
            'tenant_id' => $this->tenant->id,
            'name' => 'ISO 17025',
        ]);
    }

    public function test_matrix_returns_structure(): void
    {
        $response = $this->getJson('/api/v1/hr/skills-matrix');

        $response->assertOk();
    }
}
