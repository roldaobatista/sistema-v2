<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuvoExportControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

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
            'is_active' => true,
        ]);
        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        Sanctum::actingAs($this->user, ['*']);

        config([
            'services.auvo.api_key' => 'test-key',
            'services.auvo.api_token' => 'test-token',
        ]);
    }

    public function test_export_customer_hides_internal_auvo_runtime_details(): void
    {
        Http::fake([
            'https://api.auvo.com.br/v2/login' => Http::response([
                'result' => ['accessToken' => 'auvo-token'],
            ], 200),
            'https://api.auvo.com.br/v2/customers' => Http::response([
                'error' => 'driver stack trace',
            ], 500),
        ]);

        $response = $this->postJson("/api/v1/auvo/export/customer/{$this->customer->id}");

        $response->assertStatus(500)
            ->assertJsonPath('message', 'Falha ao exportar Cliente para o Auvo. Tente novamente em instantes.');

        $this->assertStringNotContainsString('post customers', (string) $response->json('message'));
        $this->assertStringNotContainsString('HTTP 500', (string) $response->json('message'));
    }
}
