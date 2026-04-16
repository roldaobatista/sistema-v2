<?php

namespace Tests\Feature;

use App\Events\TechnicianLocationUpdated;
use App\Models\Tenant;
use App\Models\TimeEntry;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

use function setPermissionsTeamId;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class UserLocationStatusTest extends TestCase
{
    public function test_user_can_update_location()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
        ]);
        $user->tenants()->attach($tenant->id);
        Permission::findOrCreate('technicians.schedule.view', 'web');

        app()->instance('current_tenant_id', $tenant->id);
        setPermissionsTeamId($tenant->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $user->givePermissionTo('technicians.schedule.view');

        Event::fake();

        $response = $this->actingAs($user)->postJson('/api/v1/user/location', [
            'latitude' => -23.550520,
            'longitude' => -46.633308,
        ]);

        Event::assertDispatched(TechnicianLocationUpdated::class);

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Localização atualizada com sucesso.',
                'location' => [
                    'lat' => -23.550520,
                    'lng' => -46.633308,
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'location_lat' => -23.550520,
            'location_lng' => -46.633308,
        ]);
    }

    public function test_user_without_operational_permission_cannot_update_location()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
        ]);
        $user->tenants()->attach($tenant->id);

        app()->instance('current_tenant_id', $tenant->id);
        setPermissionsTeamId($tenant->id);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Sanctum::actingAs($user, ['tenant:'.$tenant->id]);

        $response = $this->postJson('/api/v1/user/location', [
            'latitude' => -23.550520,
            'longitude' => -46.633308,
        ]);

        $response->assertForbidden();
    }

    public function test_technician_status_changes_automatically_on_time_entry()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['status' => 'available', 'tenant_id' => $tenant->id]);
        $user->tenants()->attach($tenant->id);

        $workOrder = WorkOrder::factory()->create(['tenant_id' => $tenant->id]);

        // 1. Start Travel -> Expect 'in_transit'
        $entry = TimeEntry::create([
            'tenant_id' => $user->tenant_id,
            'technician_id' => $user->id,
            'work_order_id' => $workOrder->id,
            'type' => TimeEntry::TYPE_TRAVEL,
            'started_at' => now(),
        ]);

        $this->assertEquals('in_transit', $user->fresh()->status);

        // 2. Stop Travel -> Expect 'available'
        $entry->update(['ended_at' => now()->addMinutes(30)]);
        $this->assertEquals('available', $user->fresh()->status);

        // 3. Start Work -> Expect 'working'
        $entry2 = TimeEntry::create([
            'tenant_id' => $user->tenant_id,
            'technician_id' => $user->id,
            'work_order_id' => $workOrder->id,
            'type' => TimeEntry::TYPE_WORK,
            'started_at' => now(),
        ]);

        $this->assertEquals('working', $user->fresh()->status);

        // 4. Stop Work -> Expect 'available'
        $entry2->update(['ended_at' => now()->addMinutes(60)]);
        $this->assertEquals('available', $user->fresh()->status);
    }
}
