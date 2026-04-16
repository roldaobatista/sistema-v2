<?php

namespace Tests\Feature\Api\V1\Journey;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\BiometricConsent;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BiometricConsentControllerTest extends TestCase
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

    public function test_index_returns_consents_for_authenticated_user(): void
    {
        $response = $this->getJson('/api/v1/journey/biometric-consents');

        $response->assertOk()->assertJsonStructure(['data']);
    }

    public function test_grant_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/journey/biometric-consents/grant', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id', 'data_type', 'legal_basis', 'purpose']);
    }

    public function test_grant_rejects_invalid_data_type(): void
    {
        $response = $this->postJson('/api/v1/journey/biometric-consents/grant', [
            'user_id' => $this->user->id,
            'data_type' => 'iris_scan_xyz', // fora do enum
            'legal_basis' => 'consent',
            'purpose' => 'Controle de ponto',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['data_type']);
    }

    public function test_grant_rejects_invalid_legal_basis(): void
    {
        $response = $this->postJson('/api/v1/journey/biometric-consents/grant', [
            'user_id' => $this->user->id,
            'data_type' => 'facial',
            'legal_basis' => 'invalid_basis_xyz', // fora do enum
            'purpose' => 'Controle de ponto',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['legal_basis']);
    }

    public function test_grant_rejects_retention_days_outside_range(): void
    {
        // Regra: min:30, max:1825 (5 anos)
        $response = $this->postJson('/api/v1/journey/biometric-consents/grant', [
            'user_id' => $this->user->id,
            'data_type' => 'facial',
            'legal_basis' => 'consent',
            'purpose' => 'Controle de ponto',
            'retention_days' => 10, // abaixo do minimo
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['retention_days']);
    }

    public function test_grant_rejects_cross_tenant_user(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        $otherUser->tenants()->attach($otherTenant->id, ['is_default' => true]);

        $response = $this->postJson('/api/v1/journey/biometric-consents/grant', [
            'user_id' => $otherUser->id,
            'data_type' => 'facial',
            'legal_basis' => 'consent',
            'purpose' => 'Controle de ponto',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);

        $this->assertDatabaseMissing('biometric_consents', [
            'tenant_id' => $otherTenant->id,
            'user_id' => $otherUser->id,
            'data_type' => 'facial',
        ]);
    }

    public function test_revoke_validates_required_fields(): void
    {
        $response = $this->postJson('/api/v1/journey/biometric-consents/revoke', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id', 'data_type']);
    }

    public function test_revoke_rejects_cross_tenant_user(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        $otherUser->tenants()->attach($otherTenant->id, ['is_default' => true]);
        BiometricConsent::factory()->create([
            'tenant_id' => $otherTenant->id,
            'user_id' => $otherUser->id,
            'data_type' => 'facial',
            'is_active' => true,
            'revoked_at' => null,
        ]);

        $response = $this->postJson('/api/v1/journey/biometric-consents/revoke', [
            'user_id' => $otherUser->id,
            'data_type' => 'facial',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);

        $this->assertDatabaseHas('biometric_consents', [
            'tenant_id' => $otherTenant->id,
            'user_id' => $otherUser->id,
            'data_type' => 'facial',
            'is_active' => true,
            'revoked_at' => null,
        ]);
    }

    public function test_check_returns_consent_status_structure(): void
    {
        $response = $this->postJson('/api/v1/journey/biometric-consents/check', [
            'user_id' => $this->user->id,
            'data_type' => 'geolocation',
        ]);

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'has_consent',
                    'alternative_method',
                ],
            ]);
    }

    public function test_check_rejects_cross_tenant_user(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        $otherUser->tenants()->attach($otherTenant->id, ['is_default' => true]);
        BiometricConsent::factory()->create([
            'tenant_id' => $otherTenant->id,
            'user_id' => $otherUser->id,
            'data_type' => 'facial',
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/v1/journey/biometric-consents/check', [
            'user_id' => $otherUser->id,
            'data_type' => 'facial',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['user_id']);
    }
}
