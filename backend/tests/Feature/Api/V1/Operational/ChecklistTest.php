<?php

namespace Tests\Feature\Api\V1\Operational;

use App\Models\Checklist;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\ChecklistSeeder;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ChecklistTest extends TestCase
{
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->seed(ChecklistSeeder::class);
    }

    public function test_admin_can_create_checklist()
    {
        $tenant = Tenant::factory()->create();
        $admin = User::factory()->create(['tenant_id' => $tenant->id]);
        setPermissionsTeamId($tenant->id);
        $admin->givePermissionTo('technicians.checklist.manage');

        $response = $this->actingAs($admin)->postJson('/api/v1/checklists', [
            'name' => 'Checklist Diário',
            'items' => [
                ['id' => '1', 'text' => 'Item 1', 'type' => 'boolean', 'required' => true],
            ],
            'is_active' => true,
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('checklists', ['name' => 'Checklist Diário']);
    }

    public function test_technician_cannot_create_checklist()
    {
        $tenant = Tenant::factory()->create();
        $tech = User::factory()->create(['tenant_id' => $tenant->id]);
        setPermissionsTeamId($tenant->id);
        $tech->givePermissionTo('technicians.checklist.view');
        // No manage permission

        $response = $this->actingAs($tech)->postJson('/api/v1/checklists', [
            'name' => 'Checklist Hacker',
            'items' => [],
        ]);

        $response->assertForbidden();
    }

    public function test_technician_can_submit_checklist()
    {
        $tenant = Tenant::factory()->create();
        $tech = User::factory()->create(['tenant_id' => $tenant->id]);
        setPermissionsTeamId($tenant->id);
        $tech->givePermissionTo('technicians.checklist.view');
        $tech->givePermissionTo('technicians.checklist.create');

        $checklist = Checklist::factory()->create(['tenant_id' => $tenant->id]);

        $response = $this->actingAs($tech)->postJson('/api/v1/checklist-submissions', [
            'checklist_id' => $checklist->id,
            'responses' => ['item_1' => true],
        ]);

        $response->assertCreated();
        $this->assertDatabaseHas('checklist_submissions', [
            'checklist_id' => $checklist->id,
            'technician_id' => $tech->id,
        ]);
    }
}
