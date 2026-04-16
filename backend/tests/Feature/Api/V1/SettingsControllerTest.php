<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\SystemSetting;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SettingsControllerTest extends TestCase
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

    public function test_index_returns_paginated_settings(): void
    {
        // Cria settings diretamente para ter data na listagem
        SystemSetting::setValue('sample_key_a', 'value_a', 'string', 'general', $this->tenant->id);
        SystemSetting::setValue('sample_key_b', 'value_b', 'string', 'general', $this->tenant->id);

        $response = $this->getJson('/api/v1/settings');

        $response->assertOk()->assertJsonStructure(['data']);
    }

    public function test_index_filters_by_group(): void
    {
        SystemSetting::setValue('fiscal_setting', 'val1', 'string', 'fiscal', $this->tenant->id);
        SystemSetting::setValue('general_setting', 'val2', 'string', 'general', $this->tenant->id);

        $response = $this->getJson('/api/v1/settings?group=fiscal');

        $response->assertOk();

        $data = $response->json('data');
        $this->assertIsArray($data);
        foreach ($data as $setting) {
            if (isset($setting['group'])) {
                $this->assertEquals('fiscal', $setting['group']);
            }
        }
    }

    public function test_update_rejects_empty_payload(): void
    {
        $response = $this->putJson('/api/v1/settings', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['settings']);
    }

    public function test_update_persists_settings_values(): void
    {
        $response = $this->putJson('/api/v1/settings', [
            'settings' => [
                [
                    'key' => 'company_name',
                    'value' => 'Kalibrium Test Co',
                    'type' => 'string',
                    'group' => 'general',
                ],
                [
                    'key' => 'feature_flag_x',
                    'value' => true,
                    'type' => 'boolean',
                    'group' => 'features',
                ],
            ],
        ]);

        $response->assertOk();

        // Verificar persistencia no banco
        $this->assertDatabaseHas('system_settings', [
            'tenant_id' => $this->tenant->id,
            'key' => 'company_name',
        ]);
    }

    public function test_update_rejects_invalid_quote_sequence_start(): void
    {
        // withValidator impoe que quote_sequence_start seja inteiro >= 1
        $response = $this->putJson('/api/v1/settings', [
            'settings' => [
                [
                    'key' => 'quote_sequence_start',
                    'value' => -5,
                    'type' => 'integer',
                    'group' => 'quotes',
                ],
            ],
        ]);

        $response->assertStatus(422);
    }
}
