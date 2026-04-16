<?php

namespace Tests\Feature\Api\V1\Crm;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\CrmInteractiveProposal;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrmProposalTrackingControllerTest extends TestCase
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

    private function createQuote(?int $tenantId = null, ?int $customerId = null): Quote
    {
        $tid = $tenantId ?? $this->tenant->id;

        return Quote::create([
            'tenant_id' => $tid,
            'quote_number' => 'Q-'.uniqid(),
            'customer_id' => $customerId ?? $this->customer->id,
            'status' => 'draft',
            'subtotal' => 1000,
            'total' => 1000,
            'currency' => 'BRL',
            'created_by' => $this->user->id,
        ]);
    }

    private function createProposal(?int $tenantId = null, ?int $quoteId = null): CrmInteractiveProposal
    {
        $tid = $tenantId ?? $this->tenant->id;

        return CrmInteractiveProposal::create([
            'tenant_id' => $tid,
            'quote_id' => $quoteId ?? $this->createQuote($tid)->id,
            'token' => Str::random(40),
            'status' => 'sent',
            'view_count' => 0,
            'expires_at' => now()->addDays(7),
        ]);
    }

    public function test_interactive_proposals_returns_only_current_tenant(): void
    {
        $mine = $this->createProposal();

        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherQuote = $this->createQuote($otherTenant->id, $otherCustomer->id);
        $foreign = $this->createProposal($otherTenant->id, $otherQuote->id);

        $response = $this->getJson('/api/v1/crm-features/proposals');

        $response->assertOk()->assertJsonStructure(['data']);
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($mine->id, $ids);
        $this->assertNotContains($foreign->id, $ids);
    }

    public function test_interactive_proposals_filters_by_quote_id(): void
    {
        $q1 = $this->createQuote();
        $q2 = $this->createQuote();
        $this->createProposal(null, $q1->id);
        $this->createProposal(null, $q2->id);

        $response = $this->getJson("/api/v1/crm-features/proposals?quote_id={$q1->id}");

        $response->assertOk();
        foreach ($response->json('data') as $row) {
            $this->assertEquals($q1->id, $row['quote_id']);
        }
    }

    public function test_create_interactive_proposal_validates_required_quote(): void
    {
        $response = $this->postJson('/api/v1/crm-features/proposals', []);

        $response->assertStatus(422);
    }

    public function test_create_interactive_proposal_rejects_quote_from_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        $foreignQuote = $this->createQuote($otherTenant->id, $otherCustomer->id);

        $response = $this->postJson('/api/v1/crm-features/proposals', [
            'quote_id' => $foreignQuote->id,
        ]);

        $response->assertStatus(422);
    }

    public function test_tracking_stats_returns_structure(): void
    {
        $response = $this->getJson('/api/v1/crm-features/tracking/stats');

        $response->assertOk();
    }
}
