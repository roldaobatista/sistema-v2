<?php

namespace Tests\Feature\Api\V1\Quality;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\CorrectiveAction;
use App\Models\CustomerComplaint;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CorrectiveActionControllerTest extends TestCase
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
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    private function createAction(array $overrides = []): CorrectiveAction
    {
        return CorrectiveAction::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'type' => 'corrective',
            'source' => 'internal',
            'sourceable_type' => null,
            'sourceable_id' => null,
            'nonconformity_description' => 'Falha genérica identificada',
            'status' => 'open',
            'responsible_id' => $this->user->id,
        ], $overrides));
    }

    public function test_index_returns_paginated_actions(): void
    {
        $this->createAction(['nonconformity_description' => 'Ação 1']);
        $this->createAction(['nonconformity_description' => 'Ação 2']);

        $response = $this->getJson('/api/v1/quality/corrective-actions');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta']);
        $this->assertGreaterThanOrEqual(2, count($response->json('data')));
    }

    public function test_index_filters_by_status(): void
    {
        $this->createAction(['status' => 'open']);
        $this->createAction(['status' => 'completed', 'completed_at' => now()]);

        $response = $this->getJson('/api/v1/quality/corrective-actions?status=completed');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('completed', $data[0]['status']);
    }

    public function test_index_respects_tenant_isolation(): void
    {
        $this->createAction(['nonconformity_description' => 'Minha CAPA']);

        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        CorrectiveAction::create([ // assuming BelongsToTenant allows this without global scope disabled since we don't have withoutGlobalScopes
            'tenant_id' => $otherTenant->id,
            'type' => 'corrective',
            'source' => 'internal',
            'nonconformity_description' => 'CAPA de outro tenant',
            'status' => 'open',
        ]);

        $response = $this->getJson('/api/v1/quality/corrective-actions');

        $response->assertOk();
        $titles = collect($response->json('data'))->pluck('nonconformity_description')->toArray();
        $this->assertContains('Minha CAPA', $titles);
        $this->assertNotContains('CAPA de outro tenant', $titles);
    }

    public function test_store_creates_internal_corrective_action(): void
    {
        $response = $this->postJson('/api/v1/quality/corrective-actions', [
            'type' => 'corrective',
            'source' => 'internal',
            'nonconformity_description' => 'Foi constatado um erro interno no envio de e-mails.',
            'action_plan' => 'Revisar a configuração SMTP.',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.source', 'internal')
            ->assertJsonPath('data.nonconformity_description', 'Foi constatado um erro interno no envio de e-mails.');
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/quality/corrective-actions', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type', 'source', 'nonconformity_description']);
    }

    public function test_store_validates_morph_mapping_for_complaints(): void
    {
        $complaint = CustomerComplaint::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->postJson('/api/v1/quality/corrective-actions', [
            'type' => 'corrective',
            'source' => 'complaint',
            'sourceable_type' => CustomerComplaint::class,
            'sourceable_id' => $complaint->id,
            'nonconformity_description' => 'Cliente reclamou do atraso no laudo.',
        ]);

        $response->assertStatus(201);
    }

    public function test_update_modifies_action(): void
    {
        $action = $this->createAction(['status' => 'open']);

        $response = $this->putJson("/api/v1/quality/corrective-actions/{$action->id}", [
            'status' => 'in_progress',
            'action_plan' => 'Novo plano de ação',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.action_plan', 'Novo plano de ação');
    }

    public function test_update_auto_sets_completed_at(): void
    {
        $action = $this->createAction(['status' => 'in_progress']);

        $response = $this->putJson("/api/v1/quality/corrective-actions/{$action->id}", [
            'status' => 'completed',
            'verification_notes' => 'Tudo verificado e OK.',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'completed');
        $this->assertNotNull($response->json('data.completed_at'));
    }

    public function test_destroy_deletes_action(): void
    {
        $action = $this->createAction();

        $response = $this->deleteJson("/api/v1/quality/corrective-actions/{$action->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('corrective_actions', ['id' => $action->id]);
    }

    public function test_destroy_prevents_deleting_completed_status(): void
    {
        $action = $this->createAction(['status' => 'completed']);

        $response = $this->deleteJson("/api/v1/quality/corrective-actions/{$action->id}");

        $response->assertStatus(422);
        $this->assertDatabaseHas('corrective_actions', ['id' => $action->id]);
    }

    public function test_unauthenticated_user_gets_401(): void
    {
        Sanctum::actingAs(new User, []);
        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/api/v1/quality/corrective-actions');

        $response->assertUnauthorized();
    }
}
