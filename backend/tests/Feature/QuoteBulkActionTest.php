<?php

namespace Tests\Feature;

use App\Enums\QuoteStatus;
use App\Http\Middleware\CheckPermission;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * Tests for quote bulk action endpoint.
 */
class QuoteBulkActionTest extends TestCase
{
    private Tenant $tenant;

    private User $admin;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([CheckPermission::class]);
        Event::fake();

        $this->tenant = Tenant::factory()->create();
        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->admin->tenants()->attach($this->tenant->id, ['is_default' => true]);
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        $this->admin->assignRole('admin');
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    }

    public function test_bulk_delete_quotes(): void
    {
        $quotes = Quote::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => QuoteStatus::DRAFT,
        ]);

        $response = $this->actingAs($this->admin)->postJson('/api/v1/quotes/bulk-action', [
            'ids' => $quotes->pluck('id')->toArray(),
            'action' => 'delete',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.success', 3);
        $response->assertJsonPath('data.failed', 0);
    }

    public function test_bulk_action_validates_required_fields(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/quotes/bulk-action', []);
        $response->assertUnprocessable();
    }

    public function test_bulk_action_validates_action_type(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/quotes/bulk-action', [
            'ids' => [1],
            'action' => 'invalid_action',
        ]);
        $response->assertUnprocessable();
    }

    public function test_bulk_action_limits_to_50(): void
    {
        $ids = range(1, 51);
        $response = $this->actingAs($this->admin)->postJson('/api/v1/quotes/bulk-action', [
            'ids' => $ids,
            'action' => 'delete',
        ]);
        $response->assertUnprocessable();
    }

    public function test_bulk_action_partial_failure(): void
    {
        $draftQuote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => QuoteStatus::DRAFT,
        ]);
        $sentQuote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => QuoteStatus::SENT,
        ]);

        // approve action: draft should fail (not sent), sent should succeed
        $response = $this->actingAs($this->admin)->postJson('/api/v1/quotes/bulk-action', [
            'ids' => [$draftQuote->id, $sentQuote->id],
            'action' => 'approve',
        ]);

        $response->assertOk();
        $this->assertEquals(1, $response->json('data.success'));
        $this->assertEquals(1, $response->json('data.failed'));
        $this->assertNotEmpty($response->json('data.errors'));
    }

    public function test_bulk_action_ignores_cross_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherQuote = Quote::factory()->create([
            'tenant_id' => $otherTenant->id,
            'customer_id' => Customer::factory()->create(['tenant_id' => $otherTenant->id])->id,
        ]);

        $response = $this->actingAs($this->admin)->postJson('/api/v1/quotes/bulk-action', [
            'ids' => [$otherQuote->id],
            'action' => 'delete',
        ]);

        $response->assertOk();
        $this->assertEquals(0, $response->json('data.success'));
        $this->assertEquals(1, $response->json('data.failed'));
    }
}
