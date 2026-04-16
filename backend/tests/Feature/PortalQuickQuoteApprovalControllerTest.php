<?php

namespace Tests\Feature;

use App\Events\QuoteApproved;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
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
        ]);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cliente Portal Legado',
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);

        setPermissionsTeamId($this->tenant->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::findOrCreate('quotes.quote.approve', 'web');
        $this->user->givePermissionTo('quotes.quote.approve');

        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_portal_quick_quote_approval_uses_domain_service_metadata(): void
    {
        Event::fake([QuoteApproved::class]);

        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'seller_id' => $this->user->id,
            'status' => Quote::STATUS_SENT,
            'valid_until' => today()->addDay(),
        ]);

        $this->postJson("/api/v1/portal/quotes/{$quote->id}/approve", [
            'customer_id' => $this->customer->id,
            'approval_token' => $quote->approval_token,
        ])->assertOk()
            ->assertJsonPath('message', 'Orçamento aprovado com sucesso!')
            ->assertJsonPath('data.status', Quote::STATUS_APPROVED)
            ->assertJsonPath('data.approval_channel', 'portal_one_click');

        $quote->refresh();

        $this->assertSame(Quote::STATUS_APPROVED, $quote->status->value);
        $this->assertSame('portal_one_click', $quote->approval_channel);
        $this->assertSame($this->customer->name, $quote->approved_by_name);
        $this->assertNotNull($quote->term_accepted_at);
        $this->assertSame('127.0.0.1', $quote->client_ip_approval);

        Event::assertDispatched(QuoteApproved::class);
    }

    public function test_portal_quick_quote_approval_rejects_expired_quote(): void
    {
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'seller_id' => $this->user->id,
            'status' => Quote::STATUS_SENT,
            'valid_until' => today()->subDay(),
        ]);

        $this->postJson("/api/v1/portal/quotes/{$quote->id}/approve", [
            'customer_id' => $this->customer->id,
            'approval_token' => $quote->approval_token,
        ])->assertUnprocessable()
            ->assertJsonPath('message', 'Orçamento expirado');

        $this->assertSame(Quote::STATUS_EXPIRED, $quote->fresh()->status->value ?? $quote->fresh()->status);
    }

    public function test_portal_quick_quote_approval_rejects_invalid_token(): void
    {
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'seller_id' => $this->user->id,
            'status' => Quote::STATUS_SENT,
            'valid_until' => today()->addDay(),
        ]);

        $this->postJson("/api/v1/portal/quotes/{$quote->id}/approve", [
            'customer_id' => $this->customer->id,
            'approval_token' => 'token-invalido-portal',
        ])->assertForbidden()
            ->assertJsonPath('message', 'Token inválido');

        $this->assertSame(Quote::STATUS_SENT, $quote->fresh()->status->value ?? $quote->fresh()->status);
    }
}
