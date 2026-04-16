<?php

namespace Tests\Feature\Api\V1\Financial;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\ChartOfAccount;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ChartOfAccountControllerTest extends TestCase
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

    public function test_index_returns_only_current_tenant_chart_of_accounts(): void
    {
        ChartOfAccount::factory()->count(3)->create(['tenant_id' => $this->tenant->id]);

        $otherTenant = Tenant::factory()->create();
        ChartOfAccount::factory()->count(4)->create(['tenant_id' => $otherTenant->id]);

        $response = $this->getJson('/api/v1/chart-of-accounts');

        $response->assertOk();

        $data = $response->json('data') ?? $response->json();
        $this->assertIsArray($data);

        foreach ($data as $account) {
            if (isset($account['tenant_id'])) {
                $this->assertEquals(
                    $this->tenant->id,
                    $account['tenant_id'],
                    'ChartOfAccount de outro tenant vazou na listagem'
                );
            }
        }
    }

    public function test_store_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/chart-of-accounts', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code', 'name', 'type']);
    }

    public function test_store_rejects_invalid_type(): void
    {
        $response = $this->postJson('/api/v1/chart-of-accounts', [
            'code' => '1.01.001',
            'name' => 'Conta Teste',
            'type' => 'invalid_type_zzz',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_store_rejects_duplicate_code_in_same_tenant(): void
    {
        ChartOfAccount::factory()->create([
            'tenant_id' => $this->tenant->id,
            'code' => '1.01.001',
        ]);

        $response = $this->postJson('/api/v1/chart-of-accounts', [
            'code' => '1.01.001',
            'name' => 'Outra Conta',
            'type' => ChartOfAccount::TYPE_REVENUE,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['code']);
    }

    public function test_update_returns_404_for_cross_tenant_account(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreign = ChartOfAccount::factory()->create([
            'tenant_id' => $otherTenant->id,
            'code' => '2.02.002',
        ]);

        $response = $this->putJson("/api/v1/chart-of-accounts/{$foreign->id}", [
            'code' => '2.02.002',
            'name' => 'Hacked',
            'type' => ChartOfAccount::TYPE_EXPENSE,
        ]);

        // Nao pode aceitar a update (200)
        $this->assertNotEquals(200, $response->status(), 'Cross-tenant update foi permitido — SECURITY P0');
        $this->assertContains($response->status(), [403, 404, 422]);
    }
}
