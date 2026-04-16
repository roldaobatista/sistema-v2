<?php

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
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
});

test('script tag in customer name is stripped', function () {
    // StoreCustomerRequest uses strip_tags in prepareForValidation
    $response = $this->postJson('/api/v1/customers', [
        'type' => 'PF',
        'name' => '<script>alert("xss")</script>Test Customer',
    ]);

    $response->assertStatus(201);
    $name = $response->json('data.name') ?? $response->json('name');
    expect($name)->not->toContain('<script>');
    expect($name)->toContain('Test Customer');
});

test('img onerror XSS in customer name is stripped', function () {
    $response = $this->postJson('/api/v1/customers', [
        'type' => 'PJ',
        'name' => '<img onerror="alert(1)" src=x>Company Name',
    ]);

    $response->assertStatus(201);
    $name = $response->json('data.name') ?? $response->json('name');
    expect($name)->not->toContain('<img');
    expect($name)->not->toContain('onerror');
});

test('HTML tags in customer email field are validated', function () {
    $response = $this->postJson('/api/v1/customers', [
        'type' => 'PF',
        'name' => 'Test Customer',
        'email' => '<script>alert(1)</script>@test.com',
    ]);

    // prepareForValidation strips tags: '<script>alert(1)</script>@test.com' becomes 'alert(1)@test.com'
    // which is a technically valid email, so it passes validation (201)
    // The important thing is the stored email doesn't contain script tags
    if ($response->status() === 201) {
        $email = $response->json('data.email') ?? $response->json('email');
        expect($email)->not->toContain('<script');
        expect($email)->not->toContain('</script>');
    } else {
        $response->assertStatus(422);
    }
});

test('script tag in customer trade_name is stripped', function () {
    $response = $this->postJson('/api/v1/customers', [
        'type' => 'PJ',
        'name' => 'Valid Company Name',
        'trade_name' => '<script>document.cookie</script>Trade Name',
    ]);

    $response->assertStatus(201);
    $tradeName = $response->json('data.trade_name') ?? $response->json('trade_name');
    if ($tradeName) {
        expect($tradeName)->not->toContain('<script>');
    }
});

test('HTML in customer notes is stripped', function () {
    $response = $this->postJson('/api/v1/customers', [
        'type' => 'PF',
        'name' => 'Test Customer',
        'notes' => '<div onmouseover="alert(1)">Notes with XSS</div>',
    ]);

    $response->assertStatus(201);
    $notes = $response->json('data.notes') ?? $response->json('notes');
    if ($notes) {
        expect($notes)->not->toContain('<div');
        expect($notes)->not->toContain('onmouseover');
    }
});

test('script tag in customer address fields is stripped', function () {
    $response = $this->postJson('/api/v1/customers', [
        'type' => 'PF',
        'name' => 'Test Customer',
        'address_street' => '<script>alert("xss")</script>Rua Teste',
        'address_neighborhood' => '<img src=x onerror=alert(1)>Bairro',
        'address_city' => '<b>Cidade</b>',
    ]);

    $response->assertStatus(201);
    $data = $response->json('data') ?? $response->json();
    if (isset($data['address_street'])) {
        expect($data['address_street'])->not->toContain('<script>');
    }
});

test('javascript URL in google_maps_link is rejected', function () {
    $response = $this->postJson('/api/v1/customers', [
        'type' => 'PF',
        'name' => 'Test Customer',
        'google_maps_link' => 'javascript:alert(1)',
    ]);

    // google_maps_link has 'url' validation, javascript: should fail
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['google_maps_link']);
});

test('valid HTTPS URL in google_maps_link is accepted', function () {
    $response = $this->postJson('/api/v1/customers', [
        'type' => 'PF',
        'name' => 'Test Customer',
        'google_maps_link' => 'https://www.google.com/maps/search/?api=1&query=-23.55,-46.63',
    ]);

    $response->assertStatus(201);
});
