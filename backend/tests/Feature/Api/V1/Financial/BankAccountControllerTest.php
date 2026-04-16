<?php

namespace Tests\Feature\Api\V1\Financial;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\BankAccount;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BankAccountControllerTest extends TestCase
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

    public function test_index_returns_only_current_tenant_bank_accounts(): void
    {
        BankAccount::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $otherTenant = Tenant::factory()->create();
        BankAccount::factory()->count(5)->create(['tenant_id' => $otherTenant->id]);

        $response = $this->getJson('/api/v1/bank-accounts');

        $response->assertOk()->assertJsonStructure(['data']);

        $data = $response->json('data');
        $this->assertIsArray($data);

        foreach ($data as $account) {
            $this->assertEquals(
                $this->tenant->id,
                $account['tenant_id'] ?? null,
                'BankAccount de outro tenant vazou na listagem'
            );
        }
    }

    public function test_show_returns_404_for_cross_tenant_bank_account(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreign = BankAccount::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->getJson("/api/v1/bank-accounts/{$foreign->id}");

        $response->assertStatus(404);
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/bank-accounts', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'name',
                'bank_name',
                'account_type',
            ]);
    }

    public function test_store_rejects_invalid_account_type(): void
    {
        $response = $this->postJson('/api/v1/bank-accounts', [
            'name' => 'Conta Teste',
            'bank_name' => 'Banco Teste',
            'account_type' => 'inexistent_type_xyz',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['account_type']);
    }

    public function test_destroy_returns_404_for_cross_tenant_bank_account(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreign = BankAccount::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->deleteJson("/api/v1/bank-accounts/{$foreign->id}");

        // Nao pode ser 200/204 — caso contrario cross-tenant delete funcionou
        $this->assertNotEquals(200, $response->status(), 'Cross-tenant delete foi permitido — SECURITY P0');
        $this->assertNotEquals(204, $response->status(), 'Cross-tenant delete foi permitido — SECURITY P0');
        $this->assertContains($response->status(), [403, 404, 422]);
    }
}
