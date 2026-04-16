<?php

namespace Tests\Unit\Services;

use App\Contracts\SmsProviderInterface;
use App\Models\AccountReceivable;
use App\Models\CollectionAction;
use App\Models\CollectionRule;
use App\Models\Customer;
use App\Models\Tenant;
use App\Services\ClientNotificationService;
use App\Services\CollectionAutomationService;
use App\Services\WhatsAppService;
use Illuminate\Support\Facades\Mail;
use Mockery;
use Tests\TestCase;

/**
 * Valida a refatoração do CollectionAutomationService para usar
 * `$rule->steps` (JSON array) em vez dos campos inexistentes
 * `days_offset` / `channel` (que geravam erro em produção).
 */
class CollectionAutomationServiceTest extends TestCase
{
    private Tenant $tenant;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        $this->tenant = Tenant::factory()->create();
        $this->setTenantContext($this->tenant->id);
        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => 'cliente@teste.com',
            'phone' => '+5511999999999',
        ]);
    }

    private function makeService(): CollectionAutomationService
    {
        $whatsApp = Mockery::mock(WhatsAppService::class);
        $whatsApp->shouldReceive('sendText')->andReturn(null)->byDefault();

        $notification = Mockery::mock(ClientNotificationService::class);

        $sms = Mockery::mock(SmsProviderInterface::class);
        $sms->shouldReceive('send')->andReturn(true)->byDefault();

        return new CollectionAutomationService($whatsApp, $notification, $sms);
    }

    public function test_returns_zero_when_tenant_has_no_rules(): void
    {
        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => 'pending',
            'due_date' => now(),
        ]);

        $result = $this->makeService()->processForTenant($this->tenant->id);

        $this->assertSame(0, $result['sent']);
        $this->assertSame(0, $result['processed']);
    }

    public function test_ignores_inactive_rules(): void
    {
        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => 'pending',
            'due_date' => now(),
        ]);

        CollectionRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Inativa',
            'is_active' => false,
            'steps' => [['days_offset' => 0, 'channel' => 'email']],
        ]);

        $result = $this->makeService()->processForTenant($this->tenant->id);

        $this->assertSame(0, $result['sent']);
    }

    public function test_fires_action_when_step_offset_matches_today(): void
    {
        $ar = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => 'pending',
            'due_date' => now(), // days_offset=0 dispara HOJE
            'amount' => 150,
        ]);

        $rule = CollectionRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Lembrete D-Day',
            'is_active' => true,
            'steps' => [
                ['days_offset' => 0, 'channel' => 'email'],
            ],
        ]);

        $result = $this->makeService()->processForTenant($this->tenant->id);

        $this->assertSame(1, $result['sent']);
        $this->assertSame(1, $result['processed']);
        $this->assertDatabaseHas('collection_actions', [
            'tenant_id' => $this->tenant->id,
            'account_receivable_id' => $ar->id,
            'collection_rule_id' => $rule->id,
            'step_index' => 0,
            'channel' => 'email',
            'status' => 'sent',
        ]);
    }

    public function test_multiple_steps_in_same_rule_fire_independently(): void
    {
        $ar = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => 'pending',
            'due_date' => now(),
            'amount' => 200,
        ]);

        $rule = CollectionRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Rule com 2 passos hoje',
            'is_active' => true,
            'steps' => [
                ['days_offset' => 0, 'channel' => 'email'],
                ['days_offset' => 0, 'channel' => 'sms'],
            ],
        ]);

        $result = $this->makeService()->processForTenant($this->tenant->id);

        // Ambos os passos são elegíveis e devem disparar independentemente
        $this->assertSame(2, $result['sent']);
        $this->assertSame(2, CollectionAction::where('collection_rule_id', $rule->id)->count());
    }

    public function test_deduplicates_by_step_index_on_rerun(): void
    {
        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => 'pending',
            'due_date' => now(),
            'amount' => 100,
        ]);

        CollectionRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Dedup test',
            'is_active' => true,
            'steps' => [['days_offset' => 0, 'channel' => 'email']],
        ]);

        $service = $this->makeService();

        $first = $service->processForTenant($this->tenant->id);
        $second = $service->processForTenant($this->tenant->id);

        $this->assertSame(1, $first['sent']);
        $this->assertSame(0, $second['sent'], 'Segundo run não pode reprocessar passo já enviado');
    }

    public function test_skips_malformed_steps_without_crashing(): void
    {
        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => 'pending',
            'due_date' => now(),
        ]);

        CollectionRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Rule malformada',
            'is_active' => true,
            'steps' => [
                ['channel' => 'email'], // SEM days_offset → deve ser ignorado
                'string invalido',       // não é array
                ['days_offset' => 0, 'channel' => 'email'], // válido
            ],
        ]);

        $result = $this->makeService()->processForTenant($this->tenant->id);

        $this->assertSame(1, $result['sent'], 'Apenas o step válido deve disparar');
    }

    public function test_ignores_receivables_from_other_tenants(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        AccountReceivable::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'status' => 'pending',
            'due_date' => now(),
        ]);

        CollectionRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Regra tenant atual',
            'is_active' => true,
            'steps' => [['days_offset' => 0, 'channel' => 'email']],
        ]);

        $result = $this->makeService()->processForTenant($this->tenant->id);

        // Nenhum receivable no tenant atual → processed=0
        $this->assertSame(0, $result['processed']);
        $this->assertSame(0, $result['sent']);
    }

    public function test_step_does_not_trigger_when_target_date_is_not_today(): void
    {
        AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => 'pending',
            'due_date' => now()->addDays(30), // Due em 30 dias
            'amount' => 100,
        ]);

        CollectionRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Lembrete 3 dias antes',
            'is_active' => true,
            // days_offset=-3 significa lembrete 3 dias ANTES do due_date.
            // Due em +30d, então o target é +27d — NÃO é hoje, não dispara.
            'steps' => [['days_offset' => -3, 'channel' => 'email']],
        ]);

        $result = $this->makeService()->processForTenant($this->tenant->id);

        $this->assertSame(0, $result['sent']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
