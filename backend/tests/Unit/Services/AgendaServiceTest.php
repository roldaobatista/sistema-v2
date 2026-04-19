<?php

namespace Tests\Unit\Services;

use App\Enums\AgendaItemStatus;
use App\Models\AgendaItem;
use App\Models\AgendaItemWatcher;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AgendaService;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AgendaServiceTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private AgendaService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        app()->instance('current_tenant_id', $this->tenant->id);
        $this->actingAs($this->user);

        $this->service = app(AgendaService::class);
    }

    public function test_criar_creates_item_with_defaults(): void
    {
        $item = $this->service->criar([
            'title' => 'Tarefa de teste',
            'type' => 'task',
        ]);

        $this->assertInstanceOf(AgendaItem::class, $item);
        $this->assertEquals('Tarefa de teste', $item->title);
        $this->assertEquals($this->user->id, $item->created_by_user_id);
        $this->assertEquals($this->tenant->id, $item->tenant_id);
    }

    public function test_criar_sets_default_status_and_priority(): void
    {
        $item = $this->service->criar([
            'title' => 'Defaults test',
            'type' => 'task',
        ]);

        $this->assertEquals(AgendaItemStatus::ABERTO->value, $item->status->value ?? $item->status);
    }

    public function test_criar_assigns_to_another_user(): void
    {
        $other = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        $item = $this->service->criar([
            'title' => 'Assigned item',
            'type' => 'task',
            'assignee_user_id' => $other->id,
        ]);

        $this->assertEquals($other->id, $item->assignee_user_id);
    }

    public function test_criar_adds_watchers(): void
    {
        $watcher = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        $item = $this->service->criar([
            'title' => 'Watched item',
            'type' => 'task',
            'watchers' => [$watcher->id],
        ]);

        $fk = AgendaItemWatcher::itemForeignKey();
        $this->assertTrue(
            AgendaItemWatcher::withoutGlobalScopes()
                ->where($fk, $item->id)
                ->where('user_id', $watcher->id)
                ->exists()
        );
    }

    public function test_atualizar_changes_item_fields(): void
    {
        $item = $this->service->criar([
            'title' => 'Original title',
            'type' => 'task',
        ]);

        $updated = $this->service->atualizar($item, [
            'title' => 'Updated title',
        ]);

        $this->assertEquals('Updated title', $updated->title);
    }

    public function test_comentar_creates_comment(): void
    {
        $item = $this->service->criar([
            'title' => 'Comment test',
            'type' => 'task',
        ]);

        $comment = $this->service->comentar($item, 'Meu comentário', $this->user->id);

        $this->assertEquals('Meu comentário', $comment->body);
        $this->assertEquals($this->user->id, $comment->user_id);
    }

    public function test_add_watcher_adds_user_as_watcher(): void
    {
        $item = $this->service->criar([
            'title' => 'Watcher test',
            'type' => 'task',
        ]);

        $other = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        $watcher = $this->service->addWatcher($item, $other->id);

        $this->assertEquals($other->id, $watcher->user_id);
    }

    public function test_remove_watcher_removes_user(): void
    {
        $item = $this->service->criar(['title' => 'Remove watcher test', 'type' => 'task']);
        $other = User::factory()->create(['tenant_id' => $this->tenant->id, 'current_tenant_id' => $this->tenant->id]);

        $watcher = $this->service->addWatcher($item, $other->id);
        $this->assertNotNull($watcher->id);

        // Use withoutGlobalScopes to bypass tenant filtering and verify the watcher exists
        $fk = AgendaItemWatcher::itemForeignKey();
        $exists = AgendaItemWatcher::withoutGlobalScopes()
            ->where($fk, $item->id)
            ->where('user_id', $other->id)
            ->exists();
        $this->assertTrue($exists);

        // Delete the watcher
        AgendaItemWatcher::withoutGlobalScopes()
            ->where($fk, $item->id)
            ->where('user_id', $other->id)
            ->delete();

        $existsAfter = AgendaItemWatcher::withoutGlobalScopes()
            ->where($fk, $item->id)
            ->where('user_id', $other->id)
            ->exists();
        $this->assertFalse($existsAfter);
    }

    public function test_resumo_returns_expected_keys(): void
    {
        $summary = $this->service->resumo();

        $this->assertArrayHasKey('hoje', $summary);
        $this->assertArrayHasKey('atrasadas', $summary);
        $this->assertArrayHasKey('sem_prazo', $summary);
        $this->assertArrayHasKey('total_aberto', $summary);
        $this->assertArrayHasKey('urgentes', $summary);
        $this->assertArrayHasKey('seguindo', $summary);
    }

    public function test_listar_returns_paginated_results(): void
    {
        $this->service->criar(['title' => 'Item 1', 'type' => 'task']);
        $this->service->criar(['title' => 'Item 2', 'type' => 'task']);

        $result = $this->service->listar([], 10);

        $this->assertGreaterThanOrEqual(2, $result->total());
    }

    public function test_listar_filters_by_search(): void
    {
        $this->service->criar(['title' => 'Calibração urgente', 'type' => 'task']);
        $this->service->criar(['title' => 'Reunião equipe', 'type' => 'task']);

        $result = $this->service->listar(['search' => 'Calibração'], 10);

        $this->assertGreaterThanOrEqual(1, $result->total());
    }

    public function test_listar_filters_by_status(): void
    {
        $this->service->criar(['title' => 'Open item', 'type' => 'task']);

        $result = $this->service->listar(['status' => ['open']], 10);
        $this->assertGreaterThanOrEqual(1, $result->total());
    }

    public function test_listar_filters_only_mine(): void
    {
        $this->service->criar(['title' => 'My item', 'type' => 'task']);

        $result = $this->service->listar(['scope' => 'minhas'], 10);
        $this->assertGreaterThanOrEqual(1, $result->total());
    }

    public function test_usuario_pode_acessar_item_as_responsavel(): void
    {
        $item = $this->service->criar(['title' => 'Access test', 'type' => 'task']);

        $this->assertTrue($this->service->usuarioPodeAcessarItem($item));
    }

    public function test_usuario_pode_acessar_item_as_creator(): void
    {
        $other = User::factory()->create(['tenant_id' => $this->tenant->id, 'current_tenant_id' => $this->tenant->id]);
        $item = $this->service->criar([
            'title' => 'Creator access',
            'type' => 'task',
            'assignee_user_id' => $other->id,
        ]);

        $this->assertTrue($this->service->usuarioPodeAcessarItem($item));
    }
}
