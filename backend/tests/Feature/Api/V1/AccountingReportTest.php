<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\JourneyEntry;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountingReportTest extends TestCase
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
        app()->instance('current_tenant_id', $this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    // ── index ───────────────────────────────────────────────────

    public function test_index_returns_paginated_journey_entries(): void
    {
        JourneyEntry::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-01',
            'scheduled_hours' => 8,
            'worked_hours' => 9,
            'overtime_hours_50' => 1,
            'overtime_hours_100' => 0,
            'night_hours' => 0,
            'absence_hours' => 0,
            'hour_bank_balance' => 1,
        ]);

        $response = $this->getJson('/api/v1/hr/reports/accounting?start_date=2026-03-01&end_date=2026-03-31');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_index_requires_start_date(): void
    {
        $response = $this->getJson('/api/v1/hr/reports/accounting?end_date=2026-03-31');

        $response->assertStatus(422);
    }

    public function test_index_requires_end_date(): void
    {
        $response = $this->getJson('/api/v1/hr/reports/accounting?start_date=2026-03-01');

        $response->assertStatus(422);
    }

    public function test_index_validates_end_date_after_start_date(): void
    {
        $response = $this->getJson('/api/v1/hr/reports/accounting?start_date=2026-03-15&end_date=2026-03-01');

        $response->assertStatus(422);
    }

    public function test_index_filters_by_user_id(): void
    {
        $otherUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        JourneyEntry::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-05',
            'scheduled_hours' => 8,
            'worked_hours' => 8,
            'overtime_hours_50' => 0,
            'overtime_hours_100' => 0,
            'night_hours' => 0,
            'absence_hours' => 0,
            'hour_bank_balance' => 0,
        ]);

        JourneyEntry::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $otherUser->id,
            'date' => '2026-03-05',
            'scheduled_hours' => 8,
            'worked_hours' => 6,
            'overtime_hours_50' => 0,
            'overtime_hours_100' => 0,
            'night_hours' => 0,
            'absence_hours' => 2,
            'hour_bank_balance' => -2,
        ]);

        $response = $this->getJson('/api/v1/hr/reports/accounting?start_date=2026-03-01&end_date=2026-03-31&user_id='.$this->user->id);

        $response->assertOk();
        $data = $response->json('data');
        foreach ($data as $entry) {
            $this->assertEquals($this->user->id, $entry['user_id']);
        }
    }

    public function test_index_tenant_isolation(): void
    {
        $otherTenant = Tenant::factory()->create();

        JourneyEntry::create([
            'tenant_id' => $otherTenant->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-05',
            'scheduled_hours' => 8,
            'worked_hours' => 8,
            'overtime_hours_50' => 0,
            'overtime_hours_100' => 0,
            'night_hours' => 0,
            'absence_hours' => 0,
            'hour_bank_balance' => 0,
        ]);

        $response = $this->getJson('/api/v1/hr/reports/accounting?start_date=2026-03-01&end_date=2026-03-31');

        $response->assertOk();
        $data = $response->json('data');
        foreach ($data as $entry) {
            $this->assertEquals($this->tenant->id, $entry['tenant_id']);
        }
    }

    // ── export ──────────────────────────────────────────────────

    public function test_export_json_returns_data(): void
    {
        JourneyEntry::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-01',
            'scheduled_hours' => 8,
            'worked_hours' => 8,
            'overtime_hours_50' => 0,
            'overtime_hours_100' => 0,
            'night_hours' => 0,
            'absence_hours' => 0,
            'hour_bank_balance' => 0,
        ]);

        $response = $this->getJson('/api/v1/hr/reports/accounting/export?start_date=2026-03-01&end_date=2026-03-31&format=json');

        $response->assertOk()
            ->assertJsonStructure(['data']);
    }

    public function test_export_csv_returns_stream(): void
    {
        JourneyEntry::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-01',
            'scheduled_hours' => 8,
            'worked_hours' => 8,
            'overtime_hours_50' => 0,
            'overtime_hours_100' => 0,
            'night_hours' => 0,
            'absence_hours' => 0,
            'hour_bank_balance' => 0,
        ]);

        $response = $this->get('/api/v1/hr/reports/accounting/export?start_date=2026-03-01&end_date=2026-03-31&format=csv');

        $response->assertOk();
        $contentType = $response->headers->get('content-type');
        $this->assertStringStartsWith('text/csv', $contentType);
    }

    public function test_export_requires_format(): void
    {
        $response = $this->getJson('/api/v1/hr/reports/accounting/export?start_date=2026-03-01&end_date=2026-03-31');

        $response->assertStatus(422);
    }

    public function test_export_validates_format_values(): void
    {
        $response = $this->getJson('/api/v1/hr/reports/accounting/export?start_date=2026-03-01&end_date=2026-03-31&format=xlsx');

        $response->assertStatus(422);
    }
}
