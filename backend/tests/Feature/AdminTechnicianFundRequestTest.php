<?php

namespace Tests\Feature;

use App\Models\BankAccount;
use App\Models\TechnicianCashFund;
use App\Models\TechnicianFundRequest;
use App\Models\Tenant;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AdminTechnicianFundRequestTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Tenant::factory()->create(['id' => 1, 'status' => 'active']);
    }

    private function grantAdminPermissions(User $admin)
    {
        Permission::findOrCreate('technicians.cashbox.manage', 'web');
        Permission::findOrCreate('technicians.cashbox.manage', 'sanctum');
        Permission::findOrCreate('technicians.cashbox.manage', 'api');

        app()->instance('current_tenant_id', 1);
        setPermissionsTeamId(1);

        $admin->givePermissionTo('technicians.cashbox.manage');
    }

    public function test_admin_can_list_pending_requests()
    {
        /** @var User $admin */
        $admin = User::factory()->create(['tenant_id' => 1, 'current_tenant_id' => 1]);
        $admin->tenants()->attach(1, ['is_default' => true]);
        $this->grantAdminPermissions($admin);

        $technician = User::factory()->create(['tenant_id' => 1, 'current_tenant_id' => 1]);
        $technician->tenants()->attach(1, ['is_default' => true]);

        TechnicianFundRequest::forceCreate([
            'tenant_id' => 1,
            'user_id' => $technician->id,
            'amount' => 150,
            'reason' => 'Need money for fuel',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin, 'sanctum')->getJson('/api/v1/technician-fund-requests?status=pending');

        $response->assertStatus(200);
        $response->assertJsonStructure(['data' => [['id', 'user_id', 'amount', 'reason', 'status']]]);
        $this->assertCount(1, $response->json('data'));
    }

    public function test_admin_can_approve_request_and_funds_are_credited()
    {
        /** @var User $admin */
        $admin = User::factory()->create(['tenant_id' => 1, 'current_tenant_id' => 1]);
        $admin->tenants()->attach(1, ['is_default' => true]);
        $this->grantAdminPermissions($admin);

        $technician = User::factory()->create(['tenant_id' => 1, 'current_tenant_id' => 1]);
        $technician->tenants()->attach(1, ['is_default' => true]);

        // Ensure fund exists
        TechnicianCashFund::forceCreate([
            'tenant_id' => 1,
            'user_id' => $technician->id,
            'balance' => 0,
        ]);

        $bankAccount = BankAccount::factory()->create([
            'tenant_id' => 1,
            'balance' => 1000,
            'initial_balance' => 1000,
        ]);

        $fundReq = TechnicianFundRequest::forceCreate([
            'tenant_id' => 1,
            'user_id' => $technician->id,
            'amount' => 150,
            'reason' => 'Need money for fuel',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin, 'sanctum')->putJson("/api/v1/technician-fund-requests/{$fundReq->id}/status", [
            'status' => 'approved',
            'bank_account_id' => $bankAccount->id,
            'payment_method' => 'cash',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('technician_fund_requests', [
            'id' => $fundReq->id,
            'status' => 'approved',
            'payment_method' => 'cash',
            'approved_by' => $admin->id,
        ]);

        $fund = TechnicianCashFund::where('user_id', $technician->id)->first();
        $this->assertDatabaseHas('technician_cash_transactions', [
            'fund_id' => $fund->id,
            'amount' => '150.00',
            'type' => 'credit',
            'payment_method' => 'cash',
            // Check partial description because FundTransferService prefixes with Transferência via cash:
        ]);

        $this->assertDatabaseHas('technician_cash_funds', [
            'user_id' => $technician->id,
            'balance' => 150,
        ]);
    }

    public function test_admin_can_reject_request()
    {
        /** @var User $admin */
        $admin = User::factory()->create(['tenant_id' => 1, 'current_tenant_id' => 1]);
        $admin->tenants()->attach(1, ['is_default' => true]);
        $this->grantAdminPermissions($admin);

        $technician = User::factory()->create(['tenant_id' => 1, 'current_tenant_id' => 1]);
        $technician->tenants()->attach(1, ['is_default' => true]);

        $fundReq = TechnicianFundRequest::forceCreate([
            'tenant_id' => 1,
            'user_id' => $technician->id,
            'amount' => 150,
            'reason' => 'Need money for fuel',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($admin, 'sanctum')->putJson("/api/v1/technician-fund-requests/{$fundReq->id}/status", [
            'status' => 'rejected',
            'rejection_reason' => 'Not enough info',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('technician_fund_requests', [
            'id' => $fundReq->id,
            'status' => 'rejected',
            'approved_by' => $admin->id,
        ]);
    }

    public function test_technician_cannot_approve_own_request()
    {
        /** @var User $technician */
        $technician = User::factory()->create(['tenant_id' => 1, 'current_tenant_id' => 1]);
        $technician->tenants()->attach(1, ['is_default' => true]);

        $fundReq = TechnicianFundRequest::forceCreate([
            'tenant_id' => 1,
            'user_id' => $technician->id,
            'amount' => 150,
            'reason' => 'Need money for fuel',
            'status' => 'pending',
        ]);

        $response = $this->actingAs($technician, 'sanctum')->putJson("/api/v1/technician-fund-requests/{$fundReq->id}/status", [
            'status' => 'approved',
        ]);

        $response->assertStatus(403);
    }
}
