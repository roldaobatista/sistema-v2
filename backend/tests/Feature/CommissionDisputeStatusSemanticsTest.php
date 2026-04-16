<?php

namespace Tests\Feature;

use App\Enums\CommissionDisputeStatus;
use App\Enums\CommissionEventStatus;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\CommissionDispute;
use App\Models\CommissionEvent;
use App\Models\CommissionRule;
use App\Models\Customer;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class CommissionDisputeStatusSemanticsTest extends TestCase
{
    private Tenant $tenant;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([
            EnsureTenantScope::class,
        ]);

        $this->tenant = Tenant::factory()->create();
        $this->manager = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $this->setTenantContext($this->tenant->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ([
            'commissions.dispute.view',
            'commissions.dispute.resolve',
        ] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $this->manager->givePermissionTo([
            'commissions.dispute.view',
            'commissions.dispute.resolve',
        ]);

        Sanctum::actingAs($this->manager, ['*']);
    }

    public function test_resolved_filter_returns_legacy_rows_and_modern_final_states(): void
    {
        $accepted = $this->createDispute(CommissionDisputeStatus::ACCEPTED->value);
        $rejected = $this->createDispute(CommissionDisputeStatus::REJECTED->value);
        $legacyResolved = $this->createLegacyResolvedDispute();
        $open = $this->createDispute(CommissionDisputeStatus::OPEN->value);

        $response = $this->getJson('/api/v1/commission-disputes?status=resolved');

        $response->assertOk();
        $response->assertJsonCount(3, 'data');

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($accepted->id, $ids);
        $this->assertContains($rejected->id, $ids);
        $this->assertContains($legacyResolved->id, $ids);
        $this->assertNotContains($open->id, $ids);
    }

    public function test_resolve_endpoint_still_rejects_legacy_resolved_as_write_target_status(): void
    {
        $dispute = $this->createDispute(CommissionDisputeStatus::OPEN->value);

        $this->putJson("/api/v1/commission-disputes/{$dispute->id}/resolve", [
            'status' => CommissionDisputeStatus::RESOLVED->value,
            'resolution_notes' => 'Fluxo novo nao deve aceitar resolved como escrita.',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    private function createDispute(string $status): CommissionDispute
    {
        $beneficiary = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
        $workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $customer->id,
            'created_by' => $this->manager->id,
            'assigned_to' => $beneficiary->id,
            'status' => WorkOrder::STATUS_AWAITING_RETURN,
        ]);

        $rule = CommissionRule::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => null,
            'name' => 'Semantica de disputa',
            'type' => 'percentage',
            'value' => 10,
            'applies_to' => 'all',
            'active' => true,
            'calculation_type' => CommissionRule::CALC_PERCENT_GROSS,
            'applies_to_role' => CommissionRule::ROLE_TECHNICIAN,
            'applies_when' => CommissionRule::WHEN_OS_COMPLETED,
        ]);

        $event = CommissionEvent::create([
            'tenant_id' => $this->tenant->id,
            'commission_rule_id' => $rule->id,
            'work_order_id' => $workOrder->id,
            'user_id' => $beneficiary->id,
            'base_amount' => 1000,
            'commission_amount' => 100,
            'status' => CommissionEventStatus::PENDING,
        ]);

        return CommissionDispute::create([
            'tenant_id' => $this->tenant->id,
            'commission_event_id' => $event->id,
            'user_id' => $beneficiary->id,
            'reason' => "Contestacao {$status} para validacao de semantica.",
            'status' => $status,
            'resolution_notes' => $status === CommissionDisputeStatus::OPEN->value ? null : "Status {$status} consolidado.",
            'resolved_by' => $status === CommissionDisputeStatus::OPEN->value ? null : $this->manager->id,
            'resolved_at' => $status === CommissionDisputeStatus::OPEN->value ? null : now(),
        ]);
    }

    private function createLegacyResolvedDispute(): CommissionDispute
    {
        $dispute = $this->createDispute(CommissionDisputeStatus::OPEN->value);

        DB::table('commission_disputes')
            ->where('id', $dispute->id)
            ->update([
                'status' => CommissionDisputeStatus::RESOLVED->value,
                'resolution_notes' => 'Linha legada resolvida antes da canonicalizacao.',
                'resolved_by' => $this->manager->id,
                'resolved_at' => now(),
                'updated_at' => now(),
            ]);

        return $dispute->fresh();
    }
}
