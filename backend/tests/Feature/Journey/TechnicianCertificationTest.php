<?php

namespace Tests\Feature\Journey;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\TechnicianCertification;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Journey\TechnicianEligibilityService;
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
    $this->service = app(TechnicianEligibilityService::class);
});

it('creates certification with valid status', function () {
    $cert = TechnicianCertification::factory()->cnh()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
    ]);

    expect($cert->type)->toBe('cnh');
    expect($cert->status)->toBe('valid');
    expect($cert->isValid())->toBeTrue();
    expect($cert->isExpired())->toBeFalse();
});

it('detects expired certification', function () {
    $cert = TechnicianCertification::factory()->expired()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
    ]);

    expect($cert->isExpired())->toBeTrue();
    expect($cert->isValid())->toBeFalse();
});

it('detects certification expiring soon', function () {
    $cert = TechnicianCertification::factory()->expiringSoon()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
    ]);

    expect($cert->isExpiringSoon(30))->toBeTrue();
    expect($cert->isValid())->toBeTrue();
});

it('refreshes status from valid to expired', function () {
    $cert = TechnicianCertification::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'expires_at' => now()->subDay(),
        'status' => 'valid',
    ]);

    $cert->refreshStatus();

    expect($cert->fresh()->status)->toBe('expired');
});

it('refreshes status from valid to expiring_soon', function () {
    $cert = TechnicianCertification::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'expires_at' => now()->addDays(10),
        'status' => 'valid',
    ]);

    $cert->refreshStatus();

    expect($cert->fresh()->status)->toBe('expiring_soon');
});

it('does not change revoked status on refresh', function () {
    $cert = TechnicianCertification::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'status' => 'revoked',
    ]);

    $cert->refreshStatus();

    expect($cert->fresh()->status)->toBe('revoked');
});

it('technician is eligible when no certifications required', function () {
    $eligible = $this->service->isEligibleForServiceType($this->user, 'manutencao_geral');

    expect($eligible)->toBeTrue();
});

it('technician is eligible with valid required certification', function () {
    TechnicianCertification::factory()->nr10()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'required_for_service_types' => ['eletrica'],
    ]);

    $eligible = $this->service->isEligibleForServiceType($this->user, 'eletrica');

    expect($eligible)->toBeTrue();
});

it('technician is NOT eligible with expired required certification', function () {
    TechnicianCertification::factory()->nr10()->expired()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'required_for_service_types' => ['eletrica'],
    ]);

    $eligible = $this->service->isEligibleForServiceType($this->user, 'eletrica');

    expect($eligible)->toBeFalse();
});

it('returns blocking certifications with reasons', function () {
    TechnicianCertification::factory()->nr35()->expired()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'required_for_service_types' => ['altura'],
    ]);

    $blocking = $this->service->getBlockingCertifications($this->user, 'altura');

    expect($blocking)->toHaveCount(1);
    expect($blocking->first()['type'])->toBe('nr35');
    expect($blocking->first()['reason'])->toBe('expired');
});

it('gets expiring certifications within period', function () {
    TechnicianCertification::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'expires_at' => now()->addDays(15),
        'status' => 'valid',
    ]);

    TechnicianCertification::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'expires_at' => now()->addDays(60),
        'status' => 'valid',
    ]);

    $expiring = $this->service->getExpiringCertifications($this->tenant->id, 30);

    expect($expiring)->toHaveCount(1);
});

it('refreshAllStatuses updates stale certifications', function () {
    TechnicianCertification::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'expires_at' => now()->subDay(),
        'status' => 'valid',
    ]);

    TechnicianCertification::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'expires_at' => now()->addDays(10),
        'status' => 'valid',
    ]);

    $updated = $this->service->refreshAllStatuses($this->tenant->id);

    expect($updated)->toBe(2); // 1 expired + 1 expiring_soon
});

it('cannot access certification from another tenant', function () {
    $otherTenant = Tenant::factory()->create();
    $otherUser = User::factory()->create([
        'tenant_id' => $otherTenant->id,
        'current_tenant_id' => $otherTenant->id,
    ]);

    TechnicianCertification::withoutGlobalScope('tenant')->create([
        'tenant_id' => $otherTenant->id,
        'user_id' => $otherUser->id,
        'type' => 'cnh',
        'name' => 'CNH',
        'issued_at' => now()->subYear(),
    ]);

    expect(TechnicianCertification::count())->toBe(0);
});
