<?php

namespace Tests\Unit\Models;

use App\Models\AgendaItem;
use App\Models\AgendaItemComment;
use App\Models\AgendaItemHistory;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Tests\TestCase;

/**
 * Testes de regressao para as relacoes de AgendaItemHistory e AgendaItemComment.
 *
 * Motivacao: em 2026-04-10 foi descoberto schema drift historico em producao
 * onde as tabelas `central_item_history` e `central_item_comments` tinham
 * coluna `central_item_id` ao inves de `agenda_item_id`, enquanto os Models
 * Laravel usam `agenda_item_id`. Esse drift passou despercebido porque nao
 * havia teste exercitando a relacao `.item()` dessas models.
 *
 * A migration `2026_04_09_100000_add_tenant_id_to_central_item_history_and_comments`
 * corrigiu o drift e adicionou tenant_id. Este teste garante que a relacao
 * continua funcionando e que qualquer rename silencioso futuro sera detectado.
 */
class AgendaItemHistoryAndCommentRelationTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private AgendaItem $item;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);

        $this->actingAs($this->user);

        $this->item = AgendaItem::factory()->create([
            'tenant_id' => $this->tenant->id,
            'titulo' => 'Item raiz para regressao de relacao',
        ]);
    }

    // ═══ AgendaItemHistory ═══

    public function test_history_pode_ser_criado_com_fk_agenda_item_id(): void
    {
        $history = AgendaItemHistory::create([
            'tenant_id' => $this->tenant->id,
            'agenda_item_id' => $this->item->id,
            'user_id' => $this->user->id,
            'action' => 'status_changed',
            'from_value' => 'ABERTO',
            'to_value' => 'EM_ANDAMENTO',
        ]);

        $this->assertNotNull($history->id);
        $this->assertSame($this->item->id, $history->agenda_item_id);
        $this->assertSame($this->tenant->id, $history->tenant_id);
    }

    public function test_history_item_relation_retorna_agenda_item_correto(): void
    {
        $history = AgendaItemHistory::create([
            'tenant_id' => $this->tenant->id,
            'agenda_item_id' => $this->item->id,
            'user_id' => $this->user->id,
            'action' => 'created',
        ]);

        $related = $history->item;

        $this->assertInstanceOf(AgendaItem::class, $related);
        $this->assertSame($this->item->id, $related->id);
        $this->assertSame('Item raiz para regressao de relacao', $related->titulo);
    }

    public function test_history_item_relation_usa_fk_agenda_item_id_no_sql(): void
    {
        $history = AgendaItemHistory::make(['agenda_item_id' => $this->item->id]);

        $relation = $history->item();

        // belongsTo->toSql() consulta a tabela pai ("central_items" -> id = ?), portanto
        // a FK local nao aparece no SQL. A forma correta de detectar schema drift e
        // inspecionar o nome da FK configurada no relationship.
        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertSame(
            'agenda_item_id',
            $relation->getForeignKeyName(),
            'Relacao item() de AgendaItemHistory deve usar agenda_item_id como FK'
        );
        $this->assertNotSame(
            'central_item_id',
            $relation->getForeignKeyName(),
            'Relacao item() NAO pode usar central_item_id (schema drift historico)'
        );
    }

    public function test_history_respeita_belongs_to_tenant_scope(): void
    {
        $outroTenant = Tenant::factory()->create();
        $outroUser = User::factory()->create([
            'tenant_id' => $outroTenant->id,
            'current_tenant_id' => $outroTenant->id,
        ]);
        $outroItem = AgendaItem::withoutGlobalScopes()->create([
            'tenant_id' => $outroTenant->id,
            'tipo' => 'TASK',
            'titulo' => 'Item de outro tenant',
            'responsavel_user_id' => $outroUser->id,
            'criado_por_user_id' => $outroUser->id,
            'status' => 'ABERTO',
            'prioridade' => 'MEDIA',
            'origem' => 'MANUAL',
            'visibilidade' => 'EQUIPE',
        ]);

        AgendaItemHistory::withoutGlobalScopes()->create([
            'tenant_id' => $outroTenant->id,
            'agenda_item_id' => $outroItem->id,
            'action' => 'created',
        ]);

        AgendaItemHistory::create([
            'tenant_id' => $this->tenant->id,
            'agenda_item_id' => $this->item->id,
            'action' => 'created',
        ]);

        // Scope deve filtrar apenas pelo tenant corrente
        $this->assertSame(1, AgendaItemHistory::count(), 'BelongsToTenant scope deve filtrar por tenant corrente');
    }

    // ═══ AgendaItemComment ═══

    public function test_comment_pode_ser_criado_com_fk_agenda_item_id(): void
    {
        $comment = AgendaItemComment::create([
            'tenant_id' => $this->tenant->id,
            'agenda_item_id' => $this->item->id,
            'user_id' => $this->user->id,
            'body' => 'Comentario de regressao',
        ]);

        $this->assertNotNull($comment->id);
        $this->assertSame($this->item->id, $comment->agenda_item_id);
        $this->assertSame($this->tenant->id, $comment->tenant_id);
    }

    public function test_comment_item_relation_retorna_agenda_item_correto(): void
    {
        $comment = AgendaItemComment::create([
            'tenant_id' => $this->tenant->id,
            'agenda_item_id' => $this->item->id,
            'user_id' => $this->user->id,
            'body' => 'Outro comentario',
        ]);

        $related = $comment->item;

        $this->assertInstanceOf(AgendaItem::class, $related);
        $this->assertSame($this->item->id, $related->id);
    }

    public function test_comment_item_relation_usa_fk_agenda_item_id_no_sql(): void
    {
        $comment = AgendaItemComment::make(['agenda_item_id' => $this->item->id]);

        $relation = $comment->item();

        // Ver nota em test_history_item_relation_usa_fk_agenda_item_id_no_sql:
        // belongsTo->toSql() consulta a tabela pai, portanto a FK local nao aparece
        // no SQL. A deteccao de drift e feita via getForeignKeyName().
        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertSame(
            'agenda_item_id',
            $relation->getForeignKeyName(),
            'Relacao item() de AgendaItemComment deve usar agenda_item_id como FK'
        );
        $this->assertNotSame(
            'central_item_id',
            $relation->getForeignKeyName(),
            'Relacao item() NAO pode usar central_item_id (schema drift historico)'
        );
    }

    public function test_comment_respeita_belongs_to_tenant_scope(): void
    {
        $outroTenant = Tenant::factory()->create();
        $outroUser = User::factory()->create([
            'tenant_id' => $outroTenant->id,
            'current_tenant_id' => $outroTenant->id,
        ]);
        $outroItem = AgendaItem::withoutGlobalScopes()->create([
            'tenant_id' => $outroTenant->id,
            'tipo' => 'TASK',
            'titulo' => 'Item de outro tenant',
            'responsavel_user_id' => $outroUser->id,
            'criado_por_user_id' => $outroUser->id,
            'status' => 'ABERTO',
            'prioridade' => 'MEDIA',
            'origem' => 'MANUAL',
            'visibilidade' => 'EQUIPE',
        ]);

        AgendaItemComment::withoutGlobalScopes()->create([
            'tenant_id' => $outroTenant->id,
            'agenda_item_id' => $outroItem->id,
            'user_id' => $outroUser->id,
            'body' => 'Comentario de outro tenant',
        ]);

        AgendaItemComment::create([
            'tenant_id' => $this->tenant->id,
            'agenda_item_id' => $this->item->id,
            'user_id' => $this->user->id,
            'body' => 'Comentario do meu tenant',
        ]);

        $this->assertSame(1, AgendaItemComment::count(), 'BelongsToTenant scope deve filtrar por tenant corrente');
    }

    // ═══ Contrato de schema (detecta drift) ═══

    public function test_schema_contract_history_table_tem_colunas_corretas(): void
    {
        $this->assertTrue(
            \Schema::hasColumn('central_item_history', 'agenda_item_id'),
            'Tabela central_item_history DEVE ter coluna agenda_item_id'
        );
        $this->assertFalse(
            \Schema::hasColumn('central_item_history', 'central_item_id'),
            'Tabela central_item_history NAO DEVE ter coluna central_item_id (drift)'
        );
        $this->assertTrue(
            \Schema::hasColumn('central_item_history', 'tenant_id'),
            'Tabela central_item_history DEVE ter coluna tenant_id (multi-tenant)'
        );
    }

    public function test_schema_contract_comments_table_tem_colunas_corretas(): void
    {
        $this->assertTrue(
            \Schema::hasColumn('central_item_comments', 'agenda_item_id'),
            'Tabela central_item_comments DEVE ter coluna agenda_item_id'
        );
        $this->assertFalse(
            \Schema::hasColumn('central_item_comments', 'central_item_id'),
            'Tabela central_item_comments NAO DEVE ter coluna central_item_id (drift)'
        );
        $this->assertTrue(
            \Schema::hasColumn('central_item_comments', 'tenant_id'),
            'Tabela central_item_comments DEVE ter coluna tenant_id (multi-tenant)'
        );
    }
}
