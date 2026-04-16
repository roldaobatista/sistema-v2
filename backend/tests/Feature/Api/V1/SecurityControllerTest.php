<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SecurityControllerTest extends TestCase
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
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_password_policy_returns_default_when_none_configured(): void
    {
        $response = $this->getJson('/api/v1/security/password-policy');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'min_length',
                    'require_uppercase',
                    'require_lowercase',
                    'require_number',
                    'max_attempts',
                ],
            ])
            ->assertJsonPath('data.min_length', 8);
    }

    public function test_update_password_policy_validates_required_min_length(): void
    {
        $response = $this->putJson('/api/v1/security/password-policy', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['min_length']);
    }

    public function test_update_password_policy_persists_valid_values(): void
    {
        $response = $this->putJson('/api/v1/security/password-policy', [
            'min_length' => 12,
            'require_uppercase' => true,
            'require_number' => true,
            'expiry_days' => 60,
        ]);

        $response->assertOk();

        $policy = DB::table('password_policies')
            ->where('tenant_id', $this->tenant->id)
            ->first();

        $this->assertNotNull($policy, 'Password policy deve ser persistida apos update');
        $this->assertSame(12, (int) $policy->min_length);
    }

    public function test_update_password_policy_rejects_min_length_below_6(): void
    {
        $response = $this->putJson('/api/v1/security/password-policy', [
            'min_length' => 4, // min:6 na regra
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['min_length']);
    }

    public function test_watermark_config_returns_default_when_none_configured(): void
    {
        $response = $this->getJson('/api/v1/security/watermark');

        $response->assertOk()
            ->assertJsonPath('data.enabled', false)
            ->assertJsonPath('data.text', 'CONFIDENCIAL');
    }

    public function test_update_watermark_config_rejects_invalid_position(): void
    {
        $response = $this->putJson('/api/v1/security/watermark', [
            'enabled' => true,
            'position' => 'invalid_position_xyz',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['position']);
    }
}
