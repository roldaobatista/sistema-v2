<?php

namespace Tests\Feature;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantSettingsControllerTest extends TestCase
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
            'is_active' => true,
        ]);

        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        app()->instance('current_tenant_id', $this->tenant->id);
        setPermissionsTeamId($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    // ── Index ──

    public function test_index_returns_all_tenant_settings(): void
    {
        TenantSetting::setValue($this->tenant->id, 'theme', ['mode' => 'dark']);
        TenantSetting::setValue($this->tenant->id, 'logo_url', '/img/logo.png');

        $response = $this->getJson('/api/v1/tenant-settings');

        $response->assertOk()
            ->assertJsonPath('data.theme.mode', 'dark')
            ->assertJsonPath('data.logo_url', '/img/logo.png');
    }

    public function test_index_returns_empty_when_no_settings(): void
    {
        $response = $this->getJson('/api/v1/tenant-settings');

        $response->assertOk()
            ->assertJsonPath('data', []);
    }

    // ── Show ──

    public function test_show_returns_existing_setting(): void
    {
        TenantSetting::setValue($this->tenant->id, 'theme', ['mode' => 'dark']);

        $response = $this->getJson('/api/v1/tenant-settings/theme');

        $response->assertOk()
            ->assertJsonPath('key', 'theme')
            ->assertJsonPath('value.mode', 'dark');
    }

    public function test_show_returns_null_for_nonexistent_key(): void
    {
        $response = $this->getJson('/api/v1/tenant-settings/nonexistent_key');

        $response->assertOk()
            ->assertJsonPath('key', 'nonexistent_key')
            ->assertJsonPath('value', null);
    }

    // ── Upsert ──

    public function test_upsert_creates_new_settings(): void
    {
        $response = $this->postJson('/api/v1/tenant-settings', [
            'settings' => [
                ['key' => 'new_key', 'value' => 'new_value'],
                ['key' => 'another_key', 'value' => ['nested' => true]],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.new_key', 'new_value')
            ->assertJsonPath('data.another_key.nested', true);

        $this->assertDatabaseHas('tenant_settings', [
            'tenant_id' => $this->tenant->id,
            'key' => 'new_key',
        ]);
    }

    public function test_upsert_updates_existing_setting(): void
    {
        TenantSetting::setValue($this->tenant->id, 'theme', ['mode' => 'light']);

        $response = $this->postJson('/api/v1/tenant-settings', [
            'settings' => [
                ['key' => 'theme', 'value' => ['mode' => 'dark']],
            ],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.theme.mode', 'dark');

        // Ensure only one record exists (upsert, not duplicate)
        $count = TenantSetting::withoutGlobalScope('tenant')
            ->where('tenant_id', $this->tenant->id)
            ->where('key', 'theme')
            ->count();
        $this->assertEquals(1, $count);
    }

    public function test_upsert_rejects_empty_settings(): void
    {
        $response = $this->postJson('/api/v1/tenant-settings', []);

        $response->assertStatus(422);
    }

    public function test_upsert_rejects_settings_without_key(): void
    {
        $response = $this->postJson('/api/v1/tenant-settings', [
            'settings' => [
                ['value' => 'no_key'],
            ],
        ]);

        $response->assertStatus(422);
    }

    // ── Destroy ──

    public function test_destroy_removes_existing_setting(): void
    {
        TenantSetting::setValue($this->tenant->id, 'disposable_key', 'value');

        $response = $this->deleteJson('/api/v1/tenant-settings/disposable_key');

        $response->assertStatus(204);

        $this->assertDatabaseMissing('tenant_settings', [
            'tenant_id' => $this->tenant->id,
            'key' => 'disposable_key',
        ]);
    }

    public function test_destroy_returns_404_for_nonexistent_key(): void
    {
        $response = $this->deleteJson('/api/v1/tenant-settings/nonexistent_xyz');

        $response->assertStatus(404);
    }

    // ── Tenant isolation ──

    public function test_settings_are_isolated_per_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();

        TenantSetting::setValue($this->tenant->id, 'shared_key', 'tenant_a');
        TenantSetting::setValue($otherTenant->id, 'shared_key', 'tenant_b');

        $response = $this->getJson('/api/v1/tenant-settings/shared_key');

        $response->assertOk()
            ->assertJsonPath('value', 'tenant_a');
    }
}
