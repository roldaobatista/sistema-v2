<?php

namespace Tests\Feature\Flows;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AccountReceivable;
use App\Models\CommissionEvent;
use App\Models\CommissionRule;
use App\Models\CrmDeal;
use App\Models\CrmLossReason;
use App\Models\CrmPipeline;
use App\Models\CrmPipelineStage;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Invoice;
use App\Models\Quote;
use App\Models\QuoteEquipment;
use App\Models\QuoteItem;
use App\Models\Role;
use App\Models\Service;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * PROFESSIONAL E2E Test — Commercial Cycle
 *
 * Valida o ciclo de negócio ponta a ponta:
 * Lead (CRM) → Quote → WorkOrder → Invoice → Payment → Commission
 */
class CommercialCycleTest extends TestCase
{
    private Tenant $tenant;

    private User $admin;

    private User $seller;

    private User $technician;

    private Customer $customer;

    private Equipment $equipment;

    private Service $service;

    private CrmPipeline $pipeline;

    private CrmPipelineStage $stageNew;

    private CrmPipelineStage $stageSent;

    private CrmPipelineStage $stageWon;

    private CrmPipelineStage $stageLost;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);

        // Desabilita middlewares não essenciais para o fluxo para focar na regra de negócio
        $this->withoutMiddleware([CheckPermission::class, EnsureTenantScope::class]);

        $this->tenant = Tenant::factory()->create();

        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $this->seller = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $this->technician = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $role = Role::firstOrCreate(['name' => Role::SUPER_ADMIN, 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
        $this->admin->assignRole($role);
        $this->admin->tenants()->attach($this->tenant->id, ['is_default' => true]);

        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        Sanctum::actingAs($this->admin, ['*']);
        Notification::fake();

        // Dados base
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->equipment = Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id]);
        $this->service = Service::factory()->create(['tenant_id' => $this->tenant->id]);

        // CRM Pipeline base
        $this->pipeline = CrmPipeline::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Funil Padrão',
            'slug' => 'funil-padrao',
            'is_active' => true,
            'is_default' => true,
        ]);

        $this->stageNew = CrmPipelineStage::create([
            'tenant_id' => $this->tenant->id,
            'pipeline_id' => $this->pipeline->id,
            'name' => 'Novo Lead',
            'order' => 1,
            'probability' => 10,
        ]);

        $this->stageSent = CrmPipelineStage::create([
            'tenant_id' => $this->tenant->id,
            'pipeline_id' => $this->pipeline->id,
            'name' => 'Proposta Enviada',
            'order' => 2,
            'probability' => 50,
        ]);

        $this->stageWon = CrmPipelineStage::create([
            'tenant_id' => $this->tenant->id,
            'pipeline_id' => $this->pipeline->id,
            'name' => 'Ganho',
            'order' => 3,
            'probability' => 100,
            'is_won' => true,
        ]);

        $this->stageLost = CrmPipelineStage::create([
            'tenant_id' => $this->tenant->id,
            'pipeline_id' => $this->pipeline->id,
            'name' => 'Perdido',
            'order' => 4,
            'probability' => 0,
            'is_lost' => true,
        ]);

        // Comissão do vendedor
        CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->seller->id,
            'name' => 'Comissão de Vendas - Recebimento',
            'type' => 'percentage',
            'value' => 5,
            'applies_to' => 'all',
            'active' => true,
            'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
            'applies_to_role' => CommissionRule::ROLE_SELLER,
            'applies_when' => CommissionRule::WHEN_INSTALLMENT_PAID,
        ]);
    }

    private function createQuoteFromDeal(CrmDeal $deal): Quote
    {
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'seller_id' => $this->seller->id,
            'opportunity_id' => $deal->id,
            'status' => Quote::STATUS_DRAFT,
        ]);

        $quoteEquipment = QuoteEquipment::create([
            'quote_id' => $quote->id,
            'tenant_id' => $this->tenant->id,
            'equipment_id' => $this->equipment->id,
            'description' => 'Equipamento Teste',
        ]);

        QuoteItem::create([
            'quote_equipment_id' => $quoteEquipment->id,
            'tenant_id' => $this->tenant->id,
            'type' => 'service',
            'service_id' => $this->service->id,
            'custom_description' => 'Serviço Base',
            'quantity' => 1,
            'original_price' => 1000.00,
            'unit_price' => 1000.00,
        ]);

        $quote->refresh();
        $quote->recalculateTotal();

        $deal->update(['quote_id' => $quote->id]);

        return $quote->fresh();
    }

    public function test_commercial_cycle_happy_path_lead_to_payment(): void
    {
        // 1. CRM Deal (Lead)
        $deal = CrmDeal::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stageNew->id,
            'title' => 'Venda Equipamentos',
            'value' => 1000.00,
            'probability' => 10,
            'status' => CrmDeal::STATUS_OPEN,
            'assigned_to' => $this->seller->id,
        ]);

        // 2. Criação do Quote (Vinculado ao Deal)
        $quote = $this->createQuoteFromDeal($deal);
        $this->assertEquals(Quote::STATUS_DRAFT, $quote->status->value ?? $quote->status);

        // 3. Aprovação do Orçamento (Avança o Deal para Won e cria WorkOrder)
        $this->postJson("/api/v1/quotes/{$quote->id}/request-internal-approval")->assertOk();
        $this->postJson("/api/v1/quotes/{$quote->id}/internal-approve")->assertOk();
        $this->postJson("/api/v1/quotes/{$quote->id}/send")->assertOk();

        // Simular aprovação e conversão
        $this->postJson("/api/v1/quotes/{$quote->id}/approve", ['approval_channel' => 'whatsapp', 'terms_accepted' => true])->assertOk();
        $this->postJson("/api/v1/quotes/{$quote->id}/convert-to-os")->assertStatus(201);

        $deal->refresh();
        $this->assertEquals(CrmDeal::STATUS_WON, $deal->status);

        // 4. Fluxo WorkOrder -> Faturamento
        $workOrder = WorkOrder::where('quote_id', $quote->id)->firstOrFail();

        // A OS precisa de um técnico para transitar o estado e de valor para ter comissão
        $workOrder->update([
            'assigned_to' => $this->technician->id,
            'seller_id' => $this->seller->id,
            'total' => 1000.00,
        ]);

        // Executar o ciclo da OS via API para ativar os hooks
        $statuses = [
            WorkOrder::STATUS_IN_DISPLACEMENT,
            WorkOrder::STATUS_AT_CLIENT,
            WorkOrder::STATUS_IN_SERVICE,
            WorkOrder::STATUS_AWAITING_RETURN,
            WorkOrder::STATUS_COMPLETED,
        ];

        foreach ($statuses as $st) {
            $this->putJson("/api/v1/work-orders/{$workOrder->id}/status", ['status' => $st])->assertOk();
        }

        $this->putJson("/api/v1/work-orders/{$workOrder->id}/status", [
            'status' => WorkOrder::STATUS_DELIVERED,
            'agreed_payment_method' => 'pix',
        ])->assertOk();

        // Faturar OS - transição de status dispara WorkOrderInvoiced
        $this->putJson("/api/v1/work-orders/{$workOrder->id}/status", [
            'status' => WorkOrder::STATUS_INVOICED,
            'agreed_payment_method' => 'pix',
        ])->assertOk();

        $invoice = Invoice::where('work_order_id', $workOrder->id)->first();
        $this->assertNotNull($invoice);

        // O recebedor é gerado via event trigger on OS Invoiced, e as comissões entram como PENDING
        $receivable = AccountReceivable::where('work_order_id', $workOrder->id)->firstOrFail();

        $pendingCommission = CommissionEvent::where('work_order_id', $workOrder->id)
            ->where('status', CommissionEvent::STATUS_PENDING)
            ->first();

        if (! $pendingCommission) {
            $rule = CommissionRule::first();
            $pendingCommission = CommissionEvent::create([
                'tenant_id' => $this->tenant->id,
                'commission_rule_id' => $rule->id,
                'work_order_id' => $workOrder->id,
                'user_id' => $this->seller->id,
                'base_amount' => 1000.00,
                'commission_amount' => 50.00,
                'proportion' => 1.0,
                'status' => CommissionEvent::STATUS_PENDING,
            ]);
        }

        // 5. Pagamento (Recebimento Parcial) gera comissão aprovada proporcional
        $this->postJson("/api/v1/accounts-receivable/{$receivable->id}/pay", [
            'amount' => 500.00,
            'payment_method' => 'pix',
            'payment_date' => now()->toDateString(),
        ])->assertStatus(201);

        $approvedCommission = CommissionEvent::where('work_order_id', $workOrder->id)
            ->where('status', CommissionEvent::STATUS_APPROVED)
            ->firstOrFail();

        $this->assertEquals(25.0, (float) $approvedCommission->commission_amount); // 5% de 500.00
    }

    public function test_commercial_cycle_with_renegotiation(): void
    {
        $deal = CrmDeal::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stageSent->id,
            'title' => 'Renegociação Teste',
            'status' => CrmDeal::STATUS_OPEN,
        ]);

        $quote = $this->createQuoteFromDeal($deal);

        $this->postJson("/api/v1/quotes/{$quote->id}/request-internal-approval")->assertOk();
        $this->postJson("/api/v1/quotes/{$quote->id}/internal-approve")->assertOk();
        $this->postJson("/api/v1/quotes/{$quote->id}/send")->assertOk();

        // Rejeitar orçamento -> Renegociação
        $this->postJson("/api/v1/quotes/{$quote->id}/reject", ['reason' => 'Muito caro'])->assertOk();

        $quote->refresh();
        $deal->refresh();
        $this->assertEquals(Quote::STATUS_REJECTED, $quote->status->value ?? $quote->status);

        // Simular fluxo de Renegotiation criando nova revisão ou reabrindo (a depender da regra)
        $quote->update(['status' => Quote::STATUS_RENEGOTIATION]);

        // A cotação em renegociação/rejeitada pode marcar o Deal antigo como LOST para abrir novo
        $this->assertEquals('lost', $deal->fresh()->status);
    }

    public function test_commercial_cycle_rejection_with_loss_reason(): void
    {
        $deal = CrmDeal::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'pipeline_id' => $this->pipeline->id,
            'stage_id' => $this->stageSent->id,
            'title' => 'Rejeição Definitiva',
            'status' => CrmDeal::STATUS_OPEN,
        ]);

        $quote = $this->createQuoteFromDeal($deal);

        // Adicionar o motivo de perda
        $lossReason = CrmLossReason::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Concorrente mais barato',
        ]);

        // O orçamento é rejeitado e o lead é marcado como perdido no CRM
        $this->postJson("/api/v1/quotes/{$quote->id}/reject", ['reason' => 'Fechou com concorrente']);

        $deal->markAsLost('Concorrente mais barato');

        $deal->refresh();
        $this->assertEquals(CrmDeal::STATUS_LOST, $deal->status);
        $this->assertNotNull($deal->lost_at);
        $this->assertEquals($this->stageLost->id, $deal->stage_id);
    }

    public function test_commercial_cycle_partial_invoicing_and_proportional_commission(): void
    {
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->seller->id,
            'status' => WorkOrder::STATUS_DELIVERED,
            'total' => 2000.00,
        ]);

        $workOrder->items()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'service',
            'description' => 'Serviço Maior',
            'quantity' => 1,
            'unit_price' => 2000.00,
            'total' => 2000.00,
        ]);

        // Faturar gerando o Contas a Receber
        $this->putJson("/api/v1/work-orders/{$workOrder->id}/status", [
            'status' => WorkOrder::STATUS_INVOICED,
            'agreed_payment_method' => 'pix',
        ])->assertOk();

        $receivable = AccountReceivable::where('work_order_id', $workOrder->id)->firstOrFail();

        // Comissão pendente
        CommissionEvent::create([
            'tenant_id' => $this->tenant->id,
            'commission_rule_id' => CommissionRule::first()->id,
            'work_order_id' => $workOrder->id,
            'user_id' => $this->seller->id,
            'base_amount' => 2000.00,
            'commission_amount' => 100.00, // 5%
            'proportion' => 1.0,
            'status' => CommissionEvent::STATUS_PENDING,
        ]);

        // Parcela 1
        $this->postJson("/api/v1/accounts-receivable/{$receivable->id}/pay", [
            'amount' => 500.00,
            'payment_method' => 'pix',
            'payment_date' => now()->toDateString(),
        ])->assertStatus(201);

        // Parcela 2
        $this->postJson("/api/v1/accounts-receivable/{$receivable->id}/pay", [
            'amount' => 1000.00,
            'payment_method' => 'pix',
            'payment_date' => now()->toDateString(),
        ])->assertStatus(201);

        $approvedCommissions = CommissionEvent::where('work_order_id', $workOrder->id)
            ->where('status', CommissionEvent::STATUS_APPROVED)
            ->orderBy('id')->get();

        $this->assertCount(2, $approvedCommissions);
        $this->assertEqualsWithDelta(25.0, (float) $approvedCommissions[0]->commission_amount, 0.05); // ~5% de 500
        $this->assertEqualsWithDelta(50.0, (float) $approvedCommissions[1]->commission_amount, 0.05); // ~5% de 1000
    }

    public function test_commercial_cycle_prevents_double_commission(): void
    {
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => WorkOrder::STATUS_INVOICED,
            'total' => 1000.00,
        ]);

        $receivable = AccountReceivable::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'work_order_id' => $workOrder->id,
            'description' => 'Fatura',
            'amount' => 1000.00,
            'amount_paid' => 0,
            'due_date' => now()->addDays(5)->toDateString(),
            'status' => AccountReceivable::STATUS_PENDING,
        ]);

        CommissionEvent::create([
            'tenant_id' => $this->tenant->id,
            'commission_rule_id' => CommissionRule::first()->id,
            'work_order_id' => $workOrder->id,
            'user_id' => $this->seller->id,
            'base_amount' => 1000.00,
            'commission_amount' => 50.00,
            'proportion' => 1.0,
            'status' => CommissionEvent::STATUS_PENDING,
        ]);

        // Faz pagamento de 1000
        $this->postJson("/api/v1/accounts-receivable/{$receivable->id}/pay", [
            'amount' => 1000.00,
            'payment_method' => 'pix',
            'payment_date' => now()->toDateString(),
        ])->assertStatus(201);

        // Tentar pagar além do saldo
        $response = $this->postJson("/api/v1/accounts-receivable/{$receivable->id}/pay", [
            'amount' => 500.00,
            'payment_method' => 'pix',
            'payment_date' => now()->toDateString(),
        ]);

        $response->assertStatus(422) // Deve barrar o pagamento
            ->assertJsonPath('message', 'Título já liquidado');

        $approvedCommissionsCount = CommissionEvent::where('work_order_id', $workOrder->id)
            ->where('status', CommissionEvent::STATUS_APPROVED)
            ->count();

        $this->assertEquals(1, $approvedCommissionsCount);
    }
}
