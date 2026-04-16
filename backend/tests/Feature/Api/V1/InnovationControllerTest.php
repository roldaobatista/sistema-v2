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

class InnovationControllerTest extends TestCase
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

    // ─── THEME CONFIG ───────────────────────────────────────────────

    public function test_theme_config_returns_defaults_when_no_custom_theme(): void
    {
        $response = $this->getJson('/api/v1/innovation/theme-config');

        $response->assertStatus(200)
            ->assertJsonPath('data.primary_color', '#3B82F6')
            ->assertJsonPath('data.font_family', 'Inter');
    }

    public function test_theme_config_returns_saved_theme(): void
    {
        DB::table('custom_themes')->insert([
            'tenant_id' => $this->tenant->id,
            'primary_color' => '#FF0000',
            'secondary_color' => '#00FF00',
            'accent_color' => '#0000FF',
            'dark_mode' => true,
            'sidebar_style' => 'compact',
            'font_family' => 'Roboto',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/innovation/theme-config');

        $response->assertStatus(200)
            ->assertJsonPath('data.primary_color', '#FF0000')
            ->assertJsonPath('data.font_family', 'Roboto');
    }

    // ─── UPDATE THEME CONFIG ────────────────────────────────────────

    public function test_update_theme_config_creates_or_updates_theme(): void
    {
        $payload = [
            'primary_color' => '#ABCDEF',
            'dark_mode' => true,
            'sidebar_style' => 'compact',
        ];

        $response = $this->putJson('/api/v1/innovation/theme-config', $payload);

        $response->assertStatus(200);

        $this->assertDatabaseHas('custom_themes', [
            'tenant_id' => $this->tenant->id,
            'primary_color' => '#ABCDEF',
        ]);
    }

    public function test_update_theme_config_returns_422_for_invalid_sidebar_style(): void
    {
        $response = $this->putJson('/api/v1/innovation/theme-config', [
            'sidebar_style' => 'invalid_style',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['sidebar_style']);
    }

    // ─── REFERRAL PROGRAM ───────────────────────────────────────────

    public function test_referral_program_returns_user_referrals(): void
    {
        DB::table('referral_codes')->insert([
            'tenant_id' => $this->tenant->id,
            'referrer_id' => $this->user->id,
            'code' => 'TESTCODE',
            'uses' => 3,
            'reward_type' => 'discount',
            'reward_value' => 10,
            'is_active' => true,
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/innovation/referral-program');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    public function test_referral_program_does_not_leak_other_tenant_data(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);

        DB::table('referral_codes')->insert([
            'tenant_id' => $otherTenant->id,
            'referrer_id' => $otherUser->id,
            'code' => 'OTHERCODE',
            'uses' => 0,
            'reward_type' => 'discount',
            'reward_value' => 10,
            'is_active' => true,
            'created_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/innovation/referral-program');

        $response->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    // ─── GENERATE REFERRAL CODE ─────────────────────────────────────

    public function test_generate_referral_code_creates_new_code(): void
    {
        $response = $this->postJson('/api/v1/innovation/referral-code');

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['code']]);

        $this->assertDatabaseHas('referral_codes', [
            'tenant_id' => $this->tenant->id,
            'referrer_id' => $this->user->id,
        ]);
    }

    public function test_generate_referral_code_returns_422_if_already_exists(): void
    {
        DB::table('referral_codes')->insert([
            'tenant_id' => $this->tenant->id,
            'referrer_id' => $this->user->id,
            'code' => 'EXISTING',
            'uses' => 0,
            'reward_type' => 'discount',
            'reward_value' => 10,
            'is_active' => true,
            'created_at' => now(),
        ]);

        $response = $this->postJson('/api/v1/innovation/referral-code');

        $response->assertStatus(422);
    }

    // ─── ROI CALCULATOR ─────────────────────────────────────────────

    public function test_roi_calculator_returns_correct_calculations(): void
    {
        $payload = [
            'monthly_os_count' => 100,
            'avg_os_value' => 500,
            'current_monthly_cost' => 10000,
            'system_monthly_cost' => 2000,
            'time_saved_percent' => 30,
        ];

        $response = $this->postJson('/api/v1/innovation/roi-calculator', $payload);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'current_monthly_revenue',
                    'additional_os_capacity',
                    'additional_monthly_revenue',
                    'monthly_savings',
                    'annual_roi_percent',
                    'payback_months',
                    'time_saved_percent',
                ],
            ])
            ->assertJsonPath('data.current_monthly_revenue', 50000)
            ->assertJsonPath('data.time_saved_percent', 30);
    }

    public function test_roi_calculator_returns_422_for_missing_fields(): void
    {
        $response = $this->postJson('/api/v1/innovation/roi-calculator', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'monthly_os_count',
                'avg_os_value',
                'current_monthly_cost',
                'system_monthly_cost',
            ]);
    }

    public function test_roi_calculator_defaults_time_saved_to_30(): void
    {
        $payload = [
            'monthly_os_count' => 50,
            'avg_os_value' => 200,
            'current_monthly_cost' => 5000,
            'system_monthly_cost' => 1000,
        ];

        $response = $this->postJson('/api/v1/innovation/roi-calculator', $payload);

        $response->assertStatus(200)
            ->assertJsonPath('data.time_saved_percent', 30);
    }

    // ─── PRESENTATION DATA ──────────────────────────────────────────

    public function test_presentation_data_returns_kpis_structure(): void
    {
        $response = $this->getJson('/api/v1/innovation/presentation');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'company',
                    'kpis' => [
                        'total_customers',
                        'total_os_year',
                        'revenue_year',
                    ],
                    'monthly_trend',
                ],
            ]);
    }

    // ─── EASTER EGG ─────────────────────────────────────────────────

    public function test_easter_egg_returns_known_code(): void
    {
        $response = $this->getJson('/api/v1/innovation/easter-egg/konami');

        $response->assertStatus(200)
            ->assertJsonPath('data.found', true);
    }

    public function test_easter_egg_returns_not_found_for_unknown_code(): void
    {
        $response = $this->getJson('/api/v1/innovation/easter-egg/nonexistent');

        $response->assertStatus(200)
            ->assertJsonPath('data.found', false);
    }
}
