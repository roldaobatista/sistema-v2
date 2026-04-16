<?php

/**
 * Caching & Idempotency Tests
 *
 * Validates that repeated identical requests produce consistent results
 * and that second requests are no more expensive than the first (or cheaper
 * if application-level caching is in place).
 *
 * Since the current codebase does not use explicit Cache::remember() on
 * most controller endpoints, these tests primarily confirm:
 *  - Responses are deterministic (idempotent GETs).
 *  - Query counts on the second call are not worse than the first.
 *  - Data modifications correctly invalidate stale state.
 */

use App\Models\AccountReceivable;
use App\Models\CrmDeal;
use App\Models\CrmPipeline;
use App\Models\CrmPipelineStage;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Quote;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\DB;

// ─── Helpers ─────────────────────────────────────────────────────────

function startQueryCounter(): void
{
    DB::flushQueryLog();
    DB::enableQueryLog();
}

function captureQueryCount(): int
{
    return count(DB::getQueryLog());
}

// ─── Dashboard stats: second call is not more expensive ─────────────

test('dashboard stats second call is not more expensive than the first', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    WorkOrder::factory()->count(20)->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
    ]);

    startQueryCounter();
    $this->getJson('/api/v1/dashboard-stats')->assertOk();
    $firstCallQueries = captureQueryCount();

    startQueryCounter();
    $this->getJson('/api/v1/dashboard-stats')->assertOk();
    $secondCallQueries = captureQueryCount();

    // Second call should be equal or cheaper (if cached)
    expect($secondCallQueries)->toBeLessThanOrEqual(
        $firstCallQueries + 2, // allow small variance for session/auth overhead
        'Second dashboard call should not execute significantly more queries'
    );
});

// ─── Dashboard stats return consistent data ─────────────────────────

test('dashboard stats return consistent data on repeated calls', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    WorkOrder::factory()->count(5)->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
    ]);

    $response1 = $this->getJson('/api/v1/dashboard-stats');
    $response2 = $this->getJson('/api/v1/dashboard-stats');

    $response1->assertOk();
    $response2->assertOk();

    expect($response1->json('data'))->toEqual($response2->json('data'));
});

// ─── Customer list: idempotent response ─────────────────────────────

test('customer list returns identical data on repeated calls', function () {
    Customer::factory()->count(10)->create(['tenant_id' => $this->tenant->id]);

    $response1 = $this->getJson('/api/v1/customers?per_page=10');
    $response2 = $this->getJson('/api/v1/customers?per_page=10');

    $response1->assertOk();
    $response2->assertOk();

    expect($response1->json('data'))->toEqual($response2->json('data'));
});

// ─── Customer list: second call is not more expensive ───────────────

test('customer list second call has similar query count', function () {
    Customer::factory()->count(20)->create(['tenant_id' => $this->tenant->id]);

    startQueryCounter();
    $this->getJson('/api/v1/customers')->assertOk();
    $first = captureQueryCount();

    startQueryCounter();
    $this->getJson('/api/v1/customers')->assertOk();
    $second = captureQueryCount();

    expect($second)->toBeLessThanOrEqual($first + 2);
});

// ─── Work-order list: second call consistent ────────────────────────

test('work order list second call has similar query count', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    WorkOrder::factory()->count(20)->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
    ]);

    startQueryCounter();
    $this->getJson('/api/v1/work-orders')->assertOk();
    $first = captureQueryCount();

    startQueryCounter();
    $this->getJson('/api/v1/work-orders')->assertOk();
    $second = captureQueryCount();

    expect($second)->toBeLessThanOrEqual($first + 2);
});

// ─── Data mutation invalidates stale reads ──────────────────────────

test('new customer appears in list immediately after creation', function () {
    Customer::factory()->count(5)->create(['tenant_id' => $this->tenant->id]);

    $before = $this->getJson('/api/v1/customers')->assertOk();
    $countBefore = $before->json('meta.total') ?? count($before->json('data'));

    Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'New Customer After Cache',
    ]);

    $after = $this->getJson('/api/v1/customers')->assertOk();
    $countAfter = $after->json('meta.total') ?? count($after->json('data'));

    expect($countAfter)->toBeGreaterThan($countBefore);
});

// ─── Receivable payment updates the list ────────────────────────────

test('receivable status updates after payment', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    $receivable = AccountReceivable::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'created_by' => $this->user->id,
        'amount' => 100.00,
        'amount_paid' => 0,
        'status' => AccountReceivable::STATUS_PENDING,
    ]);

    // Read before payment
    $beforeResponse = $this->getJson("/api/v1/accounts-receivable/{$receivable->id}");
    $beforeResponse->assertOk();

    // Make payment
    $this->postJson("/api/v1/accounts-receivable/{$receivable->id}/pay", [
        'amount' => 100,
        'payment_date' => now()->toDateString(),
        'payment_method' => 'pix',
    ]);

    // Read after payment -- should reflect updated state
    $afterResponse = $this->getJson("/api/v1/accounts-receivable/{$receivable->id}");
    $afterResponse->assertOk();

    $receivable->refresh();
    expect((float) $receivable->amount_paid)->toBe(100.00);
});

// ─── Quote list: idempotent ─────────────────────────────────────────

test('quote list returns identical data on repeated calls', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    Quote::factory()->count(10)->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'seller_id' => $this->user->id,
    ]);

    $response1 = $this->getJson('/api/v1/quotes?per_page=10');
    $response2 = $this->getJson('/api/v1/quotes?per_page=10');

    $response1->assertOk();
    $response2->assertOk();

    expect($response1->json('data'))->toEqual($response2->json('data'));
});

// ─── Equipment list: second call consistent ─────────────────────────

test('equipment list second call has similar query count', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    Equipment::factory()->count(20)->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
    ]);

    startQueryCounter();
    $this->getJson('/api/v1/equipments')->assertOk();
    $first = captureQueryCount();

    startQueryCounter();
    $this->getJson('/api/v1/equipments')->assertOk();
    $second = captureQueryCount();

    expect($second)->toBeLessThanOrEqual($first + 2);
});

// ─── CRM deals: second call consistent ──────────────────────────────

test('CRM deals list second call has similar query count', function () {
    $customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
    $pipeline = CrmPipeline::factory()->create(['tenant_id' => $this->tenant->id]);
    $stage = CrmPipelineStage::factory()->create(['pipeline_id' => $pipeline->id]);

    CrmDeal::factory()->count(20)->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $customer->id,
        'pipeline_id' => $pipeline->id,
        'stage_id' => $stage->id,
    ]);

    startQueryCounter();
    $this->getJson('/api/v1/crm/deals')->assertOk();
    $first = captureQueryCount();

    startQueryCounter();
    $this->getJson('/api/v1/crm/deals')->assertOk();
    $second = captureQueryCount();

    expect($second)->toBeLessThanOrEqual($first + 2);
});
