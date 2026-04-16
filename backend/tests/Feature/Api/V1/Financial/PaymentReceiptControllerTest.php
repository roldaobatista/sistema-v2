<?php

namespace Tests\Feature\Api\V1\Financial;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AccountReceivable;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentReceiptControllerTest extends TestCase
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

    private function createPayment(int $tenantId): Payment
    {
        $receivable = AccountReceivable::factory()->create(['tenant_id' => $tenantId]);
        $user = User::factory()->create([
            'tenant_id' => $tenantId,
            'current_tenant_id' => $tenantId,
        ]);

        return Payment::factory()->create([
            'tenant_id' => $tenantId,
            'payable_type' => AccountReceivable::class,
            'payable_id' => $receivable->id,
            'received_by' => $user->id,
        ]);
    }

    public function test_index_returns_only_current_tenant_receipts(): void
    {
        // 3 payments do tenant atual
        for ($i = 0; $i < 3; $i++) {
            $this->createPayment($this->tenant->id);
        }

        // 4 payments de outro tenant — NAO podem aparecer
        $otherTenant = Tenant::factory()->create();
        for ($i = 0; $i < 4; $i++) {
            $this->createPayment($otherTenant->id);
        }

        $response = $this->getJson('/api/v1/payment-receipts');

        $response->assertOk()->assertJsonStructure(['data']);

        $data = $response->json('data');
        $this->assertIsArray($data);

        foreach ($data as $receipt) {
            $this->assertEquals(
                $this->tenant->id,
                $receipt['tenant_id'] ?? null,
                'PaymentReceipt de outro tenant vazou na listagem'
            );
        }
    }

    public function test_index_paginates_with_per_page_param(): void
    {
        // Cria 8 payments
        for ($i = 0; $i < 8; $i++) {
            $this->createPayment($this->tenant->id);
        }

        $response = $this->getJson('/api/v1/payment-receipts?per_page=5');

        $response->assertOk()->assertJsonStructure([
            'data',
            'meta' => ['current_page', 'per_page', 'total'],
        ]);

        $this->assertLessThanOrEqual(5, count($response->json('data')));
    }

    public function test_show_returns_payment_receipt(): void
    {
        $payment = $this->createPayment($this->tenant->id);

        $response = $this->getJson("/api/v1/payment-receipts/{$payment->id}");

        $response->assertOk();
    }

    public function test_show_returns_non_success_for_cross_tenant_receipt(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreign = $this->createPayment($otherTenant->id);

        $response = $this->getJson("/api/v1/payment-receipts/{$foreign->id}");

        // Cross-tenant nao pode vazar informacao
        $this->assertNotEquals(200, $response->status(), 'Cross-tenant leak de PaymentReceipt');
        $this->assertContains($response->status(), [403, 404]);
    }
}
