<?php

namespace Tests\Feature\Console;

use App\Models\Customer;
use App\Models\Notification;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class CheckUnbilledWorkOrdersTest extends TestCase
{
    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->tenant = Tenant::factory()->create();
        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
    }

    public function test_alerts_completed_work_orders_without_billing(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
        $admin = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $admin->assignRole($adminRole);

        // Completed WO 5 days ago without billing (should alert)
        $unbilledWo = WorkOrder::factory()->completed()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'completed_at' => now()->subDays(5),
        ]);

        // Completed WO 1 day ago (within grace period, should NOT alert)
        $recentWo = WorkOrder::factory()->completed()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'completed_at' => now()->subDay(),
        ]);

        // Open WO (should NOT alert)
        $openWo = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
        ]);

        $this->artisan('work-orders:check-unbilled')
            ->assertExitCode(0);

        // Should alert for unbilled WO
        $this->assertDatabaseHas('notifications', [
            'user_id' => $admin->id,
            'type' => 'unbilled_work_order',
            'notifiable_type' => WorkOrder::class,
            'notifiable_id' => $unbilledWo->id,
        ]);

        // Should NOT alert for recent or open WOs
        $this->assertDatabaseMissing('notifications', [
            'notifiable_type' => WorkOrder::class,
            'notifiable_id' => $recentWo->id,
            'type' => 'unbilled_work_order',
        ]);
        $this->assertDatabaseMissing('notifications', [
            'notifiable_type' => WorkOrder::class,
            'notifiable_id' => $openWo->id,
            'type' => 'unbilled_work_order',
        ]);
    }

    public function test_excludes_work_orders_with_invoice(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
        $admin = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $admin->assignRole($adminRole);

        $billedWo = WorkOrder::factory()->completed()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'completed_at' => now()->subDays(10),
        ]);

        // Create an invoice for this WO (faturada = no alert)
        \DB::table('invoices')->insert([
            'tenant_id' => $this->tenant->id,
            'work_order_id' => $billedWo->id,
            'customer_id' => $customer->id,
            'created_by' => $admin->id,
            'invoice_number' => 'NF-000001',
            'status' => 'issued',
            'total' => 1000,
            'issued_at' => now(),
            'due_date' => now()->addDays(30),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->artisan('work-orders:check-unbilled')
            ->assertExitCode(0);

        // Should NOT alert because WO has invoice
        $this->assertDatabaseMissing('notifications', [
            'notifiable_type' => WorkOrder::class,
            'notifiable_id' => $billedWo->id,
            'type' => 'unbilled_work_order',
        ]);
    }

    public function test_deduplicates_alerts_within_1_day(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $adminRole = Role::firstOrCreate(['name' => 'admin', 'guard_name' => 'web', 'tenant_id' => $this->tenant->id]);
        $admin = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $admin->assignRole($adminRole);

        $unbilledWo = WorkOrder::factory()->completed()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'completed_at' => now()->subDays(5),
        ]);

        // Run twice
        $this->artisan('work-orders:check-unbilled')->assertExitCode(0);
        $this->artisan('work-orders:check-unbilled')->assertExitCode(0);

        // Should only have 1 notification (deduplicated)
        $count = Notification::where('notifiable_type', WorkOrder::class)
            ->where('notifiable_id', $unbilledWo->id)
            ->where('user_id', $admin->id)
            ->where('type', 'unbilled_work_order')
            ->count();

        $this->assertEquals(1, $count);
    }
}
