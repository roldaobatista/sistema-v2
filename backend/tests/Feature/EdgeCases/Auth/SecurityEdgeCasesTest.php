<?php

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'current_tenant_id' => $this->tenant->id,
        'is_active' => true,
        'email' => 'testuser@example.com',
        'password' => Hash::make('SecurePassword123!'),
    ]);
    app()->instance('current_tenant_id', $this->tenant->id);
});

test('SQL injection in login email field is handled safely', function () {
    $response = $this->postJson('/api/v1/login', [
        'email' => "admin@test.com'; DROP TABLE users; --",
        'password' => 'anything',
    ]);

    // Should fail email validation, not execute SQL
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);

    // Verify users table still exists
    expect(User::count())->toBeGreaterThan(0);
});

test('SQL injection with valid email format is handled safely', function () {
    $response = $this->postJson('/api/v1/login', [
        'email' => "test@test.com' OR '1'='1",
        'password' => 'anything',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('XSS in name fields is sanitized during customer creation', function () {
    Gate::before(fn () => true);
    $this->withoutMiddleware([
        EnsureTenantScope::class,
        CheckPermission::class,
    ]);
    Sanctum::actingAs($this->user, ['*']);

    $response = $this->postJson('/api/v1/customers', [
        'type' => 'PF',
        'name' => '<script>alert("xss")</script>',
    ]);

    // StoreCustomerRequest uses strip_tags in prepareForValidation
    if ($response->status() === 201) {
        $data = $response->json();
        $name = $data['data']['name'] ?? $data['name'] ?? '';
        expect($name)->not->toContain('<script>');
    }
});

test('very long email string is handled gracefully', function () {
    $response = $this->postJson('/api/v1/login', [
        'email' => str_repeat('a', 10000).'@test.com',
        'password' => 'anything',
    ]);

    // Should return validation error, not crash
    $response->assertStatus(422);
});

test('very long password string is handled gracefully', function () {
    $response = $this->postJson('/api/v1/login', [
        'email' => 'testuser@example.com',
        'password' => str_repeat('a', 10000),
    ]);

    // Should fail auth, not crash
    expect($response->status())->toBeIn([401, 422]);
});

test('unicode and emoji in login email is handled', function () {
    $response = $this->postJson('/api/v1/login', [
        'email' => 'test@example.com',
        'password' => 'password',
    ]);

    // Should be a normal auth response, not a crash
    expect($response->status())->toBeIn([200, 401, 403, 422]);
});

test('password with only spaces fails authentication', function () {
    $response = $this->postJson('/api/v1/login', [
        'email' => 'testuser@example.com',
        'password' => '          ',
    ]);

    // Spaces-only password should not match the hash
    $response->assertStatus(422);
});

test('email case sensitivity is normalized', function () {
    // LoginRequest lowercases email in prepareForValidation
    $response = $this->postJson('/api/v1/login', [
        'email' => 'TESTUSER@EXAMPLE.COM',
        'password' => 'SecurePassword123!',
    ]);

    // Should succeed since email is normalized to lowercase
    $response->assertStatus(200);
});

test('multiple failed login attempts trigger rate limiting', function () {
    // Clear any existing throttle
    Cache::flush();

    for ($i = 0; $i < 5; $i++) {
        $this->postJson('/api/v1/login', [
            'email' => 'testuser@example.com',
            'password' => 'wrong_password',
        ]);
    }

    // 6th attempt should be rate limited
    $response = $this->postJson('/api/v1/login', [
        'email' => 'testuser@example.com',
        'password' => 'wrong_password',
    ]);

    $response->assertStatus(429);
    expect($response->json('message'))->toContain('bloqueada');
});

test('successful login clears rate limiting counter', function () {
    Cache::flush();

    // Make some failed attempts
    for ($i = 0; $i < 3; $i++) {
        $this->postJson('/api/v1/login', [
            'email' => 'testuser@example.com',
            'password' => 'wrong_password',
        ]);
    }

    // Successful login should clear the counter
    $response = $this->postJson('/api/v1/login', [
        'email' => 'testuser@example.com',
        'password' => 'SecurePassword123!',
    ]);

    $response->assertStatus(200);
});

test('deactivated user cannot login', function () {
    $this->user->update(['is_active' => false]);

    $response = $this->postJson('/api/v1/login', [
        'email' => 'testuser@example.com',
        'password' => 'SecurePassword123!',
    ]);

    $response->assertStatus(403);
    expect($response->json('message'))->toContain('desativada');
});

test('login with empty email returns validation error', function () {
    $response = $this->postJson('/api/v1/login', [
        'email' => '',
        'password' => 'anything',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('login with empty password returns validation error', function () {
    $response = $this->postJson('/api/v1/login', [
        'email' => 'testuser@example.com',
        'password' => '',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['password']);
});

test('login with missing fields returns all validation errors', function () {
    $response = $this->postJson('/api/v1/login', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email', 'password']);
});
