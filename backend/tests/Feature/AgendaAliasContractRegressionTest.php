<?php

namespace Tests\Feature;

use App\Enums\AgendaItemPriority;
use App\Enums\AgendaItemStatus;
use App\Enums\AgendaItemType;
use App\Models\AgendaItem;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionsSeeder;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AgendaAliasContractRegressionTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();
        $this->seed(PermissionsSeeder::class);

        $this->tenant = Tenant::factory()->create(['status' => Tenant::STATUS_ACTIVE]);
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);
        $this->user->assignRole('admin');

        $this->setTenantContext($this->tenant->id);
    }

    public function test_agenda_items_alias_uses_paginated_canonical_contract(): void
    {
        AgendaItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by_user_id' => $this->user->id,
            'assignee_user_id' => $this->user->id,
            'tipo' => AgendaItemType::TAREFA,
            'title' => 'Alias paginado',
            'status' => AgendaItemStatus::ABERTO,
        ]);

        $response = $this->actingAs($this->user)->getJson('/api/v1/agenda-items');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    ['id', 'title', 'status', 'tipo'],
                ],
                'meta' => ['current_page', 'per_page'],
                'current_page',
                'per_page',
            ])
            ->assertJsonPath('data.0.title', 'Alias paginado');
    }

    public function test_agenda_items_alias_summary_and_completion_follow_canonical_payload(): void
    {
        $item = AgendaItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by_user_id' => $this->user->id,
            'assignee_user_id' => $this->user->id,
            'tipo' => AgendaItemType::TAREFA,
            'title' => 'Alias completar',
            'priority' => AgendaItemPriority::ALTA,
            'status' => AgendaItemStatus::ABERTO,
        ]);

        $this->actingAs($this->user)
            ->getJson('/api/v1/agenda-items/summary')
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['hoje', 'atrasadas', 'sem_prazo', 'total_aberto', 'abertas', 'urgentes', 'seguindo'],
            ]);

        $this->actingAs($this->user)
            ->putJson("/api/v1/agenda/{$item->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.id', $item->id)
            ->assertJsonPath('data.status', AgendaItemStatus::CONCLUIDO->value);

        $item->refresh();

        $this->assertSame(AgendaItemStatus::CONCLUIDO, $item->status);
        $this->assertNotNull($item->closed_at);
        $this->assertSame($this->user->id, $item->closed_by);
    }

    public function test_agenda_canonical_static_routes_are_not_shadowed_by_legacy_item_alias(): void
    {
        AgendaItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'created_by_user_id' => $this->user->id,
            'assignee_user_id' => $this->user->id,
            'tipo' => AgendaItemType::TAREFA,
            'title' => 'Canonical static route',
            'status' => AgendaItemStatus::ABERTO,
        ]);

        $this->actingAs($this->user)
            ->getJson('/api/v1/agenda/summary')
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['hoje', 'atrasadas', 'sem_prazo', 'total_aberto'],
            ]);

        $this->actingAs($this->user)
            ->getJson('/api/v1/agenda/constants')
            ->assertOk()
            ->assertJsonStructure([
                'data' => ['types', 'statuses', 'priorities'],
            ]);

        $this->actingAs($this->user)
            ->getJson('/api/v1/agenda/items')
            ->assertOk()
            ->assertJsonPath('data.0.title', 'Canonical static route');
    }
}
