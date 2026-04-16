<?php

namespace Tests\Unit\Http\Requests\Hr;

use App\Http\Requests\HR\TimesheetReportRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class TimesheetReportRequestTest extends TestCase
{
    public function test_authorizes_user_with_schedule_view_permission(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->userForTenant($tenant, ['hr.schedule.view']);

        $request = TimesheetReportRequest::create('/api/v1/reports/peripheral/timesheet', 'GET');
        $request->setUserResolver(fn () => $user);

        $this->assertTrue($request->authorize());
    }

    public function test_rejects_user_without_schedule_view_permission(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->userForTenant($tenant);

        $request = TimesheetReportRequest::create('/api/v1/reports/peripheral/timesheet', 'GET');
        $request->setUserResolver(fn () => $user);

        $this->assertFalse($request->authorize());
    }

    public function test_validates_month_and_tenant_user_filter(): void
    {
        $tenant = Tenant::factory()->create();
        $otherTenant = Tenant::factory()->create();
        $actor = $this->userForTenant($tenant, ['hr.schedule.view']);
        $tenantUser = $this->userForTenant($tenant);
        $otherTenantUser = $this->userForTenant($otherTenant);

        $request = TimesheetReportRequest::create('/api/v1/reports/peripheral/timesheet', 'GET');
        $request->setUserResolver(fn () => $actor);

        $this->assertTrue(
            Validator::make([
                'month' => '2026-04',
                'user_id' => $tenantUser->id,
            ], $request->rules())->passes()
        );

        $this->assertFalse(
            Validator::make([
                'month' => '2026-04',
                'user_id' => $otherTenantUser->id,
            ], $request->rules())->passes()
        );

        $this->assertFalse(
            Validator::make([
                'month' => '04-2026',
                'user_id' => $tenantUser->id,
            ], $request->rules())->passes()
        );
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function userForTenant(Tenant $tenant, array $permissions = []): User
    {
        setPermissionsTeamId($tenant->id);
        app()->instance('current_tenant_id', $tenant->id);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
            'is_active' => true,
        ]);
        $user->tenants()->attach($tenant->id, ['is_default' => true]);

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
            $user->givePermissionTo($permission);
        }

        return $user;
    }
}
