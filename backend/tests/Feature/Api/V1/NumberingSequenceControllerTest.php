<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\NumberingSequence;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class NumberingSequenceControllerTest extends TestCase
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

    private function createSequence(?int $tenantId = null, string $entity = 'work_order'): NumberingSequence
    {
        return NumberingSequence::create([
            'tenant_id' => $tenantId ?? $this->tenant->id,
            'entity' => $entity,
            'prefix' => 'OS',
            'next_number' => 1,
            'padding' => 6,
        ]);
    }

    public function test_index_returns_only_current_tenant(): void
    {
        $mine = $this->createSequence();

        $otherTenant = Tenant::factory()->create();
        $foreign = $this->createSequence($otherTenant->id, 'quote');

        $response = $this->getJson('/api/v1/numbering-sequences');

        $response->assertOk()->assertJsonStructure(['data']);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_update_modifies_next_number(): void
    {
        $sequence = $this->createSequence();

        $response = $this->putJson("/api/v1/numbering-sequences/{$sequence->id}", [
            'prefix' => 'OS-NEW',
            'next_number' => 1000,
            'padding' => 8,
        ]);

        $this->assertContains($response->status(), [200, 201]);
    }

    public function test_preview_returns_formatted_number(): void
    {
        $sequence = $this->createSequence();

        $response = $this->getJson("/api/v1/numbering-sequences/{$sequence->id}/preview");

        $response->assertOk();
    }

    public function test_update_rejects_cross_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreign = $this->createSequence($otherTenant->id);

        $response = $this->putJson("/api/v1/numbering-sequences/{$foreign->id}", [
            'prefix' => 'HACK',
            'next_number' => 1,
            'padding' => 6,
        ]);

        $this->assertContains($response->status(), [403, 404]);
    }
}
