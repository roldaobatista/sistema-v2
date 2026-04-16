<?php

namespace Tests\Feature\Api\V1;

use App\Models\Payroll;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\LaborTaxTablesSeeder;
use Database\Seeders\PermissionsSeeder;
use Tests\TestCase;

class PayrollFullCycleTest extends TestCase
{
    private User $admin;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionsSeeder::class);
        $this->seed(LaborTaxTablesSeeder::class);

        $this->tenant = Tenant::factory()->create();
        $this->admin = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
            'salary' => 5000.00,
            'admission_date' => now()->subYear(),
        ]);
        $this->admin->assignRole('super_admin');
    }

    public function test_full_payroll_cycle_create_calculate_approve_pay(): void
    {
        // 1. Create draft
        $response = $this->actingAs($this->admin)->postJson('/api/v1/hr/payroll', [
            'reference_month' => now()->format('Y-m'),
            'type' => 'regular',
        ]);
        $response->assertStatus(201);
        $payrollId = $response->json('data.id');

        // 2. Calculate
        $response = $this->actingAs($this->admin)->postJson("/api/v1/hr/payroll/{$payrollId}/calculate");
        $response->assertStatus(200);
        $this->assertEquals('calculated', $response->json('data.status'));

        // 3. Approve
        $response = $this->actingAs($this->admin)->postJson("/api/v1/hr/payroll/{$payrollId}/approve");
        $response->assertStatus(200);
        $this->assertEquals('approved', $response->json('data.status'));

        // 4. Mark paid
        $response = $this->actingAs($this->admin)->postJson("/api/v1/hr/payroll/{$payrollId}/mark-paid");
        $response->assertStatus(200);
        $this->assertEquals('paid', $response->json('data.status'));

        // 5. Verify expense was generated
        $payroll = Payroll::find($payrollId);
        $this->assertNotNull($payroll->paid_at);
    }

    public function test_cannot_approve_draft_payroll(): void
    {
        $payroll = Payroll::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->admin)->postJson("/api/v1/hr/payroll/{$payroll->id}/approve");
        $response->assertStatus(422);
    }

    public function test_cannot_pay_unapproved_payroll(): void
    {
        $payroll = Payroll::factory()->create([
            'tenant_id' => $this->tenant->id,
            'status' => 'calculated',
        ]);

        $response = $this->actingAs($this->admin)->postJson("/api/v1/hr/payroll/{$payroll->id}/mark-paid");
        $response->assertStatus(422);
    }

    public function test_cannot_create_duplicate_payroll(): void
    {
        $month = now()->format('Y-m');

        $this->actingAs($this->admin)->postJson('/api/v1/hr/payroll', [
            'reference_month' => $month,
            'type' => 'regular',
        ])->assertStatus(201);

        $this->actingAs($this->admin)->postJson('/api/v1/hr/payroll', [
            'reference_month' => $month,
            'type' => 'regular',
        ])->assertStatus(422);
    }
}
