<?php

namespace Tests\Feature\Journey;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\BiometricConsent;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Journey\BiometricComplianceService;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
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
    app()->instance('current_tenant_id', $this->tenant->id);
    Sanctum::actingAs($this->user, ['*']);
    $this->service = app(BiometricComplianceService::class);
});

it('returns false when no consent exists', function () {
    expect($this->service->hasActiveConsent($this->user, 'facial'))->toBeFalse();
});

it('grants consent and detects active', function () {
    $this->service->grantConsent($this->user, 'facial', 'consent', 'Ponto facial');

    expect($this->service->hasActiveConsent($this->user, 'facial'))->toBeTrue();
});

it('revokes consent successfully', function () {
    $this->service->grantConsent($this->user, 'geolocation', 'consent', 'Rastreamento');

    $result = $this->service->revokeConsent($this->user, 'geolocation');

    expect($result)->toBeTrue();
    expect($this->service->hasActiveConsent($this->user, 'geolocation'))->toBeFalse();
});

it('revoke returns false when no active consent', function () {
    expect($this->service->revokeConsent($this->user, 'facial'))->toBeFalse();
});

it('new consent deactivates previous for same type', function () {
    $first = $this->service->grantConsent($this->user, 'facial', 'consent', 'V1');
    $second = $this->service->grantConsent($this->user, 'facial', 'consent', 'V2');

    expect($first->fresh()->is_active)->toBeFalse();
    expect($second->is_active)->toBeTrue();

    $activeCount = BiometricConsent::where('user_id', $this->user->id)
        ->where('data_type', 'facial')
        ->where('is_active', true)
        ->count();
    expect($activeCount)->toBe(1);
});

it('expired consent is not active', function () {
    BiometricConsent::factory()->expired()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'data_type' => 'facial',
    ]);

    expect($this->service->hasActiveConsent($this->user, 'facial'))->toBeFalse();
});

it('revoked consent is not active', function () {
    BiometricConsent::factory()->revoked()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'data_type' => 'facial',
    ]);

    expect($this->service->hasActiveConsent($this->user, 'facial'))->toBeFalse();
});

it('gets alternative method when consent denied', function () {
    BiometricConsent::factory()->revoked()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'data_type' => 'facial',
        'alternative_method' => 'pin_code',
    ]);

    expect($this->service->getAlternativeMethod($this->user, 'facial'))->toBe('pin_code');
});

it('gets all consent statuses for user', function () {
    $this->service->grantConsent($this->user, 'geolocation', 'consent', 'GPS');
    $this->service->grantConsent($this->user, 'facial', 'consent', 'Face');

    $consents = $this->service->getConsentsForUser($this->user);

    expect($consents['geolocation']['has_consent'])->toBeTrue();
    expect($consents['facial']['has_consent'])->toBeTrue();
    expect($consents['fingerprint']['has_consent'])->toBeFalse();
    expect($consents['voice']['has_consent'])->toBeFalse();
});

it('cannot access consent from another tenant', function () {
    $otherTenant = Tenant::factory()->create();
    $otherUser = User::factory()->create([
        'tenant_id' => $otherTenant->id,
        'current_tenant_id' => $otherTenant->id,
    ]);

    BiometricConsent::withoutGlobalScope('tenant')->create([
        'tenant_id' => $otherTenant->id,
        'user_id' => $otherUser->id,
        'data_type' => 'facial',
        'legal_basis' => 'consent',
        'purpose' => 'Test',
        'consented_at' => now(),
        'is_active' => true,
    ]);

    expect(BiometricConsent::count())->toBe(0);
});
