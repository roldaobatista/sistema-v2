<?php

namespace Tests\Feature\Api\V1\Financial;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\BankAccount;
use App\Models\FundTransfer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FundTransferControllerTest extends TestCase
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

    public function test_index_returns_only_current_tenant_transfers(): void
    {
        // 3 transferencias do tenant atual
        FundTransfer::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'bank_account_id' => BankAccount::factory()->create(['tenant_id' => $this->tenant->id])->id,
            'to_user_id' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        // 5 transferencias de OUTRO tenant
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        FundTransfer::factory()->count(5)->create([
            'tenant_id' => $otherTenant->id,
            'bank_account_id' => BankAccount::factory()->create(['tenant_id' => $otherTenant->id])->id,
            'to_user_id' => $otherUser->id,
            'created_by' => $otherUser->id,
        ]);

        $response = $this->getJson('/api/v1/fund-transfers');

        $response->assertOk()->assertJsonStructure(['data']);

        $data = $response->json('data');
        $this->assertIsArray($data);

        // Nenhuma transferencia de outro tenant pode vazar
        foreach ($data as $transfer) {
            $this->assertEquals(
                $this->tenant->id,
                $transfer['tenant_id'] ?? null,
                'Fund transfer de outro tenant vazou na listagem'
            );
        }
    }

    public function test_show_returns_404_for_cross_tenant_transfer(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);

        $foreignTransfer = FundTransfer::factory()->create([
            'tenant_id' => $otherTenant->id,
            'bank_account_id' => BankAccount::factory()->create(['tenant_id' => $otherTenant->id])->id,
            'to_user_id' => $otherUser->id,
            'created_by' => $otherUser->id,
        ]);

        $response = $this->getJson("/api/v1/fund-transfers/{$foreignTransfer->id}");

        // Deve ser 404 (nao 403) para nao vazar existencia
        $response->assertStatus(404);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/fund-transfers', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'bank_account_id',
                'to_user_id',
                'amount',
                'transfer_date',
                'payment_method',
                'description',
            ]);
    }

    public function test_store_rejects_negative_amount(): void
    {
        $bankAccount = BankAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $response = $this->postJson('/api/v1/fund-transfers', [
            'bank_account_id' => $bankAccount->id,
            'to_user_id' => $this->user->id,
            'amount' => -100,
            'transfer_date' => now()->format('Y-m-d'),
            'payment_method' => 'pix',
            'description' => 'Teste',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['amount']);
    }

    public function test_store_rejects_bank_account_from_other_tenant(): void
    {
        // SEGURANCA: Conta de outro tenant nao pode ser usada como fonte da transferencia.
        $otherTenant = Tenant::factory()->create();
        $foreignBankAccount = BankAccount::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);

        $response = $this->postJson('/api/v1/fund-transfers', [
            'bank_account_id' => $foreignBankAccount->id,
            'to_user_id' => $this->user->id,
            'amount' => 100,
            'transfer_date' => now()->format('Y-m-d'),
            'payment_method' => 'pix',
            'description' => 'Ataque cross-tenant',
        ]);

        // FormRequest tem Rule::exists com where tenant_id → deve retornar 422
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['bank_account_id']);
    }

    public function test_cancel_returns_404_for_cross_tenant_transfer(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);

        $foreignTransfer = FundTransfer::factory()->create([
            'tenant_id' => $otherTenant->id,
            'bank_account_id' => BankAccount::factory()->create(['tenant_id' => $otherTenant->id])->id,
            'to_user_id' => $otherUser->id,
            'created_by' => $otherUser->id,
        ]);

        $response = $this->postJson("/api/v1/fund-transfers/{$foreignTransfer->id}/cancel");

        // Nunca pode retornar 200 — caso contrario, cross-tenant cancel funcionou
        $this->assertNotEquals(200, $response->status(), 'Cross-tenant cancel de fund transfer foi permitido — SECURITY P0');
        $this->assertContains($response->status(), [403, 404, 422]);
    }
}
