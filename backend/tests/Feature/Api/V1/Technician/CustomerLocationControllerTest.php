<?php

namespace Tests\Feature\Api\V1\Technician;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CustomerLocationControllerTest extends TestCase
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
        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'latitude' => null,
            'longitude' => null,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    // ========== UPDATE GEOLOCATION ==========

    public function test_update_sets_customer_geolocation(): void
    {
        $payload = [
            'latitude' => -23.550520,
            'longitude' => -46.633308,
        ];

        $response = $this->postJson(
            "/api/v1/technicians/customers/{$this->customer->id}/geolocation",
            $payload
        );

        $response->assertOk()
            ->assertJsonPath('data.customer_id', $this->customer->id)
            ->assertJsonPath('data.location.lat', -23.550520)
            ->assertJsonPath('data.location.lng', -46.633308);

        $this->customer->refresh();
        $this->assertEquals(-23.550520, $this->customer->latitude);
        $this->assertEquals(-46.633308, $this->customer->longitude);
    }

    public function test_update_overwrites_existing_geolocation(): void
    {
        $this->customer->update([
            'latitude' => -22.906847,
            'longitude' => -43.172896,
        ]);

        $payload = [
            'latitude' => -25.428954,
            'longitude' => -49.267137,
        ];

        $response = $this->postJson(
            "/api/v1/technicians/customers/{$this->customer->id}/geolocation",
            $payload
        );

        $response->assertOk();

        $this->customer->refresh();
        $this->assertEquals(-25.428954, $this->customer->latitude);
        $this->assertEquals(-49.267137, $this->customer->longitude);
    }

    // ========== VALIDATION ==========

    public function test_update_validates_required_latitude(): void
    {
        $response = $this->postJson(
            "/api/v1/technicians/customers/{$this->customer->id}/geolocation",
            ['longitude' => -46.633308]
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude']);
    }

    public function test_update_validates_required_longitude(): void
    {
        $response = $this->postJson(
            "/api/v1/technicians/customers/{$this->customer->id}/geolocation",
            ['latitude' => -23.550520]
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['longitude']);
    }

    public function test_update_validates_latitude_range(): void
    {
        $response = $this->postJson(
            "/api/v1/technicians/customers/{$this->customer->id}/geolocation",
            ['latitude' => 95.0, 'longitude' => -46.633308]
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude']);
    }

    public function test_update_validates_longitude_range(): void
    {
        $response = $this->postJson(
            "/api/v1/technicians/customers/{$this->customer->id}/geolocation",
            ['latitude' => -23.550520, 'longitude' => 200.0]
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['longitude']);
    }

    public function test_update_validates_latitude_negative_boundary(): void
    {
        $response = $this->postJson(
            "/api/v1/technicians/customers/{$this->customer->id}/geolocation",
            ['latitude' => -91.0, 'longitude' => -46.633308]
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude']);
    }

    public function test_update_validates_longitude_negative_boundary(): void
    {
        $response = $this->postJson(
            "/api/v1/technicians/customers/{$this->customer->id}/geolocation",
            ['latitude' => -23.550520, 'longitude' => -181.0]
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['longitude']);
    }

    public function test_update_accepts_edge_latitude_values(): void
    {
        // -90 and 90 should be valid
        $response = $this->postJson(
            "/api/v1/technicians/customers/{$this->customer->id}/geolocation",
            ['latitude' => 90, 'longitude' => 0]
        );

        $response->assertOk();

        $response = $this->postJson(
            "/api/v1/technicians/customers/{$this->customer->id}/geolocation",
            ['latitude' => -90, 'longitude' => 0]
        );

        $response->assertOk();
    }

    // ========== TENANT ISOLATION ==========

    public function test_update_rejects_customer_from_another_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherCustomer = Customer::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);

        $payload = [
            'latitude' => -23.550520,
            'longitude' => -46.633308,
        ];

        $response = $this->postJson(
            "/api/v1/technicians/customers/{$otherCustomer->id}/geolocation",
            $payload
        );

        $response->assertStatus(404);
    }

    // ========== NON-EXISTENT CUSTOMER ==========

    public function test_update_returns_404_for_nonexistent_customer(): void
    {
        $payload = [
            'latitude' => -23.550520,
            'longitude' => -46.633308,
        ];

        $response = $this->postJson(
            '/api/v1/technicians/customers/99999/geolocation',
            $payload
        );

        $response->assertNotFound();
    }

    // ========== EMPTY PAYLOAD ==========

    public function test_update_rejects_empty_payload(): void
    {
        $response = $this->postJson(
            "/api/v1/technicians/customers/{$this->customer->id}/geolocation",
            []
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    // ========== NUMERIC VALIDATION ==========

    public function test_update_rejects_non_numeric_coordinates(): void
    {
        $response = $this->postJson(
            "/api/v1/technicians/customers/{$this->customer->id}/geolocation",
            ['latitude' => 'abc', 'longitude' => 'xyz']
        );

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['latitude', 'longitude']);
    }
}
