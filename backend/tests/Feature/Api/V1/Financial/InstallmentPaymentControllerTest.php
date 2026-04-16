<?php

namespace Tests\Feature\Api\V1\Financial;

use App\Http\Middleware\CheckPermission;
use App\Models\AccountReceivable;
use App\Models\AccountReceivableInstallment;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Payment\Contracts\PaymentGatewayInterface;
use App\Services\Payment\PaymentResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class InstallmentPaymentControllerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    private AccountReceivable $receivable;

    private AccountReceivableInstallment $installment;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();
        Gate::before(fn () => true);
        $this->withoutMiddleware([CheckPermission::class]);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        $this->user->assignRole('admin');
        Sanctum::actingAs($this->user, ['*']);

        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->receivable = AccountReceivable::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
            'amount' => 300.00,
        ]);

        $this->installment = AccountReceivableInstallment::create([
            'tenant_id' => $this->tenant->id,
            'account_receivable_id' => $this->receivable->id,
            'installment_number' => 1,
            'due_date' => now()->addDays(10),
            'amount' => 150.00,
            'paid_amount' => 0,
            'status' => 'pending',
        ]);
    }

    // ── GENERATE BOLETO ──

    public function test_generate_boleto_success(): void
    {
        $response = $this->postJson("/api/v1/financial/receivables/{$this->installment->id}/generate-boleto");

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'external_id',
                    'status',
                    'boleto_url',
                    'boleto_barcode',
                    'due_date',
                    'installment_id',
                ],
            ]);

        $this->assertStringStartsWith('PAY-BOL-', $response->json('data.external_id'));
        $this->assertEquals('pending', $response->json('data.status'));
        $this->assertNotNull($response->json('data.boleto_url'));
        $this->assertNotNull($response->json('data.boleto_barcode'));

        // Verify installment was updated with PSP data
        $this->installment->refresh();
        $this->assertNotNull($this->installment->psp_external_id);
        $this->assertEquals('pending', $this->installment->psp_status);
        $this->assertNotNull($this->installment->psp_boleto_url);
    }

    // ── GENERATE PIX ──

    public function test_generate_pix_success(): void
    {
        $response = $this->postJson("/api/v1/financial/receivables/{$this->installment->id}/generate-pix");

        $response->assertStatus(201)
            ->assertJsonStructure([
                'data' => [
                    'external_id',
                    'status',
                    'qr_code',
                    'qr_code_base64',
                    'pix_copy_paste',
                    'due_date',
                    'installment_id',
                ],
            ]);

        $this->assertStringStartsWith('PAY-PIX-', $response->json('data.external_id'));
        $this->assertNotNull($response->json('data.qr_code'));
        $this->assertNotNull($response->json('data.pix_copy_paste'));

        // Verify installment was updated with PSP data
        $this->installment->refresh();
        $this->assertNotNull($this->installment->psp_external_id);
        $this->assertNotNull($this->installment->psp_pix_qr_code);
    }

    // ── PAID INSTALLMENT REJECTED ──

    public function test_generate_boleto_fails_for_paid_installment(): void
    {
        $this->installment->update(['status' => 'paid', 'paid_at' => now()]);

        $response = $this->postJson("/api/v1/financial/receivables/{$this->installment->id}/generate-boleto");

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Esta parcela já está paga.']);
    }

    public function test_generate_pix_fails_for_paid_installment(): void
    {
        $this->installment->update(['status' => 'paid', 'paid_at' => now()]);

        $response = $this->postJson("/api/v1/financial/receivables/{$this->installment->id}/generate-pix");

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Esta parcela já está paga.']);
    }

    // ── CANCELLED INSTALLMENT REJECTED ──

    public function test_generate_boleto_fails_for_cancelled_installment(): void
    {
        $this->installment->update(['status' => 'cancelled']);

        $response = $this->postJson("/api/v1/financial/receivables/{$this->installment->id}/generate-boleto");

        $response->assertStatus(422)
            ->assertJsonFragment(['message' => 'Esta parcela está cancelada.']);
    }

    // ── CROSS-TENANT ISOLATION ──

    public function test_generate_boleto_returns_404_for_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherReceivable = AccountReceivable::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => $otherCustomer->id,
            'created_by' => $this->user->id,
        ]);
        $otherInstallment = AccountReceivableInstallment::create([
            'tenant_id' => $otherTenant->id,
            'account_receivable_id' => $otherReceivable->id,
            'installment_number' => 1,
            'due_date' => now()->addDays(10),
            'amount' => 100.00,
            'paid_amount' => 0,
            'status' => 'pending',
        ]);

        $response = $this->postJson("/api/v1/financial/receivables/{$otherInstallment->id}/generate-boleto");

        // Should be 404 due to tenant scope
        $response->assertNotFound();
    }

    // ── CHECK STATUS ──

    public function test_check_status_success(): void
    {
        $this->installment->update(['psp_external_id' => 'PAY-PIX-20260402-abc123']);

        $response = $this->getJson("/api/v1/financial/receivables/{$this->installment->id}/payment-status");

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['external_id', 'status', 'success', 'installment_id'],
            ]);

        $this->assertTrue($response->json('data.success'));
        $this->assertEquals('confirmed', $response->json('data.status'));
    }

    public function test_check_status_returns_404_when_no_psp_charge(): void
    {
        $response = $this->getJson("/api/v1/financial/receivables/{$this->installment->id}/payment-status");

        $response->assertStatus(404)
            ->assertJsonFragment(['message' => 'Nenhuma cobrança PSP vinculada a esta parcela.']);
    }

    // ── NONEXISTENT INSTALLMENT ──

    public function test_generate_boleto_returns_404_for_nonexistent_installment(): void
    {
        $response = $this->postJson('/api/v1/financial/receivables/999999/generate-boleto');

        $response->assertNotFound();
    }

    // ── MOCK PROVIDER CONTRACT ──

    public function test_generate_boleto_uses_gateway_service(): void
    {
        $mockGateway = \Mockery::mock(PaymentGatewayInterface::class);
        $mockGateway->shouldReceive('createBoletoCharge')
            ->once()
            ->andReturn(PaymentResult::ok([
                'external_id' => 'MOCK-BOL-001',
                'status' => 'pending',
                'boleto_url' => 'https://mock.com/boleto.pdf',
                'boleto_barcode' => '12345.67890',
                'due_date' => '2026-04-10',
            ]));

        $this->app->instance(PaymentGatewayInterface::class, $mockGateway);

        $response = $this->postJson("/api/v1/financial/receivables/{$this->installment->id}/generate-boleto");

        $response->assertStatus(201);
        $this->assertEquals('MOCK-BOL-001', $response->json('data.external_id'));
    }
}
