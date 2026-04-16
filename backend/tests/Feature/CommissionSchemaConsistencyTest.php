<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\CommissionEvent;
use App\Models\CommissionRule;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommissionSchemaConsistencyTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

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

        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_split_endpoint_persists_canonical_default_role(): void
    {
        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $otherUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $otherUser->tenants()->attach($this->tenant->id, ['is_default' => false]);

        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->user->id,
        ]);

        $rule = CommissionRule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
        ]);

        $event = CommissionEvent::factory()->create([
            'tenant_id' => $this->tenant->id,
            'commission_rule_id' => $rule->id,
            'work_order_id' => $workOrder->id,
            'user_id' => $this->user->id,
            'commission_amount' => 200,
        ]);

        $this->postJson("/api/v1/commission-events/{$event->id}/splits", [
            'splits' => [
                ['user_id' => $this->user->id, 'percentage' => 60],
                ['user_id' => $otherUser->id, 'percentage' => 40],
            ],
        ])->assertOk();

        $roles = DB::table('commission_splits')
            ->where('tenant_id', $this->tenant->id)
            ->where('commission_event_id', $event->id)
            ->pluck('role')
            ->all();

        $this->assertCount(2, $roles);
        $this->assertSame(['tecnico', 'tecnico'], $roles);
    }

    public function test_commission_goals_keeps_single_unique_index_for_domain_key(): void
    {
        $indexes = match (DB::getDriverName()) {
            'mysql' => collect(DB::select('SHOW INDEX FROM `commission_goals`'))
                ->pluck('Key_name')
                ->filter(fn ($name) => str_contains((string) $name, 'tenant') && str_contains((string) $name, 'period') && str_contains((string) $name, 'type'))
                ->unique()
                ->values()
                ->all(),
            'sqlite' => collect(DB::select("PRAGMA index_list('commission_goals')"))
                ->pluck('name')
                ->filter(fn ($name) => str_contains((string) $name, 'tenant') && str_contains((string) $name, 'period') && str_contains((string) $name, 'type'))
                ->unique()
                ->values()
                ->all(),
            default => [],
        };

        $this->assertSame(
            ['commission_goals_tenant_user_period_type_unique'],
            $indexes
        );
    }
}
