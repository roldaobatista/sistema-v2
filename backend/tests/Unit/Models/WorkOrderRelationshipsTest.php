<?php

namespace Tests\Unit\Models;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Quote;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use App\Models\WorkOrderItem;
use App\Models\WorkOrderStatusHistory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class WorkOrderRelationshipsTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        app()->instance('current_tenant_id', $this->tenant->id);
        $this->actingAs($this->user);
    }

    // ── BelongsTo Relationships ──

    public function test_belongs_to_customer(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
        ]);

        $this->assertInstanceOf(Customer::class, $wo->customer);
        $this->assertEquals($this->customer->id, $wo->customer->id);
    }

    public function test_belongs_to_equipment(): void
    {
        $eq = Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id]);
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'equipment_id' => $eq->id,
        ]);

        $this->assertInstanceOf(Equipment::class, $wo->equipment);
        $this->assertEquals($eq->id, $wo->equipment->id);
    }

    public function test_belongs_to_branch(): void
    {
        $branch = Branch::factory()->create(['tenant_id' => $this->tenant->id]);
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'branch_id' => $branch->id,
        ]);

        $this->assertInstanceOf(Branch::class, $wo->branch);
    }

    public function test_belongs_to_creator(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'created_by' => $this->user->id,
        ]);

        $this->assertInstanceOf(User::class, $wo->creator);
        $this->assertEquals($this->user->id, $wo->creator->id);
    }

    public function test_belongs_to_assignee(): void
    {
        $tech = User::factory()->create(['tenant_id' => $this->tenant->id, 'current_tenant_id' => $this->tenant->id]);
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'assigned_to' => $tech->id,
        ]);

        $this->assertInstanceOf(User::class, $wo->assignee);
        $this->assertEquals($tech->id, $wo->assignee->id);
    }

    public function test_belongs_to_quote(): void
    {
        $quote = Quote::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id]);
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'quote_id' => $quote->id,
        ]);

        $this->assertInstanceOf(Quote::class, $wo->quote);
    }

    public function test_belongs_to_seller(): void
    {
        $seller = User::factory()->create(['tenant_id' => $this->tenant->id, 'current_tenant_id' => $this->tenant->id]);
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'seller_id' => $seller->id,
        ]);

        $this->assertInstanceOf(User::class, $wo->seller);
    }

    public function test_belongs_to_driver(): void
    {
        $driver = User::factory()->create(['tenant_id' => $this->tenant->id, 'current_tenant_id' => $this->tenant->id]);
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'driver_id' => $driver->id,
        ]);

        $this->assertInstanceOf(User::class, $wo->driver);
    }

    // ── HasMany Relationships ──

    public function test_has_many_items(): void
    {
        $wo = WorkOrder::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id]);
        WorkOrderItem::factory()->count(3)->create(['work_order_id' => $wo->id, 'tenant_id' => $this->tenant->id]);

        $this->assertCount(3, $wo->items);
        $this->assertInstanceOf(WorkOrderItem::class, $wo->items->first());
    }

    public function test_has_many_status_history(): void
    {
        $wo = WorkOrder::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id]);
        WorkOrderStatusHistory::create([
            'work_order_id' => $wo->id,
            'tenant_id' => $this->tenant->id,
            'from_status' => 'open',
            'to_status' => 'in_progress',
            'user_id' => $this->user->id,
        ]);

        $this->assertGreaterThanOrEqual(1, $wo->statusHistory()->count());
    }

    public function test_has_many_events(): void
    {
        $wo = WorkOrder::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id]);

        $this->assertCount(0, $wo->events);
        $this->assertInstanceOf(HasMany::class, $wo->events());
    }

    public function test_has_many_chats(): void
    {
        $wo = WorkOrder::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id]);
        $this->assertInstanceOf(HasMany::class, $wo->chats());
    }

    public function test_has_many_invoices(): void
    {
        $wo = WorkOrder::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id]);
        $this->assertInstanceOf(HasMany::class, $wo->invoices());
    }

    public function test_has_many_attachments(): void
    {
        $wo = WorkOrder::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id]);
        $this->assertInstanceOf(HasMany::class, $wo->attachments());
    }

    public function test_has_many_calibrations(): void
    {
        $wo = WorkOrder::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id]);
        $this->assertInstanceOf(HasMany::class, $wo->calibrations());
    }

    // ── BelongsToMany Relationships ──

    public function test_belongs_to_many_technicians(): void
    {
        $wo = WorkOrder::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id]);
        $tech = User::factory()->create(['tenant_id' => $this->tenant->id, 'current_tenant_id' => $this->tenant->id]);

        $wo->technicians()->attach($tech->id, ['role' => 'lead']);

        $this->assertCount(1, $wo->technicians);
        $this->assertEquals('lead', $wo->technicians->first()->pivot->role);
    }

    public function test_belongs_to_many_equipments_list(): void
    {
        $wo = WorkOrder::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id]);
        $eq = Equipment::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id]);

        $wo->equipmentsList()->attach($eq->id, ['observations' => 'Test obs']);

        $this->assertCount(1, $wo->equipmentsList);
        $this->assertEquals('Test obs', $wo->equipmentsList->first()->pivot->observations);
    }

    // ── Self-referential (Parent/Children) ──

    public function test_parent_and_children_relationships(): void
    {
        $parent = WorkOrder::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id, 'is_master' => true]);
        $child = WorkOrder::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id, 'parent_id' => $parent->id]);

        $this->assertInstanceOf(WorkOrder::class, $child->parent);
        $this->assertEquals($parent->id, $child->parent->id);
        $this->assertCount(1, $parent->children);
    }

    // ── Accessors ──

    public function test_business_number_accessor_returns_os_number(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'os_number' => 'OS-000042',
        ]);

        $this->assertEquals('OS-000042', $wo->business_number);
    }

    public function test_business_number_accessor_falls_back_to_number(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'os_number' => null,
            'number' => 'OS-000099',
        ]);

        $this->assertEquals('OS-000099', $wo->business_number);
    }

    public function test_warranty_until_returns_null_when_not_completed(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'completed_at' => null,
        ]);

        $this->assertNull($wo->warranty_until);
    }

    public function test_warranty_until_returns_date_when_completed(): void
    {
        $completedAt = now()->subDays(10);
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'completed_at' => $completedAt,
        ]);

        $this->assertNotNull($wo->warranty_until);
        $this->assertTrue($wo->warranty_until->greaterThan($completedAt));
    }

    public function test_is_under_warranty_returns_true_when_within_period(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'completed_at' => now()->subDays(5),
        ]);

        $this->assertTrue($wo->is_under_warranty);
    }

    public function test_is_under_warranty_returns_false_when_expired(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'completed_at' => now()->subDays(365),
        ]);

        $this->assertFalse($wo->is_under_warranty);
    }

    // ── Status Transitions ──

    public function test_can_transition_to_valid_status(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);

        $this->assertTrue($wo->canTransitionTo(WorkOrder::STATUS_IN_DISPLACEMENT));
    }

    public function test_cannot_transition_to_invalid_status(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => WorkOrder::STATUS_INVOICED,
        ]);

        $this->assertFalse($wo->canTransitionTo(WorkOrder::STATUS_OPEN));
    }

    public function test_can_transition_from_cancelled_back_to_open(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => WorkOrder::STATUS_CANCELLED,
        ]);

        $this->assertTrue($wo->canTransitionTo(WorkOrder::STATUS_OPEN));
    }

    public function test_cannot_transition_with_invalid_status_string(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'status' => WorkOrder::STATUS_OPEN,
        ]);

        $this->assertFalse($wo->canTransitionTo('nonexistent_status'));
    }

    // ── Business Methods ──

    public function test_next_number_generates_sequential_padded_number(): void
    {
        $num1 = WorkOrder::nextNumber($this->tenant->id);
        $this->assertMatchesRegularExpression('/^OS-\d{6}$/', $num1);
    }

    public function test_is_technician_authorized_for_assignee(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'assigned_to' => $this->user->id,
        ]);

        $this->assertTrue($wo->isTechnicianAuthorized($this->user->id));
    }

    public function test_is_technician_authorized_for_attached_tech(): void
    {
        $wo = WorkOrder::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id]);
        $tech = User::factory()->create(['tenant_id' => $this->tenant->id, 'current_tenant_id' => $this->tenant->id]);
        $wo->technicians()->attach($tech->id, ['role' => 'helper']);

        $this->assertTrue($wo->isTechnicianAuthorized($tech->id));
    }

    public function test_is_technician_not_authorized_for_random_user(): void
    {
        $wo = WorkOrder::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id]);
        $other = User::factory()->create(['tenant_id' => $this->tenant->id, 'current_tenant_id' => $this->tenant->id]);

        $this->assertFalse($wo->isTechnicianAuthorized($other->id));
    }

    public function test_is_technician_authorized_returns_false_for_null_user_id(): void
    {
        $wo = WorkOrder::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id]);

        $this->assertFalse($wo->isTechnicianAuthorized(null));
    }

    // ── Casts ──

    public function test_tags_cast_as_array(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'tags' => ['urgent', 'vip'],
        ]);

        $wo->refresh();
        $this->assertIsArray($wo->tags);
        $this->assertContains('urgent', $wo->tags);
    }

    public function test_decimal_casts_are_proper(): void
    {
        $wo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'total' => '1234.56',
            'discount' => '100.50',
        ]);

        $wo->refresh();
        $this->assertEquals('1234.56', $wo->total);
        $this->assertEquals('100.50', $wo->discount);
    }

    // ── Warranty Days Configuration ──

    public function test_warranty_days_returns_default_when_no_config(): void
    {
        $days = WorkOrder::warrantyDays($this->tenant->id);
        $this->assertEquals(90, $days);
    }

    public function test_warranty_days_returns_configured_value(): void
    {
        SystemSetting::create([
            'tenant_id' => $this->tenant->id,
            'key' => 'warranty_days',
            'value' => '180',
        ]);

        $days = WorkOrder::warrantyDays($this->tenant->id);
        $this->assertEquals(180, $days);
    }

    // ── Soft Delete ──

    public function test_soft_delete_excludes_from_default_query(): void
    {
        $wo = WorkOrder::factory()->create(['tenant_id' => $this->tenant->id, 'customer_id' => $this->customer->id]);
        $woId = $wo->id;

        $wo->delete();

        $this->assertNull(WorkOrder::find($woId));
        $this->assertNotNull(WorkOrder::withTrashed()->find($woId));
    }

    // ── Static Methods ──

    public function test_statuses_returns_all_enum_cases(): void
    {
        $statuses = WorkOrder::statuses();

        $this->assertIsArray($statuses);
        $this->assertArrayHasKey('open', $statuses);
        $this->assertArrayHasKey('completed', $statuses);
        $this->assertArrayHasKey('cancelled', $statuses);
        $this->assertArrayHasKey('label', $statuses['open']);
        $this->assertArrayHasKey('color', $statuses['open']);
    }
}
