<?php

use App\Models\ClientPortalUser;
use App\Models\Contract;
use App\Models\Customer;
use App\Models\Tenant;
use Illuminate\Support\Facades\Hash;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();

    $this->customer = Customer::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $this->portalUser = ClientPortalUser::forceCreate([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'name' => 'Portal Test User',
        'email' => 'portal@example.com',
        'password' => Hash::make('password123'),
        'is_active' => true,
        'email_verified_at' => now(),
    ]);
});

test('portal user cannot login if customer has no active contract', function () {
    $response = $this->postJson('/api/v1/portal/login', [
        'tenant_id' => $this->tenant->id,
        'email' => 'portal@example.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(422)
        ->assertJsonPath('message', 'Credenciais invalidas.');
});

test('portal user can login if customer has active contract', function () {
    // Create an active contract
    Contract::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'status' => 'active',
    ]);

    $response = $this->postJson('/api/v1/portal/login', [
        'tenant_id' => $this->tenant->id,
        'email' => 'portal@example.com',
        'password' => 'password123',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                'token',
                'user' => ['id', 'name', 'email'],
            ],
        ]);
});
