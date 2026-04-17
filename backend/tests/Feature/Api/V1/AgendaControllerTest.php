<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AgendaItem;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AgendaControllerTest extends TestCase
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

    private function createAgendaItem(?int $tenantId = null, string $title = 'Tarefa'): AgendaItem
    {
        return AgendaItem::create([
            'tenant_id' => $tenantId ?? $this->tenant->id,
            'type' => 'tarefa',
            'origin' => 'MANUAL',
            'title' => $title,
            'short_description' => 'Descrição',
            'assignee_user_id' => $this->user->id,
            'priority' => 'medium',
            'visibility' => 'team',
            'created_by_user_id' => $this->user->id,
            'status' => 'open',
        ]);
    }

    public function test_index_returns_agenda_items(): void
    {
        $this->createAgendaItem();

        $response = $this->getJson('/api/v1/agenda');

        $response->assertOk()->assertJsonStructure(['data']);
    }

    public function test_index_returns_only_current_tenant(): void
    {
        $mine = $this->createAgendaItem();

        $otherTenant = Tenant::factory()->create();
        $foreign = $this->createAgendaItem($otherTenant->id, 'Foreign');

        $response = $this->getJson('/api/v1/agenda');

        $response->assertOk();
        $data = $response->json('data');
        $rows = is_array($data) && isset($data['data']) ? $data['data'] : $data;
        $ids = collect($rows)->pluck('id')->all();
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/agenda', []);

        $response->assertStatus(422);
    }

    public function test_constants_returns_metadata(): void
    {
        $response = $this->getJson('/api/v1/agenda/constants');

        $response->assertOk();
    }

    public function test_summary_returns_aggregated(): void
    {
        $this->createAgendaItem();

        $response = $this->getJson('/api/v1/agenda/summary');

        $response->assertOk();
    }
}
