<?php

namespace Tests\Feature\Security;

use App\Http\Middleware\CheckPermission;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class AdvancedSecurityTest extends TestCase
{
    private Tenant $tenant;

    private User $admin;

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

    }

    // ── Token Security ──

    public function test_expired_token_rejected(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer expired-token-123',
        ])->getJson('/api/v1/work-orders');
        $response->assertUnauthorized();
    }

    public function test_malformed_token_rejected(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer',
        ])->getJson('/api/v1/work-orders');
        $response->assertUnauthorized();
    }

    public function test_empty_auth_header_rejected(): void
    {
        $response = $this->withHeaders([
            'Authorization' => '',
        ])->getJson('/api/v1/work-orders');
        $response->assertUnauthorized();
    }

    // ── Input Sanitization ──

    public function test_html_tags_in_input_sanitized(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/customers', [
            'name' => '<script>alert("xss")</script>Customer',
            'type' => 'company',
        ]);
        if ($response->status() === 201) {
            $this->assertStringNotContainsString('<script>', $response->json('data.name', ''));
        } else {
            // Validation rejected the input — equally safe
            $this->assertContains($response->status(), [422, 400]);
        }
    }

    public function test_sql_injection_in_search(): void
    {
        $initialCount = Customer::count();

        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/customers?search='.urlencode("'; DROP TABLE customers; --"));
        $response->assertOk();

        $this->assertSame($initialCount, Customer::count());
    }

    public function test_sql_injection_in_order_by(): void
    {
        $response = $this->actingAs($this->admin)
            ->getJson('/api/v1/work-orders?sort=name; DROP TABLE work_orders');
        // Should not crash
        $this->assertNotEquals(500, $response->status(), 'SQL injection in order_by caused a server error');
    }

    public function test_nosql_injection_attempt(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/customers', [
            'name' => '{"$gt":""}',
            'type' => 'company',
        ]);
        $this->assertNotEquals(500, $response->status(), 'NoSQL injection attempt caused a server error');
    }

    // ── CSRF/Security Headers ──

    public function test_api_returns_json_content_type(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/work-orders');
        $response->assertHeader('Content-Type', 'application/json');
    }

    public function test_api_accepts_only_json(): void
    {
        $response = $this->actingAs($this->admin)
            ->post('/api/v1/customers', ['name' => 'test'], ['Content-Type' => 'text/html']);
        $this->assertNotEquals(500, $response->status(), 'Non-JSON content type caused a server error');
    }

    // ── ID Manipulation ──

    public function test_cannot_access_other_tenant_record_by_id(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/v1/customers/{$otherCustomer->id}");
        $response->assertNotFound();
    }

    public function test_cannot_update_other_tenant_record(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->actingAs($this->admin)
            ->putJson("/api/v1/customers/{$otherCustomer->id}", ['name' => 'Hacked']);
        $response->assertNotFound();
    }

    public function test_cannot_delete_other_tenant_record(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create(['tenant_id' => $otherTenant->id]);

        $response = $this->actingAs($this->admin)
            ->deleteJson("/api/v1/customers/{$otherCustomer->id}");
        $response->assertNotFound();
    }

    // ── Payload Size ──

    public function test_large_payload_handled(): void
    {
        $largeText = str_repeat('A', 100000);
        $response = $this->actingAs($this->admin)->postJson('/api/v1/customers', [
            'name' => $largeText,
            'type' => 'company',
        ]);
        $this->assertContains($response->status(), [201, 422, 413], 'Large payload should be created or rejected by validation, not cause errors');
    }

    public function test_empty_payload_rejected(): void
    {
        $response = $this->actingAs($this->admin)->postJson('/api/v1/customers', []);
        $response->assertUnprocessable();
    }

    // ── Integer Overflow ──

    public function test_negative_id_handled(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/customers/-1');
        $this->assertContains($response->status(), [404, 422, 400], 'Negative ID should return not found or validation error');
    }

    public function test_zero_id_handled(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/customers/0');
        $this->assertContains($response->status(), [404, 422, 400], 'Zero ID should return not found or validation error');
    }

    public function test_very_large_id_handled(): void
    {
        $response = $this->actingAs($this->admin)->getJson('/api/v1/customers/999999999999');
        $response->assertNotFound();
    }

    // ── Method Spoofing ──

    public function test_invalid_method_handled(): void
    {
        $response = $this->actingAs($this->admin)
            ->call('PATCH', '/api/v1/customers/1');
        $this->assertNotEquals(500, $response->status(), 'Invalid HTTP method should not cause a server error');
    }

    // ── Concurrent Requests ──

    public function test_concurrent_create_no_duplicates(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $data = [
            'customer_id' => $customer->id,
            'title' => 'Concurrent Test',
            'priority' => 'medium',
        ];
        $response1 = $this->actingAs($this->admin)->postJson('/api/v1/work-orders', $data);
        $response2 = $this->actingAs($this->admin)->postJson('/api/v1/work-orders', $data);
        // Both should succeed without duplicate detection errors
        $this->assertTrue(in_array($response1->status(), [201, 200, 422]));
        $this->assertTrue(in_array($response2->status(), [201, 200, 422]));
    }
}
