<?php

namespace Tests\Feature\Api\V1\Portal;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PortalQuickQuoteApprovalControllerTest extends TestCase
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
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    private function createQuote(?int $tenantId = null, ?int $customerId = null, string $status = 'draft'): Quote
    {
        $tid = $tenantId ?? $this->tenant->id;

        return Quote::create([
            'tenant_id' => $tid,
            'quote_number' => 'Q-'.uniqid(),
            'customer_id' => $customerId ?? $this->customer->id,
            'status' => $status,
            'subtotal' => 1000,
            'total' => 1000,
            'currency' => 'BRL',
            'created_by' => $this->user->id,
            'valid_until' => now()->addDays(30),
        ]);
    }

    public function test_approve_validates_required_fields(): void
    {
        $quote = $this->createQuote();

        $response = $this->postJson("/api/v1/portal/quotes/{$quote->id}/approve", []);

        $response->assertStatus(422);
    }

    public function test_approve_returns_404_for_nonexistent_quote(): void
    {
        $response = $this->postJson('/api/v1/portal/quotes/99999/approve', [
            'customer_id' => $this->customer->id,
            'approval_token' => 'qualquer-token',
        ]);

        $this->assertContains($response->status(), [404, 422]);
    }

    public function test_approve_returns_404_for_cross_tenant_quote(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $foreign = $this->createQuote($otherTenant->id, $otherCustomer->id);

        $response = $this->postJson("/api/v1/portal/quotes/{$foreign->id}/approve", [
            'customer_id' => $otherCustomer->id,
            'approval_token' => 'token-qualquer',
        ]);

        $this->assertContains($response->status(), [403, 404, 422]);
    }

    public function test_approve_rejects_invalid_token(): void
    {
        $quote = $this->createQuote();

        $response = $this->postJson("/api/v1/portal/quotes/{$quote->id}/approve", [
            'customer_id' => $this->customer->id,
            'approval_token' => 'token-absolutamente-invalido',
        ]);

        $this->assertContains($response->status(), [403, 404, 422]);
    }
}
