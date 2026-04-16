<?php

namespace Tests\Unit\Policies;

use App\Models\EquipmentCalibration;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\EquipmentCalibrationPolicy;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class EquipmentCalibrationPolicyTest extends TestCase
{
    private Tenant $tenant;

    private User $userWithViewPermission;

    private User $userWithCreatePermission;

    private User $userWithUpdatePermission;

    private User $userWithDeletePermission;

    private User $userWithReadingPermission;

    private User $userWithoutPermission;

    private EquipmentCalibration $calibration;

    private EquipmentCalibrationPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        Event::fake();

        $this->tenant = Tenant::factory()->create();
        $this->policy = new EquipmentCalibrationPolicy;

        // Create permissions in DB for testing
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        foreach ([
            'equipments.calibration.view',
            'equipments.calibration.create',
            'equipments.calibration.update',
            'equipments.calibration.delete',
            'calibration.reading.create',
        ] as $perm) {
            Permission::findOrCreate($perm, 'web');
        }
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $this->userWithViewPermission = $this->createUserWithPermission('equipments.calibration.view');
        $this->userWithCreatePermission = $this->createUserWithPermission('equipments.calibration.create');
        $this->userWithUpdatePermission = $this->createUserWithPermission('equipments.calibration.update');
        $this->userWithDeletePermission = $this->createUserWithPermission('equipments.calibration.delete');
        $this->userWithReadingPermission = $this->createUserWithPermission('calibration.reading.create');
        $this->userWithoutPermission = $this->createUser();

        $this->calibration = EquipmentCalibration::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    private function createUser(): User
    {
        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        return $user;
    }

    private function createUserWithPermission(string $permission): User
    {
        $user = $this->createUser();
        setPermissionsTeamId($this->tenant->id);
        $user->givePermissionTo($permission);
        $user->unsetRelation('permissions');

        return $user;
    }

    private function createOtherTenantUser(string $permission): User
    {
        $otherTenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        $user->tenants()->attach($otherTenant->id, ['is_default' => true]);
        setPermissionsTeamId($otherTenant->id);
        $user->givePermissionTo($permission);
        $user->unsetRelation('permissions');

        return $user;
    }

    // ── viewAny ──

    public function test_user_with_view_permission_can_view_any(): void
    {
        $this->assertTrue($this->policy->viewAny($this->userWithViewPermission));
    }

    public function test_user_without_permission_cannot_view_any(): void
    {
        $this->assertNotTrue($this->policy->viewAny($this->userWithoutPermission));
    }

    // ── view ──

    public function test_user_with_view_permission_can_view_calibration_in_own_tenant(): void
    {
        $this->assertTrue($this->policy->view($this->userWithViewPermission, $this->calibration));
    }

    public function test_user_without_permission_cannot_view_calibration(): void
    {
        $this->assertNotTrue($this->policy->view($this->userWithoutPermission, $this->calibration));
    }

    public function test_user_from_different_tenant_cannot_view_calibration(): void
    {
        $otherUser = $this->createOtherTenantUser('equipments.calibration.view');
        $this->assertNotTrue($this->policy->view($otherUser, $this->calibration));
    }

    // ── create ──

    public function test_user_with_create_permission_can_create(): void
    {
        $this->assertTrue($this->policy->create($this->userWithCreatePermission));
    }

    public function test_user_without_permission_cannot_create(): void
    {
        $this->assertNotTrue($this->policy->create($this->userWithoutPermission));
    }

    // ── update ──

    public function test_user_with_update_permission_can_update_in_own_tenant(): void
    {
        $this->assertTrue($this->policy->update($this->userWithUpdatePermission, $this->calibration));
    }

    public function test_user_without_permission_cannot_update(): void
    {
        $this->assertNotTrue($this->policy->update($this->userWithoutPermission, $this->calibration));
    }

    public function test_user_from_different_tenant_cannot_update_even_with_permission(): void
    {
        $otherUser = $this->createOtherTenantUser('equipments.calibration.update');
        $this->assertNotTrue($this->policy->update($otherUser, $this->calibration));
    }

    // ── delete ──

    public function test_user_with_delete_permission_can_delete_in_own_tenant(): void
    {
        $this->assertTrue($this->policy->delete($this->userWithDeletePermission, $this->calibration));
    }

    public function test_user_without_permission_cannot_delete(): void
    {
        $this->assertNotTrue($this->policy->delete($this->userWithoutPermission, $this->calibration));
    }

    public function test_user_from_different_tenant_cannot_delete(): void
    {
        $otherUser = $this->createOtherTenantUser('equipments.calibration.delete');
        $this->assertNotTrue($this->policy->delete($otherUser, $this->calibration));
    }

    // ── generateCertificate ──

    public function test_user_with_reading_permission_can_generate_certificate_in_own_tenant(): void
    {
        $this->assertTrue($this->policy->generateCertificate($this->userWithReadingPermission, $this->calibration));
    }

    public function test_user_without_permission_cannot_generate_certificate(): void
    {
        $this->assertNotTrue($this->policy->generateCertificate($this->userWithoutPermission, $this->calibration));
    }

    public function test_user_from_different_tenant_cannot_generate_certificate(): void
    {
        $otherUser = $this->createOtherTenantUser('calibration.reading.create');
        $this->assertNotTrue($this->policy->generateCertificate($otherUser, $this->calibration));
    }
}
