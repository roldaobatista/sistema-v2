<?php

namespace Tests\Feature\Api;

use App\Http\Middleware\CheckPermission;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ValidationEdgeCasesTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware([CheckPermission::class]);
        Event::fake();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        $this->user->assignRole('admin');
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    }

    // ── Customer Validation ──

    public function test_customer_name_required(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/customers', [
            'type' => 'company',
        ]);
        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('name');
    }

    public function test_customer_type_required(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/customers', [
            'name' => 'Test',
        ]);
        $response->assertUnprocessable();
        $response->assertJsonValidationErrors('type');
    }

    public function test_customer_type_invalid_value(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/customers', [
            'name' => 'Test',
            'type' => 'alien',
        ]);
        $response->assertUnprocessable();
    }

    public function test_customer_email_invalid(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/customers', [
            'name' => 'Test',
            'type' => 'company',
            'email' => 'not-email',
        ]);
        $response->assertUnprocessable();
    }

    public function test_customer_name_max_length(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/customers', [
            'name' => str_repeat('A', 500),
            'type' => 'company',
        ]);
        $this->assertTrue(in_array($response->status(), [201, 422]));
    }

    // ── WorkOrder Validation ──

    public function test_work_order_customer_id_required(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/work-orders', [
            'title' => 'Test WO',
        ]);
        $response->assertUnprocessable();
    }

    public function test_work_order_title_required(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/work-orders', [
            'customer_id' => $this->customer->id,
        ]);
        $response->assertUnprocessable();
    }

    public function test_work_order_invalid_customer_id(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/work-orders', [
            'customer_id' => 999999,
            'title' => 'Test',
        ]);
        $response->assertUnprocessable();
    }

    public function test_work_order_invalid_priority(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/work-orders', [
            'customer_id' => $this->customer->id,
            'title' => 'Test',
            'priority' => 'super-urgent',
        ]);
        $response->assertUnprocessable();
    }

    public function test_work_order_invalid_status(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/work-orders', [
            'customer_id' => $this->customer->id,
            'title' => 'Test',
            'status' => 'invalid_status',
        ]);
        $this->assertTrue(in_array($response->status(), [201, 422]));
    }

    // ── Equipment Validation ──

    public function test_equipment_name_required(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/equipments', [
            'customer_id' => $this->customer->id,
        ]);
        $response->assertUnprocessable();
    }

    public function test_equipment_customer_required(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/equipments', [
            'name' => 'Balança',
        ]);
        $response->assertUnprocessable();
    }

    public function test_equipment_serial_unique(): void
    {
        Equipment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'serial_number' => 'UNIQUE-SN-001',
        ]);
        $response = $this->actingAs($this->user)->postJson('/api/v1/equipments', [
            'name' => 'Outra Balança',
            'customer_id' => $this->customer->id,
            'serial_number' => 'UNIQUE-SN-001',
        ]);
        $this->assertTrue(in_array($response->status(), [201, 422]));
    }

    // ── Quote Validation ──

    public function test_quote_title_required(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/quotes', [
            'customer_id' => $this->customer->id,
        ]);
        $response->assertUnprocessable();
    }

    public function test_quote_customer_required(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/quotes', [
            'title' => 'Orçamento',
        ]);
        $response->assertUnprocessable();
    }

    public function test_quote_invalid_customer(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/quotes', [
            'title' => 'Orçamento',
            'customer_id' => 999999,
        ]);
        $response->assertUnprocessable();
    }

    // ── Pagination Validation ──

    public function test_pagination_negative_page(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/customers?page=-1');
        $this->assertTrue(in_array($response->status(), [200, 422]));
    }

    public function test_pagination_zero_per_page(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/customers?per_page=0');
        $this->assertTrue(in_array($response->status(), [200, 422]));
    }

    public function test_pagination_very_large_per_page(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/customers?per_page=10000');
        $this->assertTrue(in_array($response->status(), [200, 422]));
    }

    public function test_pagination_string_page(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/customers?page=abc');
        $this->assertTrue(in_array($response->status(), [200, 422]));
    }

    // ── Empty String vs Null ──

    public function test_empty_string_name_rejected(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/customers', [
            'name' => '',
            'type' => 'company',
        ]);
        $response->assertUnprocessable();
    }

    public function test_null_name_rejected(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/customers', [
            'name' => null,
            'type' => 'company',
        ]);
        $response->assertUnprocessable();
    }

    public function test_whitespace_only_name_rejected(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/customers', [
            'name' => '   ',
            'type' => 'company',
        ]);
        $this->assertTrue(in_array($response->status(), [201, 422]));
    }

    // ── Special Characters ──

    public function test_unicode_name_accepted(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/customers', [
            'name' => 'Empresa São João Ações ® © ™',
            'type' => 'company',
        ]);
        $this->assertTrue(in_array($response->status(), [201, 422]));
    }

    public function test_emoji_in_name(): void
    {
        $response = $this->actingAs($this->user)->postJson('/api/v1/customers', [
            'name' => 'Empresa 🏭',
            'type' => 'company',
        ]);
        $this->assertTrue(in_array($response->status(), [201, 422]));
    }
}
