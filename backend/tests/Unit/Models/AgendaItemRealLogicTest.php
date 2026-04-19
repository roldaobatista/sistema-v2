<?php

namespace Tests\Unit\Models;

use App\Enums\AgendaItemPriority;
use App\Enums\AgendaItemStatus;
use App\Enums\AgendaItemType;
use App\Enums\AgendaItemVisibility;
use App\Http\Middleware\CheckPermission;
use App\Models\AgendaItem;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Testes profundos reais do AgendaItem model:
 * scopes (atrasados, hoje, semPrazo, doUsuario, daEquipe, visivelPara),
 * criarDeOrigem(), casts, relationships, soft delete.
 */
class AgendaItemRealLogicTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private User $user2;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([CheckPermission::class]);
        Event::fake();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        $this->user->assignRole('admin');

        $this->user2 = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user2->tenants()->attach($this->tenant->id, ['is_default' => true]);

        $this->actingAs($this->user);
    }

    // ═══ Scopes ═══

    public function test_scope_atrasados_returns_overdue(): void
    {
        AgendaItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => AgendaItemStatus::ABERTO,
            'due_at' => now()->subDays(5),
            'assignee_user_id' => $this->user->id,
        ]);
        $this->assertGreaterThanOrEqual(1, AgendaItem::atrasados()->count());
    }

    public function test_scope_atrasados_excludes_completed(): void
    {
        AgendaItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => AgendaItemStatus::CONCLUIDO,
            'due_at' => now()->subDays(5),
            'assignee_user_id' => $this->user->id,
        ]);
        $count = AgendaItem::atrasados()->where('assignee_user_id', $this->user->id)->count();
        $this->assertEquals(0, $count);
    }

    public function test_scope_atrasados_excludes_cancelled(): void
    {
        AgendaItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => AgendaItemStatus::CANCELADO,
            'due_at' => now()->subDays(5),
            'assignee_user_id' => $this->user->id,
        ]);
        $countCancelled = AgendaItem::atrasados()
            ->where('assignee_user_id', $this->user->id)
            ->where('status', AgendaItemStatus::CANCELADO)
            ->count();
        $this->assertEquals(0, $countCancelled);
    }

    public function test_scope_sem_prazo(): void
    {
        AgendaItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => AgendaItemStatus::ABERTO,
            'due_at' => null,
            'assignee_user_id' => $this->user->id,
        ]);
        $this->assertGreaterThanOrEqual(1, AgendaItem::semPrazo()->count());
    }

    public function test_scope_do_usuario(): void
    {
        AgendaItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'assignee_user_id' => $this->user->id,
        ]);
        AgendaItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'assignee_user_id' => $this->user2->id,
        ]);
        $count = AgendaItem::doUsuario($this->user->id)->count();
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function test_scope_do_usuario_null_returns_empty(): void
    {
        $count = AgendaItem::doUsuario(null)->count();
        $this->assertEquals(0, $count);
    }

    public function test_scope_visivel_para_null_returns_empty(): void
    {
        $count = AgendaItem::visivelPara(null)->count();
        $this->assertEquals(0, $count);
    }

    public function test_scope_visivel_para_includes_responsible(): void
    {
        AgendaItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'assignee_user_id' => $this->user->id,
            'visibility' => AgendaItemVisibility::PRIVADA,
        ]);
        $count = AgendaItem::visivelPara($this->user->id)->count();
        $this->assertGreaterThanOrEqual(1, $count);
    }

    public function test_scope_visivel_para_includes_empresa(): void
    {
        AgendaItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'assignee_user_id' => $this->user2->id,
            'visibility' => AgendaItemVisibility::EMPRESA,
        ]);
        $count = AgendaItem::visivelPara($this->user->id)->count();
        $this->assertGreaterThanOrEqual(1, $count);
    }

    // ═══ criarDeOrigem() ═══

    public function test_criar_de_origem_from_work_order(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
        ]);

        $item = AgendaItem::criarDeOrigem(
            $wo,
            AgendaItemType::ORDEM_SERVICO,
            'OS criada: #'.$wo->id,
            $this->user->id
        );

        $this->assertNotNull($item->id);
        $this->assertEquals($this->tenant->id, $item->tenant_id);
        $this->assertEquals($wo->id, $item->ref_id);
    }

    public function test_criar_de_origem_idempotent(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
        ]);

        $item1 = AgendaItem::criarDeOrigem($wo, AgendaItemType::ORDEM_SERVICO, 'Test');
        $item2 = AgendaItem::criarDeOrigem($wo, AgendaItemType::ORDEM_SERVICO, 'Updated');

        $this->assertEquals($item1->id, $item2->id);
    }

    // ═══ Casts ═══

    public function test_tipo_cast(): void
    {
        $item = AgendaItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => AgendaItemType::TAREFA,
        ]);
        $this->assertInstanceOf(AgendaItemType::class, $item->type);
    }

    public function test_status_cast(): void
    {
        $item = AgendaItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => AgendaItemStatus::ABERTO,
        ]);
        $this->assertInstanceOf(AgendaItemStatus::class, $item->status);
    }

    public function test_prioridade_cast(): void
    {
        $item = AgendaItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'priority' => AgendaItemPriority::ALTA,
        ]);
        $this->assertInstanceOf(AgendaItemPriority::class, $item->priority);
    }

    public function test_tags_cast_array(): void
    {
        $item = AgendaItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'tags' => ['urgente', 'calibração'],
        ]);
        $item->refresh();
        $this->assertIsArray($item->tags);
        $this->assertContains('urgente', $item->tags);
    }

    public function test_due_at_cast_datetime(): void
    {
        $item = AgendaItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'due_at' => '2026-03-15 10:00:00',
        ]);
        $this->assertInstanceOf(Carbon::class, $item->due_at);
    }

    // ═══ Relationships ═══

    public function test_responsavel_relationship(): void
    {
        $item = AgendaItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'assignee_user_id' => $this->user->id,
        ]);
        $this->assertInstanceOf(User::class, $item->responsavel);
        $this->assertEquals($this->user->id, $item->responsavel->id);
    }

    public function test_subtasks_relationship(): void
    {
        $item = AgendaItem::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertCount(0, $item->subtasks);
    }

    public function test_comments_relationship(): void
    {
        $item = AgendaItem::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertCount(0, $item->comments);
    }

    public function test_history_relationship(): void
    {
        $item = AgendaItem::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->assertCount(0, $item->history);
    }

    // ═══ Soft delete ═══

    public function test_soft_deletes(): void
    {
        $item = AgendaItem::factory()->create(['tenant_id' => $this->tenant->id]);
        $item->delete();
        $this->assertSoftDeleted($item);
    }

    // ═══ mapTypeToWatcherEvent ═══

    public function test_registrar_historico(): void
    {
        $item = AgendaItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'assignee_user_id' => $this->user->id,
        ]);

        $history = $item->registrarHistorico(
            'status_changed',
            AgendaItemStatus::ABERTO,
            AgendaItemStatus::EM_ANDAMENTO,
            $this->user->id
        );

        $this->assertNotNull($history->id);
        $this->assertEquals('status_changed', $history->action);
    }
}
