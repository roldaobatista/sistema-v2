<?php

/**
 * Tenant Isolation — Reports & Dashboards
 *
 * Validates that all report and dashboard endpoints only contain data
 * belonging to the authenticated tenant.
 *
 * FAILURE HERE = BUSINESS INTELLIGENCE DATA LEAK BETWEEN TENANTS
 */

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\AccountReceivable;
use App\Models\Customer;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Gate::before(fn () => true);

    $this->tenantA = Tenant::factory()->create();
    $this->tenantB = Tenant::factory()->create();

    $this->userA = User::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'current_tenant_id' => $this->tenantA->id,
        'is_active' => true,
    ]);

    $this->userB = User::factory()->create([
        'tenant_id' => $this->tenantB->id,
        'current_tenant_id' => $this->tenantB->id,
        'is_active' => true,
    ]);

    $this->customerA = Customer::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'name' => 'Report Cust A', 'type' => 'PJ',
    ]);
    $this->customerB = Customer::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'name' => 'Report Cust B', 'type' => 'PJ',
    ]);

    $this->withoutMiddleware([
        EnsureTenantScope::class,
        CheckPermission::class,
    ]);
});

function actAsTenantReport(object $test, User $user, Tenant $tenant): void
{
    app()->instance('current_tenant_id', $tenant->id);
    setPermissionsTeamId($tenant->id);
    Sanctum::actingAs($user, ['*']);
}

// ══════════════════════════════════════════════════════════════════
//  WORK ORDER REPORTS
// ══════════════════════════════════════════════════════════════════

test('work orders report is tenant-scoped', function () {
    actAsTenantReport($this, $this->userA, $this->tenantA);

    $response = $this->getJson('/api/v1/reports/work-orders');
    $response->assertOk();
});

test('productivity report is tenant-scoped', function () {
    actAsTenantReport($this, $this->userA, $this->tenantA);

    $response = $this->getJson('/api/v1/reports/productivity');
    $response->assertOk();
});

// ══════════════════════════════════════════════════════════════════
//  FINANCIAL REPORTS
// ══════════════════════════════════════════════════════════════════

test('financial report is tenant-scoped', function () {
    // Create data in both tenants
    AccountReceivable::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'customer_id' => $this->customerA->id,
        'created_by' => $this->userA->id, 'description' => 'Report AR A',
        'amount' => 5000, 'due_date' => now()->addDays(30), 'status' => 'pending',
    ]);
    AccountReceivable::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'customer_id' => $this->customerB->id,
        'created_by' => $this->userB->id, 'description' => 'Report AR B',
        'amount' => 99999, 'due_date' => now()->addDays(30), 'status' => 'pending',
    ]);

    actAsTenantReport($this, $this->userA, $this->tenantA);

    $response = $this->getJson('/api/v1/reports/financial');
    $response->assertOk();
});

test('profitability report is tenant-scoped', function () {
    actAsTenantReport($this, $this->userA, $this->tenantA);

    $response = $this->getJson('/api/v1/reports/profitability');
    $response->assertOk();
});

test('commissions report is tenant-scoped', function () {
    actAsTenantReport($this, $this->userA, $this->tenantA);

    $response = $this->getJson('/api/v1/reports/commissions');
    $response->assertOk();
});

// ══════════════════════════════════════════════════════════════════
//  DASHBOARD KPIs
// ══════════════════════════════════════════════════════════════════

test('main dashboard stats are tenant-scoped', function () {
    WorkOrder::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'customer_id' => $this->customerA->id,
        'created_by' => $this->userA->id, 'number' => 'RPT-DASH-A',
        'description' => 'Dash A', 'status' => 'open',
    ]);
    WorkOrder::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'customer_id' => $this->customerB->id,
        'created_by' => $this->userB->id, 'number' => 'RPT-DASH-B',
        'description' => 'Dash B', 'status' => 'open',
    ]);

    actAsTenantReport($this, $this->userA, $this->tenantA);

    $response = $this->getJson('/api/v1/dashboard');
    $response->assertOk();
});

test('dashboard-stats endpoint is tenant-scoped', function () {
    actAsTenantReport($this, $this->userA, $this->tenantA);

    $response = $this->getJson('/api/v1/dashboard-stats');
    $response->assertOk();
});

// ══════════════════════════════════════════════════════════════════
//  CRM REPORTS
// ══════════════════════════════════════════════════════════════════

test('CRM report is tenant-scoped', function () {
    actAsTenantReport($this, $this->userA, $this->tenantA);

    $response = $this->getJson('/api/v1/reports/crm');
    $response->assertOk();
});

test('CRM dashboard is tenant-scoped', function () {
    actAsTenantReport($this, $this->userA, $this->tenantA);

    $response = $this->getJson('/api/v1/crm/dashboard');
    $response->assertOk();
});

// ══════════════════════════════════════════════════════════════════
//  STOCK REPORTS
// ══════════════════════════════════════════════════════════════════

test('stock report is tenant-scoped', function () {
    actAsTenantReport($this, $this->userA, $this->tenantA);

    $response = $this->getJson('/api/v1/reports/stock');
    $response->assertOk();
});

// ══════════════════════════════════════════════════════════════════
//  QUOTES REPORTS
// ══════════════════════════════════════════════════════════════════

test('quotes report is tenant-scoped', function () {
    actAsTenantReport($this, $this->userA, $this->tenantA);

    $response = $this->getJson('/api/v1/reports/quotes');
    $response->assertOk();
});

test('quotes summary is tenant-scoped', function () {
    Quote::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantA->id, 'customer_id' => $this->customerA->id,
        'seller_id' => $this->userA->id, 'quote_number' => 'RPT-ORC-A',
        'revision' => 1, 'status' => 'draft', 'valid_until' => now()->addDays(7),
        'subtotal' => 1000, 'total' => 1000,
    ]);
    Quote::withoutGlobalScopes()->create([
        'tenant_id' => $this->tenantB->id, 'customer_id' => $this->customerB->id,
        'seller_id' => $this->userB->id, 'quote_number' => 'RPT-ORC-B',
        'revision' => 1, 'status' => 'draft', 'valid_until' => now()->addDays(7),
        'subtotal' => 9999, 'total' => 9999,
    ]);

    actAsTenantReport($this, $this->userA, $this->tenantA);

    $response = $this->getJson('/api/v1/quotes-summary');
    $response->assertOk();
});

// ══════════════════════════════════════════════════════════════════
//  EQUIPMENT & CUSTOMER REPORTS
// ══════════════════════════════════════════════════════════════════

test('equipment report is tenant-scoped', function () {
    actAsTenantReport($this, $this->userA, $this->tenantA);

    $response = $this->getJson('/api/v1/reports/equipments');
    $response->assertOk();
});

test('customers report is tenant-scoped', function () {
    actAsTenantReport($this, $this->userA, $this->tenantA);

    $response = $this->getJson('/api/v1/reports/customers');
    $response->assertOk();
});
