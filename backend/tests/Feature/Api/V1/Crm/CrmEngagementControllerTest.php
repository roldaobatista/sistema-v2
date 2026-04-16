<?php

namespace Tests\Feature\Api\V1\Crm;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\CrmCalendarEvent;
use App\Models\CrmReferral;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CrmEngagementControllerTest extends TestCase
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

    public function test_features_constants_returns_static_metadata(): void
    {
        $response = $this->getJson('/api/v1/crm-features/constants');

        $response->assertOk()->assertJsonStructure(['data']);
    }

    public function test_referrals_returns_only_current_tenant(): void
    {
        CrmReferral::create([
            'tenant_id' => $this->tenant->id,
            'referrer_customer_id' => $this->customer->id,
            'referred_name' => 'João da Silva',
            'referred_email' => 'joao@example.com',
            'status' => 'pending',
        ]);

        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);
        CrmReferral::create([
            'tenant_id' => $otherTenant->id,
            'referrer_customer_id' => $otherCustomer->id,
            'referred_name' => 'LEAK',
            'referred_email' => 'leak@example.com',
            'status' => 'pending',
        ]);

        $response = $this->getJson('/api/v1/crm-features/referrals');

        $response->assertOk()->assertJsonStructure(['data']);
        $json = json_encode($response->json());
        $this->assertStringNotContainsString('LEAK', $json);
    }

    public function test_referral_stats_returns_totals(): void
    {
        CrmReferral::create([
            'tenant_id' => $this->tenant->id,
            'referrer_customer_id' => $this->customer->id,
            'referred_name' => 'João',
            'referred_email' => 'joao@example.com',
            'status' => 'pending',
        ]);
        CrmReferral::create([
            'tenant_id' => $this->tenant->id,
            'referrer_customer_id' => $this->customer->id,
            'referred_name' => 'Maria',
            'referred_email' => 'maria@example.com',
            'status' => 'converted',
        ]);

        $response = $this->getJson('/api/v1/crm-features/referrals/stats');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(2, $response->json('data.total'));
        $this->assertGreaterThanOrEqual(1, $response->json('data.converted'));
    }

    public function test_calendar_events_returns_only_current_tenant(): void
    {
        CrmCalendarEvent::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'title' => 'Reunião cliente',
            'type' => 'meeting',
            'start_at' => now(),
            'end_at' => now()->addHour(),
        ]);

        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        CrmCalendarEvent::create([
            'tenant_id' => $otherTenant->id,
            'user_id' => $otherUser->id,
            'title' => 'LEAK reunião',
            'type' => 'meeting',
            'start_at' => now(),
            'end_at' => now()->addHour(),
        ]);

        $response = $this->getJson('/api/v1/crm-features/calendar');

        $response->assertOk();
        $json = json_encode($response->json());
        $this->assertStringNotContainsString('LEAK reunião', $json);
    }

    public function test_store_calendar_event_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/crm-features/calendar', []);

        $response->assertStatus(422);
    }
}
