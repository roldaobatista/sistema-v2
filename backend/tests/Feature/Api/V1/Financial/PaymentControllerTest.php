<?php

namespace Tests\Feature\Api\V1\Financial;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;
use App\Models\Payment;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PaymentControllerTest extends TestCase
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

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_index_returns_only_current_tenant_payments(): void
    {
        // Cria payment do tenant atual
        $receivable = AccountReceivable::factory()->create(['tenant_id' => $this->tenant->id]);
        Payment::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'payable_type' => AccountReceivable::class,
            'payable_id' => $receivable->id,
            'received_by' => $this->user->id,
        ]);

        // Cria payment de OUTRO tenant
        $otherTenant = Tenant::factory()->create();
        $otherReceivable = AccountReceivable::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        Payment::factory()->count(4)->create([
            'tenant_id' => $otherTenant->id,
            'payable_type' => AccountReceivable::class,
            'payable_id' => $otherReceivable->id,
            'received_by' => $otherUser->id,
        ]);

        $response = $this->getJson('/api/v1/payments');

        $response->assertOk()->assertJsonStructure(['data']);

        $data = $response->json('data');
        $this->assertIsArray($data);

        foreach ($data as $payment) {
            $this->assertEquals(
                $this->tenant->id,
                $payment['tenant_id'] ?? null,
                'Payment de outro tenant vazou na listagem'
            );
        }
    }

    public function test_index_rejects_invalid_type_alias(): void
    {
        $response = $this->getJson('/api/v1/payments?type=invalido_xyz');

        // Controller retorna 422 via resolvePayableType quando o alias e invalido
        $response->assertStatus(422);
    }

    public function test_index_filters_by_receivable_type(): void
    {
        $receivable = AccountReceivable::factory()->create(['tenant_id' => $this->tenant->id]);
        $payable = AccountPayable::factory()->create(['tenant_id' => $this->tenant->id]);

        Payment::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'payable_type' => AccountReceivable::class,
            'payable_id' => $receivable->id,
            'received_by' => $this->user->id,
        ]);
        Payment::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'payable_type' => AccountPayable::class,
            'payable_id' => $payable->id,
            'received_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/v1/payments?type=receivable');

        $response->assertOk();
        $data = $response->json('data');

        foreach ($data as $payment) {
            $this->assertEquals(
                AccountReceivable::class,
                $payment['payable_type'] ?? null,
                'Filtro type=receivable vazou payable'
            );
        }
    }

    public function test_summary_returns_expected_structure(): void
    {
        $receivable = AccountReceivable::factory()->create(['tenant_id' => $this->tenant->id]);
        Payment::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'payable_type' => AccountReceivable::class,
            'payable_id' => $receivable->id,
            'received_by' => $this->user->id,
            'amount' => 100,
        ]);

        $response = $this->getJson('/api/v1/payments-summary');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'total_received',
                    'total_paid',
                    'net',
                    'count',
                ],
            ]);
    }

    public function test_destroy_returns_non_success_for_cross_tenant_payment(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherReceivable = AccountReceivable::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        $foreign = Payment::factory()->create([
            'tenant_id' => $otherTenant->id,
            'payable_type' => AccountReceivable::class,
            'payable_id' => $otherReceivable->id,
            'received_by' => $otherUser->id,
        ]);

        $response = $this->deleteJson("/api/v1/payments/{$foreign->id}");

        // Nunca pode deletar um payment de outro tenant
        $this->assertNotEquals(200, $response->status(), 'Cross-tenant delete de Payment foi permitido — SECURITY P0');
        $this->assertNotEquals(204, $response->status(), 'Cross-tenant delete de Payment foi permitido — SECURITY P0');
        $this->assertContains($response->status(), [403, 404, 422]);
    }
}
