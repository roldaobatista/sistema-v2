<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\GeofenceLocation;
use App\Models\Holiday;
use App\Models\JourneyRule;
use App\Models\LeaveRequest;
use App\Models\OnboardingChecklist;
use App\Models\OnboardingChecklistItem;
use App\Models\OnboardingTemplate;
use App\Models\Tenant;
use App\Models\TimeClockEntry;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HRAdvancedControllerTest extends TestCase
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

    // ─── GEOFENCES ─────────────────────────────────────────────────

    public function test_index_geofences_returns_list(): void
    {
        GeofenceLocation::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Office HQ',
            'latitude' => -23.5505,
            'longitude' => -46.6333,
            'radius_meters' => 200,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/hr/geofences');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_store_geofence_creates_record(): void
    {
        $response = $this->postJson('/api/v1/hr/geofences', [
            'name' => 'Factory Floor',
            'latitude' => -16.4613,
            'longitude' => -54.6372,
            'radius_meters' => 500,
            'is_active' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Geofence criado');

        $this->assertDatabaseHas('geofence_locations', [
            'name' => 'Factory Floor',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_store_geofence_validation_errors(): void
    {
        $response = $this->postJson('/api/v1/hr/geofences', [
            'name' => 'Test',
            // missing required fields
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['latitude', 'longitude', 'radius_meters']);
    }

    public function test_update_geofence_modifies_record(): void
    {
        $geofence = GeofenceLocation::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Old Name',
            'latitude' => -23.55,
            'longitude' => -46.63,
            'radius_meters' => 200,
            'is_active' => true,
        ]);

        $response = $this->putJson("/api/v1/hr/geofences/{$geofence->id}", [
            'name' => 'Updated HQ',
            'radius_meters' => 300,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Geofence atualizado');

        $this->assertDatabaseHas('geofence_locations', [
            'id' => $geofence->id,
            'name' => 'Updated HQ',
            'radius_meters' => 300,
        ]);
    }

    public function test_destroy_geofence_deletes_record(): void
    {
        $geofence = GeofenceLocation::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'To Remove',
            'latitude' => -23.55,
            'longitude' => -46.63,
            'radius_meters' => 100,
            'is_active' => true,
        ]);

        $response = $this->deleteJson("/api/v1/hr/geofences/{$geofence->id}");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Geofence removido');

        $this->assertDatabaseMissing('geofence_locations', ['id' => $geofence->id]);
    }

    public function test_geofence_tenant_isolation(): void
    {
        $otherTenant = Tenant::factory()->create();

        $foreign = GeofenceLocation::create([
            'tenant_id' => $otherTenant->id,
            'name' => 'Foreign Geofence',
            'latitude' => -23.55,
            'longitude' => -46.63,
            'radius_meters' => 100,
            'is_active' => true,
        ]);

        $response = $this->putJson("/api/v1/hr/geofences/{$foreign->id}", [
            'name' => 'Hacked',
        ]);

        $response->assertStatus(404);
    }

    // ─── HOLIDAYS ──────────────────────────────────────────────────

    public function test_index_holidays_returns_list(): void
    {
        Holiday::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Christmas',
            'date' => '2026-12-25',
            'is_national' => true,
            'is_recurring' => true,
        ]);

        $response = $this->getJson('/api/v1/hr/holidays');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_store_holiday_creates_record(): void
    {
        $response = $this->postJson('/api/v1/hr/holidays', [
            'name' => 'Company Anniversary',
            'date' => '2026-06-15',
            'is_national' => false,
            'is_recurring' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Feriado cadastrado');

        $this->assertDatabaseHas('holidays', [
            'name' => 'Company Anniversary',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_store_holiday_validation_requires_name_and_date(): void
    {
        $response = $this->postJson('/api/v1/hr/holidays', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'date']);
    }

    public function test_update_holiday_modifies_record(): void
    {
        $holiday = Holiday::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Old Holiday',
            'date' => '2026-05-01',
            'is_national' => false,
        ]);

        $response = $this->putJson("/api/v1/hr/holidays/{$holiday->id}", [
            'name' => 'Updated Holiday',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Feriado atualizado');
    }

    public function test_destroy_holiday_removes_record(): void
    {
        $holiday = Holiday::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Temp Holiday',
            'date' => '2026-08-01',
        ]);

        $response = $this->deleteJson("/api/v1/hr/holidays/{$holiday->id}");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Feriado removido');

        $this->assertDatabaseMissing('holidays', ['id' => $holiday->id]);
    }

    public function test_import_national_holidays_creates_records(): void
    {
        $response = $this->postJson('/api/v1/hr/holidays/import-national', [
            'year' => 2026,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.year', 2026)
            ->assertJsonPath('data.total', 8);

        $this->assertDatabaseHas('holidays', [
            'tenant_id' => $this->tenant->id,
            'name' => 'Natal',
            'date' => '2026-12-25',
        ]);
    }

    public function test_import_national_holidays_is_idempotent(): void
    {
        $this->postJson('/api/v1/hr/holidays/import-national', ['year' => 2026]);
        $response = $this->postJson('/api/v1/hr/holidays/import-national', ['year' => 2026]);

        $response->assertStatus(200)
            ->assertJsonPath('data.created', 0);
    }

    // ─── LEAVES ────────────────────────────────────────────────────

    public function test_index_leaves_returns_paginated(): void
    {
        LeaveRequest::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'type' => 'vacation',
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-15',
            'days_count' => 15,
            'status' => 'pending',
        ]);

        $response = $this->getJson('/api/v1/hr/leaves');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_store_leave_creates_request(): void
    {
        $response = $this->postJson('/api/v1/hr/leaves', [
            'user_id' => $this->user->id,
            'type' => 'vacation',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-15',
            'reason' => 'Family trip',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Afastamento solicitado');

        $this->assertDatabaseHas('leave_requests', [
            'user_id' => $this->user->id,
            'type' => 'vacation',
            'status' => 'pending',
        ]);
    }

    public function test_store_leave_rejects_overlapping(): void
    {
        LeaveRequest::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'type' => 'vacation',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-15',
            'days_count' => 15,
            'status' => 'approved',
        ]);

        $response = $this->postJson('/api/v1/hr/leaves', [
            'user_id' => $this->user->id,
            'type' => 'personal',
            'start_date' => '2026-07-10',
            'end_date' => '2026-07-20',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Já existe afastamento neste período');
    }

    public function test_approve_leave_changes_status(): void
    {
        $leave = LeaveRequest::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'type' => 'personal',
            'start_date' => '2026-08-01',
            'end_date' => '2026-08-05',
            'days_count' => 5,
            'status' => 'pending',
        ]);

        $response = $this->postJson("/api/v1/hr/leaves/{$leave->id}/approve", ['approval_channel' => 'whatsapp', 'terms_accepted' => true]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Afastamento aprovado');

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leave->id,
            'status' => 'approved',
        ]);
    }

    public function test_approve_leave_fails_if_not_pending(): void
    {
        $leave = LeaveRequest::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'type' => 'personal',
            'start_date' => '2026-08-10',
            'end_date' => '2026-08-15',
            'days_count' => 5,
            'status' => 'approved',
        ]);

        $response = $this->postJson("/api/v1/hr/leaves/{$leave->id}/approve", ['approval_channel' => 'whatsapp', 'terms_accepted' => true]);

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Afastamento não está pendente');
    }

    public function test_reject_leave_changes_status_with_reason(): void
    {
        $leave = LeaveRequest::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'type' => 'vacation',
            'start_date' => '2026-09-01',
            'end_date' => '2026-09-10',
            'days_count' => 10,
            'status' => 'pending',
        ]);

        $response = $this->postJson("/api/v1/hr/leaves/{$leave->id}/reject", [
            'reason' => 'Insufficient balance',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Afastamento rejeitado');

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leave->id,
            'status' => 'rejected',
            'rejection_reason' => 'Insufficient balance',
        ]);
    }

    // ─── ONBOARDING TEMPLATES ──────────────────────────────────────

    public function test_index_templates_returns_list(): void
    {
        OnboardingTemplate::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'New Employee',
            'type' => 'onboarding',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/v1/hr/onboarding/templates');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_store_template_creates_record(): void
    {
        $response = $this->postJson('/api/v1/hr/onboarding/templates', [
            'name' => 'Technician Onboarding',
            'type' => 'onboarding',
            'tasks' => ['Setup workstation', 'Safety orientation', 'Tool assignment'],
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Template criado');

        $this->assertDatabaseHas('onboarding_templates', [
            'name' => 'Technician Onboarding',
            'tenant_id' => $this->tenant->id,
            'type' => 'admission',
        ]);
    }

    public function test_destroy_template_prevents_if_has_checklists(): void
    {
        $template = OnboardingTemplate::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Linked Template',
            'type' => 'onboarding',
            'is_active' => true,
        ]);

        OnboardingChecklist::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'onboarding_template_id' => $template->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        $response = $this->deleteJson("/api/v1/hr/onboarding/templates/{$template->id}");

        $response->assertStatus(409);
    }

    public function test_start_onboarding_creates_checklist_with_items(): void
    {
        $template = OnboardingTemplate::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Starter Template',
            'type' => 'onboarding',
            'is_active' => true,
            'default_tasks' => [
                ['title' => 'Setup laptop', 'description' => 'IT setup'],
                ['title' => 'HR paperwork', 'description' => null],
            ],
        ]);

        $response = $this->postJson('/api/v1/hr/onboarding/start', [
            'template_id' => $template->id,
            'user_id' => $this->user->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Onboarding iniciado');

        $this->assertDatabaseHas('onboarding_checklists', [
            'onboarding_template_id' => $template->id,
            'user_id' => $this->user->id,
            'status' => 'in_progress',
        ]);

        $this->assertDatabaseHas('onboarding_checklist_items', [
            'title' => 'Setup laptop',
        ]);
    }

    public function test_complete_checklist_item_marks_done(): void
    {
        $template = OnboardingTemplate::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Test',
            'type' => 'onboarding',
            'is_active' => true,
        ]);

        $checklist = OnboardingChecklist::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'onboarding_template_id' => $template->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        $item = OnboardingChecklistItem::create([
            'onboarding_checklist_id' => $checklist->id,
            'title' => 'Task A',
            'order' => 0,
            'is_completed' => false,
        ]);

        $response = $this->postJson("/api/v1/hr/onboarding/items/{$item->id}/complete", [
            'is_completed' => true,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('onboarding_checklist_items', [
            'id' => $item->id,
            'is_completed' => true,
        ]);
    }

    public function test_all_items_completed_auto_completes_checklist(): void
    {
        $template = OnboardingTemplate::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Auto Complete',
            'type' => 'onboarding',
            'is_active' => true,
        ]);

        $checklist = OnboardingChecklist::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'onboarding_template_id' => $template->id,
            'status' => 'in_progress',
            'started_at' => now(),
        ]);

        $item = OnboardingChecklistItem::create([
            'onboarding_checklist_id' => $checklist->id,
            'title' => 'Only Task',
            'order' => 0,
            'is_completed' => false,
        ]);

        $this->postJson("/api/v1/hr/onboarding/items/{$item->id}/complete", [
            'is_completed' => true,
        ]);

        $this->assertDatabaseHas('onboarding_checklists', [
            'id' => $checklist->id,
            'status' => 'completed',
        ]);
    }

    // ─── ADVANCED DASHBOARD ────────────────────────────────────────

    public function test_advanced_dashboard_returns_counts(): void
    {
        $response = $this->getJson('/api/v1/hr/advanced/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'pending_clock_approvals',
                    'pending_adjustments',
                    'pending_leaves',
                    'expiring_documents',
                    'expired_documents',
                    'active_onboardings',
                    'active_clocks_today',
                ],
            ]);
    }

    // ─── JOURNEY RULES ─────────────────────────────────────────────

    public function test_index_journey_rules_returns_list(): void
    {
        JourneyRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Standard 8h',
            'daily_hours' => 8,
            'weekly_hours' => 44,
            'is_default' => true,
        ]);

        $response = $this->getJson('/api/v1/hr/journey-rules');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    public function test_store_journey_rule_creates_record(): void
    {
        $response = $this->postJson('/api/v1/hr/journey-rules', [
            'name' => 'Part-time 6h',
            'daily_hours' => 6,
            'weekly_hours' => 30,
            'overtime_weekday_pct' => 50,
            'overtime_weekend_pct' => 100,
            'overtime_holiday_pct' => 100,
            'is_default' => false,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('message', 'Regra de jornada criada');
    }

    public function test_store_journey_rule_default_unsets_others(): void
    {
        $existing = JourneyRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Old Default',
            'daily_hours' => 8,
            'weekly_hours' => 44,
            'is_default' => true,
        ]);

        $this->postJson('/api/v1/hr/journey-rules', [
            'name' => 'New Default',
            'daily_hours' => 8,
            'weekly_hours' => 40,
            'overtime_weekday_pct' => 50,
            'overtime_weekend_pct' => 100,
            'overtime_holiday_pct' => 100,
            'is_default' => true,
        ]);

        $existing->refresh();
        $this->assertFalse((bool) $existing->is_default);
    }

    public function test_destroy_journey_rule_removes_record(): void
    {
        $rule = JourneyRule::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'To Delete',
            'daily_hours' => 8,
            'weekly_hours' => 44,
        ]);

        $response = $this->deleteJson("/api/v1/hr/journey-rules/{$rule->id}");

        $response->assertStatus(200)
            ->assertJsonPath('message', 'Regra removida');

        $this->assertSoftDeleted('journey_rules', ['id' => $rule->id]);
    }

    // ─── CLOCK STATUS ──────────────────────────────────────────────

    public function test_current_clock_status_not_clocked_in(): void
    {
        $response = $this->getJson('/api/v1/hr/advanced/clock/status');

        $response->assertStatus(200)
            ->assertJsonPath('data.clocked_in', false)
            ->assertJsonPath('data.on_break', false);
    }

    public function test_current_clock_status_clocked_in(): void
    {
        TimeClockEntry::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'clock_in' => now(),
            'type' => 'regular',
        ]);

        $response = $this->getJson('/api/v1/hr/advanced/clock/status');

        $response->assertStatus(200)
            ->assertJsonPath('data.clocked_in', true);
    }

    // ─── USER OPTIONS ──────────────────────────────────────────────

    public function test_user_options_returns_list(): void
    {
        $response = $this->getJson('/api/v1/hr/users/options');

        $response->assertStatus(200)
            ->assertJsonStructure(['data']);
    }

    // ─── VACATION BALANCES ─────────────────────────────────────────

    public function test_vacation_balances_returns_paginated(): void
    {
        $response = $this->getJson('/api/v1/hr/vacation-balances');

        $response->assertStatus(200)
            ->assertJsonStructure(['data', 'meta']);
    }
}
