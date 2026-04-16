<?php

namespace Tests\Feature\Api\V1\Crm;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\CrmSequence;
use App\Models\CrmSequenceStep;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrmSequenceControllerTest extends TestCase
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

    private function createSequence(?int $tenantId = null, string $name = 'Cadência padrão'): CrmSequence
    {
        $sequence = CrmSequence::create([
            'tenant_id' => $tenantId ?? $this->tenant->id,
            'name' => $name,
            'description' => 'descrição',
            'status' => 'active',
            'total_steps' => 1,
            'created_by' => $this->user->id,
        ]);

        CrmSequenceStep::create([
            'tenant_id' => $tenantId ?? $this->tenant->id,
            'sequence_id' => $sequence->id,
            'step_order' => 1,
            'delay_days' => 0,
            'channel' => 'email',
            'action_type' => 'send_message',
            'subject' => 'Olá',
            'body' => 'Corpo da mensagem',
        ]);

        return $sequence;
    }

    public function test_sequences_returns_only_current_tenant(): void
    {
        $mine = $this->createSequence();

        $otherTenant = Tenant::factory()->create();
        $foreign = $this->createSequence($otherTenant->id, 'Foreign');

        $response = $this->getJson('/api/v1/crm-features/sequences');

        $response->assertOk()->assertJsonStructure(['data']);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_store_sequence_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/crm-features/sequences', []);

        $response->assertStatus(422);
    }

    public function test_store_sequence_creates_with_steps(): void
    {
        $response = $this->postJson('/api/v1/crm-features/sequences', [
            'name' => 'Nurture Leads',
            'description' => 'Sequência de nurturing',
            'steps' => [
                [
                    'step_order' => 1,
                    'delay_days' => 0,
                    'channel' => 'email',
                    'action_type' => 'send_message',
                    'subject' => 'Boas-vindas',
                    'body' => 'Olá',
                ],
                [
                    'step_order' => 2,
                    'delay_days' => 3,
                    'channel' => 'email',
                    'action_type' => 'send_message',
                    'subject' => 'Follow-up',
                    'body' => 'Tudo bem?',
                ],
            ],
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('crm_sequences', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Nurture Leads',
            'total_steps' => 2,
            'created_by' => $this->user->id,
        ]);
    }

    public function test_show_sequence_returns_details(): void
    {
        $sequence = $this->createSequence();

        $response = $this->getJson("/api/v1/crm-features/sequences/{$sequence->id}");

        $response->assertOk();
    }

    public function test_destroy_sequence_removes_sequence(): void
    {
        $sequence = $this->createSequence();

        $response = $this->deleteJson("/api/v1/crm-features/sequences/{$sequence->id}");

        $response->assertOk();
        $this->assertNotNull($sequence->fresh()->deleted_at);
    }
}
