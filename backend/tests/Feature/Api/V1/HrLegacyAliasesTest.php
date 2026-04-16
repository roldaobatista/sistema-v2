<?php

namespace Tests\Feature\Api\V1;

use App\Models\Tenant;
use App\Models\TimeClockEntry;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class HrLegacyAliasesTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_hr_employees_store_alias_creates_user_with_same_contract_as_iam(): void
    {
        Permission::findOrCreate('iam.user.create', 'web');
        $this->user->givePermissionTo('iam.user.create');

        $response = $this->postJson('/api/v1/hr/employees', [
            'name' => 'Colaborador RH',
            'email' => 'colaborador.rh@example.com',
            'password' => 'StrongPass123',
            'password_confirmation' => 'StrongPass123',
            'is_active' => true,
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', 'Colaborador RH')
            ->assertJsonPath('data.email', 'colaborador.rh@example.com');

        $this->assertDatabaseHas('users', [
            'email' => 'colaborador.rh@example.com',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_hr_time_clock_aliases_map_to_existing_clock_flows(): void
    {
        Permission::findOrCreate('hr.clock.manage', 'web');
        Permission::findOrCreate('hr.clock.view', 'web');
        $this->user->givePermissionTo(['hr.clock.manage', 'hr.clock.view']);

        $clockInResponse = $this->postJson('/api/v1/hr/time-clock', [
            'type' => 'regular',
            'latitude' => -23.5505,
            'longitude' => -46.6333,
            'selfie' => 'data:image/jpeg;base64,'.base64_encode('fake-selfie'),
        ]);

        $clockInResponse->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'clock_in']]);

        TimeClockEntry::query()->where('tenant_id', $this->tenant->id)->update([
            'clock_in' => now()->subHour(),
            'clock_out' => now(),
        ]);

        $listResponse = $this->getJson('/api/v1/hr/time-clock');

        $listResponse->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_hr_leave_requests_aliases_cover_index_store_and_approval_flow(): void
    {
        Permission::findOrCreate('hr.leave.view', 'web');
        Permission::findOrCreate('hr.leave.create', 'web');
        Permission::findOrCreate('hr.leave.approve', 'web');
        $this->user->givePermissionTo(['hr.leave.view', 'hr.leave.create', 'hr.leave.approve']);

        $storeResponse = $this->postJson('/api/v1/hr/leave-requests', [
            'user_id' => $this->user->id,
            'type' => 'vacation',
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-05',
            'reason' => 'Descanso',
        ]);

        $storeResponse->assertCreated()
            ->assertJsonPath('message', 'Afastamento solicitado');

        $leaveId = (int) $storeResponse->json('data.id');

        $indexResponse = $this->getJson('/api/v1/hr/leave-requests');

        $indexResponse->assertOk()
            ->assertJsonStructure(['data', 'meta']);

        $approveResponse = $this->postJson("/api/v1/hr/leave-requests/{$leaveId}/approve", ['approval_channel' => 'whatsapp', 'terms_accepted' => true]);

        $approveResponse->assertOk()
            ->assertJsonPath('message', 'Afastamento aprovado');

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leaveId,
            'status' => 'approved',
        ]);
    }

    public function test_hr_leave_requests_alias_requires_matching_permission(): void
    {
        $response = $this->getJson('/api/v1/hr/leave-requests');

        $response->assertForbidden();
    }
}
