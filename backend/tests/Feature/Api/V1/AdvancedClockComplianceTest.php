<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\TimeClockAuditLog;
use App\Models\TimeClockEntry;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdvancedClockComplianceTest extends TestCase
{
    protected User $user;

    protected Tenant $tenant;

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

    public function test_clock_in_requires_gps_coordinates(): void
    {

        $response = $this->postJson('/api/v1/hr/advanced/clock-in', [
            'selfie' => base64_encode('fake-selfie-data'),
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['latitude', 'longitude']);
    }

    public function test_clock_in_requires_selfie(): void
    {
        $response = $this->postJson('/api/v1/hr/advanced/clock-in', [
            'latitude' => -23.5505,
            'longitude' => -46.6333,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['selfie']);
    }

    public function test_clock_in_with_valid_data_succeeds(): void
    {
        \Storage::fake('public');

        $selfie = UploadedFile::fake()->image('selfie.jpg', 200, 200);

        $response = $this->post('/api/v1/hr/advanced/clock-in', [
            'latitude' => -23.5505,
            'longitude' => -46.6333,
            'selfie' => $selfie,
            'clock_method' => 'selfie',
        ], ['Accept' => 'application/json']);

        $this->assertContains($response->status(), [200, 201],
            "Expected 200/201 but got {$response->status()}: {$response->content()}");
    }

    public function test_clock_in_without_any_data_fails(): void
    {
        $response = $this->postJson('/api/v1/hr/advanced/clock-in', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['latitude', 'longitude', 'selfie']);
    }

    public function test_verify_integrity_endpoint(): void
    {
        $response = $this->postJson('/api/v1/hr/compliance/verify-integrity', [
            'start_date' => now()->subMonth()->format('Y-m-d'),
            'end_date' => now()->format('Y-m-d'),
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'is_valid', 'total_records', 'valid_count', 'invalid_count', 'nsr_gaps',
        ]);
    }

    public function test_confirm_entry_endpoint(): void
    {
        $entry = TimeClockEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'clock_in' => now()->subHours(2),
        ]);

        $response = $this->postJson("/api/v1/hr/compliance/confirm-entry/{$entry->id}", [
            'method' => 'manual',
        ]);

        $response->assertOk();
        $response->assertJsonStructure(['confirmation_hash', 'confirmed_at']);
    }

    public function test_confirm_entry_cannot_confirm_twice(): void
    {
        $entry = TimeClockEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'confirmed_at' => now(),
            'employee_confirmation_hash' => str_repeat('a', 64),
            'confirmation_method' => 'manual',
        ]);

        $response = $this->postJson("/api/v1/hr/compliance/confirm-entry/{$entry->id}", [
            'method' => 'manual',
        ]);

        $response->assertStatus(422);
    }

    public function test_audit_trail_report_endpoint(): void
    {
        // Create audit log
        TimeClockAuditLog::log('test_action', null, null, ['test' => true]);

        $response = $this->getJson('/api/v1/hr/audit-trail/report?'.http_build_query([
            'start_date' => now()->subDay()->format('Y-m-d'),
            'end_date' => now()->addDay()->format('Y-m-d'),
        ]));

        $response->assertOk();
        $response->assertJsonStructure(['data']);
    }

    public function test_tampering_attempts_endpoint(): void
    {
        $response = $this->getJson('/api/v1/hr/security/tampering-attempts');

        $response->assertOk();
        $response->assertJsonStructure(['data']);
    }

    public function test_tax_tables_endpoint(): void
    {
        $response = $this->getJson('/api/v1/hr/tax-tables?year=2026');

        $response->assertOk();
        $response->assertJsonStructure(['inss', 'irrf']);
    }
}
