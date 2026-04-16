<?php

namespace Tests\Feature;

use App\Enums\AgendaItemPriority;
use App\Enums\AgendaItemStatus;
use App\Enums\AgendaItemType;
use App\Models\AgendaItem;
use App\Models\AgendaItemWatcher;
use App\Models\AgendaRule;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CentralTest extends TestCase
{
    private User $user;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);

        // Setup Tenant
        $this->tenant = Tenant::factory()->create();

        // Setup user
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        // Mock current tenant
        app()->instance('current_tenant_id', $this->tenant->id);

        // Give user all needed permissions via Spatie
        setPermissionsTeamId($this->tenant->id);
        $perms = [
            'agenda.item.view', 'agenda.create.task', 'agenda.close.self',
            'agenda.assign', 'agenda.manage.rules', 'agenda.manage.kpis',
            'agenda.item.view_all',
        ];
        foreach ($perms as $p) {
            Permission::findOrCreate($p, 'web');
        }
        $this->user->givePermissionTo($perms);

        // Authenticate via Sanctum (for API guard)
        Sanctum::actingAs($this->user, ['*']);

        // Also authenticate via web guard for auth() helper
        $this->actingAs($this->user, 'web');
    }

    public function test_can_list_central_items()
    {
        // Create an item so the listing is not empty
        AgendaItem::create([
            'tenant_id' => $this->tenant->id,
            'criado_por_user_id' => $this->user->id,
            'responsavel_user_id' => $this->user->id,
            'tipo' => AgendaItemType::TAREFA,
            'titulo' => 'List Test Item',
            'status' => AgendaItemStatus::ABERTO,
        ]);

        $response = $this->getJson('/api/v1/agenda/items');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);

        $data = $response->json('data');
        $items = is_array($data) && isset($data['data']) ? $data['data'] : $data;
        $this->assertNotEmpty($items);
        $titles = collect($items)->pluck('titulo');
        $this->assertTrue($titles->contains('List Test Item'));
    }

    public function test_can_create_central_item()
    {
        $payload = [
            'tipo' => AgendaItemType::TAREFA->value,
            'titulo' => 'Test Task',
            'descricao_curta' => 'Short description',
            'prioridade' => AgendaItemPriority::ALTA->value,
        ];

        $response = $this->postJson('/api/v1/agenda/items', $payload);

        $response->assertStatus(201)
            ->assertJsonPath('data.titulo', 'Test Task');

        $this->assertDatabaseHas('central_items', [
            'titulo' => 'Test Task',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_create_item_for_another_user_sends_notification(): void
    {
        $assignee = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/agenda/items', [
            'tipo' => AgendaItemType::TAREFA->value,
            'titulo' => 'Item para atribuicao',
            'responsavel_user_id' => $assignee->id,
        ]);

        $response->assertCreated();

        $itemId = (int) $response->json('data.id');

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $this->tenant->id,
            'user_id' => $assignee->id,
            'type' => 'agenda_item_assigned',
            'notifiable_type' => AgendaItem::class,
            'notifiable_id' => $itemId,
        ]);
    }

    public function test_can_update_central_item()
    {
        $item = AgendaItem::create([
            'tenant_id' => $this->tenant->id,
            'criado_por_user_id' => $this->user->id,
            'responsavel_user_id' => $this->user->id,
            'tipo' => AgendaItemType::TAREFA,
            'titulo' => 'Old Title',
            'status' => AgendaItemStatus::ABERTO,
            'prioridade' => AgendaItemPriority::MEDIA,
        ]);

        $payload = ['titulo' => 'New Title', 'prioridade' => AgendaItemPriority::URGENTE->value];

        $response = $this->patchJson("/api/v1/agenda/items/{$item->id}", $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.titulo', 'New Title');

        $this->assertDatabaseHas('central_items', ['id' => $item->id, 'prioridade' => AgendaItemPriority::URGENTE]);
    }

    public function test_can_comment_on_item()
    {
        $item = AgendaItem::create([
            'tenant_id' => $this->tenant->id,
            'criado_por_user_id' => $this->user->id,
            'responsavel_user_id' => $this->user->id,
            'tipo' => AgendaItemType::TAREFA,
            'titulo' => 'Item for Comment',
            'status' => AgendaItemStatus::ABERTO,
        ]);

        $response = $this->postJson("/api/v1/agenda/items/{$item->id}/comments", [
            'body' => 'This is a comment',
        ]);

        $response->assertStatus(201);

        $this->assertDatabaseHas('central_item_comments', [
            'agenda_item_id' => $item->id,
            'body' => 'This is a comment',
            'user_id' => $this->user->id,
        ]);
    }

    public function test_summary_endpoint()
    {
        AgendaItem::create([
            'tenant_id' => $this->tenant->id,
            'criado_por_user_id' => $this->user->id,
            'responsavel_user_id' => $this->user->id,
            'tipo' => AgendaItemType::TAREFA,
            'titulo' => 'Task 1',
            'status' => AgendaItemStatus::ABERTO,
            'due_at' => now()->startOfDay(),
        ]);

        $response = $this->getJson('/api/v1/agenda/summary');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['hoje', 'atrasadas', 'sem_prazo', 'total_aberto']]);
    }

    public function test_agenda_endpoints_support_legacy_watcher_foreign_key_column(): void
    {
        $renamedFromAgendaToCentral = false;

        if (Schema::hasColumn('central_item_watchers', 'agenda_item_id')) {
            Schema::table('central_item_watchers', function (Blueprint $table) {
                $table->renameColumn('agenda_item_id', 'central_item_id');
            });
            $renamedFromAgendaToCentral = true;
        }

        AgendaItemWatcher::resetItemForeignKeyCache();

        try {
            $item = AgendaItem::create([
                'tenant_id' => $this->tenant->id,
                'criado_por_user_id' => $this->user->id,
                'responsavel_user_id' => User::factory()->create([
                    'tenant_id' => $this->tenant->id,
                    'current_tenant_id' => $this->tenant->id,
                    'is_active' => true,
                ])->id,
                'tipo' => AgendaItemType::TAREFA,
                'titulo' => 'Item visivel por watcher legado',
                'status' => AgendaItemStatus::ABERTO,
                'visibilidade' => 'private',
            ]);

            AgendaItemWatcher::query()->insert([
                'central_item_id' => $item->id,
                'user_id' => $this->user->id,
                'tenant_id' => $this->tenant->id,
                'role' => 'watcher',
                'added_by_type' => 'manual',
                'notify_status_change' => true,
                'notify_comment' => true,
                'notify_due_date' => true,
                'notify_assignment' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->getJson('/api/v1/agenda/items')
                ->assertOk()
                ->assertJsonFragment(['titulo' => 'Item visivel por watcher legado']);

            $this->getJson('/api/v1/agenda/summary')
                ->assertOk()
                ->assertJsonPath('data.seguindo', 1);
        } finally {
            if ($renamedFromAgendaToCentral && Schema::hasColumn('central_item_watchers', 'central_item_id')) {
                Schema::table('central_item_watchers', function (Blueprint $table) {
                    $table->renameColumn('central_item_id', 'agenda_item_id');
                });
            }
            AgendaItemWatcher::resetItemForeignKeyCache();
        }
    }

    public function test_dashboard_endpoints(): void
    {
        AgendaItem::create([
            'tenant_id' => $this->tenant->id,
            'criado_por_user_id' => $this->user->id,
            'responsavel_user_id' => $this->user->id,
            'tipo' => AgendaItemType::OS,
            'titulo' => 'OS atrasada',
            'status' => AgendaItemStatus::ABERTO,
            'prioridade' => AgendaItemPriority::ALTA,
            'due_at' => now()->subDay(),
        ]);

        $this->getJson('/api/v1/agenda/kpis')
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'total',
                    'abertas',
                    'em_andamento',
                    'concluidas',
                    'atrasadas',
                    'taxa_conclusao',
                    'tempo_medio_horas',
                ],
            ]);

        $this->getJson('/api/v1/agenda/workload')
            ->assertOk()
            ->assertJsonStructure(['data']);

        $this->postJson('/api/v1/agenda/items', [
            'tipo' => AgendaItemType::TAREFA->value,
            'titulo' => 'Urgente para workload',
            'prioridade' => AgendaItemPriority::URGENTE->value,
            'responsavel_user_id' => $this->user->id,
        ])->assertCreated();

        $this->getJson('/api/v1/agenda/workload')
            ->assertOk()
            ->assertJsonFragment(['urgentes' => 1]);

        $this->getJson('/api/v1/agenda/overdue-by-team')
            ->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_assign_endpoint_sends_notification_to_new_assignee(): void
    {
        $newAssignee = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $item = AgendaItem::create([
            'tenant_id' => $this->tenant->id,
            'criado_por_user_id' => $this->user->id,
            'responsavel_user_id' => $this->user->id,
            'tipo' => AgendaItemType::TAREFA,
            'titulo' => 'Item para reatribuicao',
            'status' => AgendaItemStatus::ABERTO,
        ]);

        $this->postJson("/api/v1/agenda/items/{$item->id}/assign", [
            'user_id' => $newAssignee->id,
        ])->assertOk();

        $this->assertDatabaseHas('notifications', [
            'tenant_id' => $this->tenant->id,
            'user_id' => $newAssignee->id,
            'type' => 'agenda_item_assigned',
            'notifiable_type' => AgendaItem::class,
            'notifiable_id' => $item->id,
        ]);

        $this->assertDatabaseHas('central_item_history', [
            'agenda_item_id' => $item->id,
            'action' => 'assigned',
            'to_value' => (string) $newAssignee->id,
        ]);
    }

    public function test_can_manage_central_rules(): void
    {
        $createResponse = $this->postJson('/api/v1/agenda/rules', [
            'nome' => 'Priorizar OS urgentes',
            'descricao' => 'Regra MVP',
            'ativo' => true,
            'tipo_item' => 'work_order',
            'prioridade_minima' => 'high',
            'acao_tipo' => 'set_priority',
            'acao_config' => ['prioridade' => 'urgent'],
        ]);

        $createResponse->assertCreated()
            ->assertJsonPath('data.nome', 'Priorizar OS urgentes')
            ->assertJsonPath('data.tipo_item', 'work_order')
            ->assertJsonPath('data.prioridade_minima', 'high');

        $ruleId = (int) $createResponse->json('data.id');

        $this->assertDatabaseHas('central_rules', [
            'id' => $ruleId,
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'tipo_item' => 'work_order',
            'prioridade_minima' => 'high',
        ]);

        $this->getJson('/api/v1/agenda/rules')
            ->assertOk()
            ->assertJsonPath('data.0.id', $ruleId);

        $this->patchJson("/api/v1/agenda/rules/{$ruleId}", [
            'nome' => 'Priorizar OS críticas',
            'ativo' => false,
        ])->assertOk()
            ->assertJsonPath('data.nome', 'Priorizar OS críticas')
            ->assertJsonPath('data.ativo', false);

        $this->deleteJson("/api/v1/agenda/rules/{$ruleId}")
            ->assertNoContent();

        $this->assertDatabaseMissing('central_rules', ['id' => $ruleId]);
    }

    public function test_rule_is_applied_on_item_creation(): void
    {
        AgendaRule::create([
            'tenant_id' => $this->tenant->id,
            'nome' => 'Elevar prioridade de tarefas',
            'ativo' => true,
            'tipo_item' => AgendaItemType::TAREFA->value,
            'prioridade_minima' => AgendaItemPriority::BAIXA->value,
            'acao_tipo' => 'set_priority',
            'acao_config' => ['prioridade' => 'urgent'],
            'created_by' => $this->user->id,
        ]);

        $response = $this->postJson('/api/v1/agenda/items', [
            'tipo' => AgendaItemType::TAREFA->value,
            'titulo' => 'Item com automação',
            'prioridade' => AgendaItemPriority::BAIXA->value,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.prioridade', AgendaItemPriority::URGENTE->value);
    }
}
