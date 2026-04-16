<?php

namespace Tests\Feature\Api\V1;

use App\Models\Tenant;
use App\Models\TimeClockEntry;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class FiscalAccessControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        $this->tenant = Tenant::factory()->create([
            'document' => '12345678000190',
        ]);
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        app()->instance('current_tenant_id', $this->tenant->id);

        $this->user->assignRole('super_admin');
    }

    // ═══════════════════════════════════════════════════════════════
    // GET /api/v1/hr/fiscal/afd — Export AFD
    // ═══════════════════════════════════════════════════════════════

    public function test_export_afd_returns_200_with_text_plain(): void
    {
        TimeClockEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'clock_in' => Carbon::parse('2026-03-18 08:00:00'),
            'clock_out' => Carbon::parse('2026-03-18 17:00:00'),
            'type' => 'regular',
            'approval_status' => 'approved',
            'nsr' => 1,
        ]);

        $response = $this->actingAs($this->user)->get('/api/v1/hr/fiscal/afd?start_date=2026-03-01&end_date=2026-03-31');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/plain', $response->headers->get('Content-Type'));
    }

    public function test_export_afd_has_content_disposition_header(): void
    {
        TimeClockEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'clock_in' => Carbon::parse('2026-03-18 08:00:00'),
            'clock_out' => Carbon::parse('2026-03-18 17:00:00'),
            'type' => 'regular',
            'approval_status' => 'approved',
            'nsr' => 1,
        ]);

        $response = $this->actingAs($this->user)->get('/api/v1/hr/fiscal/afd?start_date=2026-03-01&end_date=2026-03-31');

        $response->assertStatus(200);
        $this->assertNotNull($response->headers->get('Content-Disposition'));
        $this->assertStringContainsString('AFD_', $response->headers->get('Content-Disposition'));
    }

    public function test_export_afd_has_document_signature_header(): void
    {
        TimeClockEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'clock_in' => Carbon::parse('2026-03-18 08:00:00'),
            'clock_out' => Carbon::parse('2026-03-18 17:00:00'),
            'type' => 'regular',
            'approval_status' => 'approved',
            'nsr' => 1,
        ]);

        $response = $this->actingAs($this->user)->get('/api/v1/hr/fiscal/afd?start_date=2026-03-01&end_date=2026-03-31');

        $response->assertStatus(200);
        $this->assertNotNull($response->headers->get('X-Document-Signature'));
    }

    public function test_export_afd_requires_start_date(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/hr/fiscal/afd?end_date=2026-03-31');

        $response->assertStatus(422);
    }

    public function test_export_afd_requires_end_date(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/hr/fiscal/afd?start_date=2026-03-01');

        $response->assertStatus(422);
    }

    public function test_export_afd_validates_end_date_after_start_date(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/hr/fiscal/afd?start_date=2026-03-31&end_date=2026-03-01');

        $response->assertStatus(422);
    }

    // ═══════════════════════════════════════════════════════════════
    // GET /api/v1/hr/fiscal/aep/{userId}/{year}/{month} — Espelho
    // ═══════════════════════════════════════════════════════════════

    public function test_export_aep_returns_200_with_json(): void
    {
        $response = $this->actingAs($this->user)->getJson("/api/v1/hr/fiscal/aep/{$this->user->id}/2026/3");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'employee' => ['id', 'name'],
                    'period' => ['year', 'month', 'month_name'],
                    'days',
                    'summary' => ['total_work_days', 'total_hours', 'total_minutes'],
                ],
            ]);
    }

    public function test_export_aep_returns_correct_employee(): void
    {
        $response = $this->actingAs($this->user)->getJson("/api/v1/hr/fiscal/aep/{$this->user->id}/2026/3");

        $response->assertStatus(200)
            ->assertJsonPath('data.employee.id', $this->user->id)
            ->assertJsonPath('data.period.year', 2026)
            ->assertJsonPath('data.period.month', 3);
    }

    public function test_export_aep_includes_clock_entries_in_summary(): void
    {
        TimeClockEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'clock_in' => Carbon::parse('2026-03-10 08:00:00'),
            'clock_out' => Carbon::parse('2026-03-10 17:00:00'),
            'type' => 'regular',
        ]);

        $response = $this->actingAs($this->user)->getJson("/api/v1/hr/fiscal/aep/{$this->user->id}/2026/3");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertGreaterThan(0, $data['summary']['total_work_days']);
        $this->assertGreaterThan(0, $data['summary']['total_hours']);
    }

    public function test_export_aep_returns_all_days_of_march(): void
    {
        $response = $this->actingAs($this->user)->getJson("/api/v1/hr/fiscal/aep/{$this->user->id}/2026/3");

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(31, $data['days']);
    }

    public function test_export_aep_returns_correct_month_name(): void
    {
        $response = $this->actingAs($this->user)->getJson("/api/v1/hr/fiscal/aep/{$this->user->id}/2026/3");

        $response->assertStatus(200)
            ->assertJsonPath('data.period.month_name', 'Março');
    }

    // ═══════════════════════════════════════════════════════════════
    // GET /api/v1/hr/fiscal/integrity — Hash chain verification
    // ═══════════════════════════════════════════════════════════════

    public function test_verify_integrity_returns_200_with_chain_intact_field(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/hr/fiscal/integrity');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'total_entries',
                    'valid_entries',
                    'invalid_entries',
                    'chain_intact',
                    'violations',
                ],
            ]);
    }

    public function test_verify_integrity_chain_intact_when_no_entries(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/hr/fiscal/integrity');

        $response->assertStatus(200)
            ->assertJsonPath('data.chain_intact', true)
            ->assertJsonPath('data.total_entries', 0);
    }

    public function test_verify_integrity_accepts_user_id_filter(): void
    {
        $response = $this->actingAs($this->user)->getJson("/api/v1/hr/fiscal/integrity?user_id={$this->user->id}");

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['chain_intact']]);
    }

    public function test_verify_integrity_accepts_date_range_filters(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/hr/fiscal/integrity?start_date=2026-03-01&end_date=2026-03-31');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => ['chain_intact']]);
    }

    public function test_verify_integrity_validates_end_date_after_start_date(): void
    {
        $response = $this->actingAs($this->user)->getJson('/api/v1/hr/fiscal/integrity?start_date=2026-03-31&end_date=2026-03-01');

        $response->assertStatus(422);
    }

    // ═══════════════════════════════════════════════════════════════
    // Authentication — 401 Unauthenticated
    // ═══════════════════════════════════════════════════════════════

    public function test_unauthenticated_afd_returns_401(): void
    {
        $response = $this->getJson('/api/v1/hr/fiscal/afd?start_date=2026-03-01&end_date=2026-03-31');

        $response->assertStatus(401);
    }

    public function test_unauthenticated_aep_returns_401(): void
    {
        $response = $this->getJson("/api/v1/hr/fiscal/aep/{$this->user->id}/2026/3");

        $response->assertStatus(401);
    }

    public function test_unauthenticated_integrity_returns_401(): void
    {
        $response = $this->getJson('/api/v1/hr/fiscal/integrity');

        $response->assertStatus(401);
    }

    // ═══════════════════════════════════════════════════════════════
    // Authorization — 403 Forbidden
    // ═══════════════════════════════════════════════════════════════

    public function test_without_permission_afd_returns_403(): void
    {
        $userWithout = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $userWithout->tenants()->attach($this->tenant->id, ['is_default' => true]);

        $response = $this->actingAs($userWithout)->getJson('/api/v1/hr/fiscal/afd?start_date=2026-03-01&end_date=2026-03-31');

        $response->assertStatus(403);
    }

    public function test_without_permission_aep_returns_403(): void
    {
        $userWithout = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $userWithout->tenants()->attach($this->tenant->id, ['is_default' => true]);

        $response = $this->actingAs($userWithout)->getJson("/api/v1/hr/fiscal/aep/{$this->user->id}/2026/3");

        $response->assertStatus(403);
    }

    public function test_without_permission_integrity_returns_403(): void
    {
        $userWithout = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $userWithout->tenants()->attach($this->tenant->id, ['is_default' => true]);

        $response = $this->actingAs($userWithout)->getJson('/api/v1/hr/fiscal/integrity');

        $response->assertStatus(403);
    }
}
