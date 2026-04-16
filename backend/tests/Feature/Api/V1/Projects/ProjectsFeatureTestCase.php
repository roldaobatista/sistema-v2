<?php

namespace Tests\Feature\Api\V1\Projects;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\CreatesTestDatabase;
use Tests\TestCase;

abstract class ProjectsFeatureTestCase extends TestCase
{
    use CreatesTestDatabase;

    protected Tenant $tenant;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Gate::before(fn () => true);
        $this->withoutMiddleware([EnsureTenantScope::class, CheckPermission::class]);

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $this->setTenantContext($this->tenant->id);
    }

    protected function actingAsUser(): void
    {
        Sanctum::actingAs($this->user, ['*']);
    }
}
