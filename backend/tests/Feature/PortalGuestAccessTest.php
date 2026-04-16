<?php

use App\Models\Customer;
use App\Models\PortalGuestLink;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->seller = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->quote = Quote::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'seller_id' => $this->seller->id,
        'status' => Quote::STATUS_SENT,
        'valid_until' => now()->addDays(10),
    ]);
});

test('guest can view entity via valid guest link', function () {
    $link = PortalGuestLink::create([
        'tenant_id' => $this->tenant->id,
        'token' => PortalGuestLink::generateSecureToken(),
        'entity_type' => Quote::class,
        'entity_id' => $this->quote->id,
        'expires_at' => now()->addDay(),
    ]);

    $response = $this->getJson("/api/v1/portal/guest/{$link->token}");

    $response->assertStatus(200)
        ->assertJsonPath('data.type', 'Quote')
        ->assertJsonPath('data.resource.id', $this->quote->id);
});

test('guest cannot view entity if link is expired', function () {
    $link = PortalGuestLink::create([
        'tenant_id' => $this->tenant->id,
        'token' => PortalGuestLink::generateSecureToken(),
        'entity_type' => Quote::class,
        'entity_id' => $this->quote->id,
        'expires_at' => now()->subMinute(),
    ]);

    $response = $this->getJson("/api/v1/portal/guest/{$link->token}");

    $response->assertStatus(404)
        ->assertJsonPath('message', 'Este link de acesso expirou ou é invalido.');
});

test('guest cannot view entity if link is already used', function () {
    $link = PortalGuestLink::create([
        'tenant_id' => $this->tenant->id,
        'token' => PortalGuestLink::generateSecureToken(),
        'entity_type' => Quote::class,
        'entity_id' => $this->quote->id,
        'expires_at' => now()->addDay(),
        'used_at' => now(),
    ]);

    $response = $this->getJson("/api/v1/portal/guest/{$link->token}");

    $response->assertStatus(404);
});

test('guest can consume link to approve quote', function () {
    $link = PortalGuestLink::create([
        'tenant_id' => $this->tenant->id,
        'token' => PortalGuestLink::generateSecureToken(),
        'entity_type' => Quote::class,
        'entity_id' => $this->quote->id,
        'expires_at' => now()->addDay(),
    ]);

    $response = $this->postJson("/api/v1/portal/guest/{$link->token}/consume", [
        'action' => 'approve',
        'signer_name' => 'John Doe Guest',
        'comments' => 'Looks good',
    ]);

    $response->assertStatus(200);

    $this->quote->refresh();
    expect($this->quote->status->value ?? (string) $this->quote->status)->toBe(Quote::STATUS_APPROVED)
        ->and($this->quote->approved_by_name)->toBe('John Doe Guest');

    $link->refresh();
    expect($link->used_at)->not->toBeNull();
});
