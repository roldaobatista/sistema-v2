<?php

use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\Equipment;
use App\Models\Product;
use App\Models\Quote;
use App\Models\Tenant;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    $this->withoutMiddleware([
        EnsureTenantScope::class,
    ]);

    $this->tenant = Tenant::factory()->create();
    app()->instance('current_tenant_id', $this->tenant->id);
    setPermissionsTeamId($this->tenant->id);

    $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);
});

function quoteUser(Tenant $tenant, array $permissions = []): User
{
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'current_tenant_id' => $tenant->id,
        'is_active' => true,
    ]);

    setPermissionsTeamId($tenant->id);

    foreach ($permissions as $perm) {
        Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        $user->givePermissionTo($perm);
    }

    return $user;
}

// ============================================================
// Quotes - View
// ============================================================

test('user WITH quotes.quote.view can list quotes', function () {
    $user = quoteUser($this->tenant, ['quotes.quote.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/quotes')->assertOk();
});

test('user WITHOUT quotes.quote.view gets 403 on list quotes', function () {
    $user = quoteUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/quotes')->assertForbidden();
});

test('user WITH quotes.quote.view can show a quote', function () {
    $user = quoteUser($this->tenant, ['quotes.quote.view']);
    Sanctum::actingAs($user, ['*']);

    $quote = Quote::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'seller_id' => $user->id,
    ]);

    $this->getJson("/api/v1/quotes/{$quote->id}")->assertOk();
});

test('user WITHOUT quotes.quote.view gets 403 on show quote', function () {
    $user = quoteUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $quote = Quote::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'seller_id' => $user->id,
    ]);

    $this->getJson("/api/v1/quotes/{$quote->id}")->assertForbidden();
});

test('user WITH quotes.quote.view can access quotes summary', function () {
    $user = quoteUser($this->tenant, ['quotes.quote.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/quotes-summary')->assertOk();
});

test('user WITHOUT quotes.quote.view gets 403 on quotes summary', function () {
    $user = quoteUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/quotes-summary')->assertForbidden();
});

test('user WITH quotes.quote.view can access quote timeline', function () {
    $user = quoteUser($this->tenant, ['quotes.quote.view']);
    Sanctum::actingAs($user, ['*']);

    $quote = Quote::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'seller_id' => $user->id,
    ]);

    $this->getJson("/api/v1/quotes/{$quote->id}/timeline")->assertOk();
});

test('user WITHOUT quotes.quote.view gets 403 on quote timeline', function () {
    $user = quoteUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $quote = Quote::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'seller_id' => $user->id,
    ]);

    $this->getJson("/api/v1/quotes/{$quote->id}/timeline")->assertForbidden();
});

test('user WITH quotes.quote.view can export quotes CSV', function () {
    $user = quoteUser($this->tenant, ['quotes.quote.view']);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/quotes-export')->assertOk();
});

test('user WITHOUT quotes.quote.view gets 403 on export quotes', function () {
    $user = quoteUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->getJson('/api/v1/quotes-export')->assertForbidden();
});

// ============================================================
// Quotes - Create
// ============================================================

test('user WITH quotes.quote.create can store quote', function () {
    $user = quoteUser($this->tenant, ['quotes.quote.create']);
    Sanctum::actingAs($user, ['*']);

    $equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
    ]);
    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->postJson('/api/v1/quotes', [
        'customer_id' => $this->customer->id,
        'valid_until' => now()->addDays(7)->toDateString(),
        'equipments' => [
            [
                'equipment_id' => $equipment->id,
                'items' => [
                    [
                        'type' => 'product',
                        'product_id' => $product->id,
                        'quantity' => 1,
                        'original_price' => 100.00,
                        'unit_price' => 100.00,
                    ],
                ],
            ],
        ],
    ])->assertStatus(201);
});

test('user WITHOUT quotes.quote.create gets 403 on store quote', function () {
    $user = quoteUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/quotes', [
        'customer_id' => $this->customer->id,
    ])->assertForbidden();
});

test('user WITH quotes.quote.create can duplicate quote', function () {
    $user = quoteUser($this->tenant, ['quotes.quote.create']);
    Sanctum::actingAs($user, ['*']);

    $quote = Quote::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'seller_id' => $user->id,
    ]);

    $this->postJson("/api/v1/quotes/{$quote->id}/duplicate")->assertStatus(201);
});

test('user WITHOUT quotes.quote.create gets 403 on duplicate quote', function () {
    $user = quoteUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $quote = Quote::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'seller_id' => $user->id,
    ]);

    $this->postJson("/api/v1/quotes/{$quote->id}/duplicate")->assertForbidden();
});

// ============================================================
// Quotes - Update
// ============================================================

test('user WITH quotes.quote.update can update quote', function () {
    $user = quoteUser($this->tenant, ['quotes.quote.update']);
    Sanctum::actingAs($user, ['*']);

    $quote = Quote::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'seller_id' => $user->id,
    ]);

    $this->putJson("/api/v1/quotes/{$quote->id}", [
        'valid_until' => now()->addDays(14)->toDateString(),
    ])->assertOk();
});

test('user WITHOUT quotes.quote.update gets 403 on update quote', function () {
    $user = quoteUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $quote = Quote::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'seller_id' => $user->id,
    ]);

    $this->putJson("/api/v1/quotes/{$quote->id}", [
        'valid_until' => now()->addDays(14)->toDateString(),
    ])->assertForbidden();
});

// ============================================================
// Quotes - Delete
// ============================================================

test('user WITH quotes.quote.delete can delete quote', function () {
    $user = quoteUser($this->tenant, ['quotes.quote.delete']);
    Sanctum::actingAs($user, ['*']);

    $quote = Quote::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'seller_id' => $user->id,
    ]);

    $this->deleteJson("/api/v1/quotes/{$quote->id}")->assertNoContent();
});

test('user WITHOUT quotes.quote.delete gets 403 on delete quote', function () {
    $user = quoteUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $quote = Quote::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'seller_id' => $user->id,
    ]);

    $this->deleteJson("/api/v1/quotes/{$quote->id}")->assertForbidden();
});

// ============================================================
// Quotes - Approve / Reject
// ============================================================

test('user WITH quotes.quote.approve can approve quote', function () {
    $user = quoteUser($this->tenant, ['quotes.quote.approve']);
    Sanctum::actingAs($user, ['*']);

    $quote = Quote::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'seller_id' => $user->id,
        'status' => 'sent',
    ]);

    $this->postJson("/api/v1/quotes/{$quote->id}/approve", [
        'approval_channel' => 'whatsapp',
        'terms_accepted' => true,
    ])->assertOk();
});

test('user WITHOUT quotes.quote.approve gets 403 on approve quote', function () {
    $user = quoteUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $quote = Quote::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'seller_id' => $user->id,
    ]);

    $this->postJson("/api/v1/quotes/{$quote->id}/approve")->assertForbidden();
});

test('user WITH quotes.quote.approve can reject quote', function () {
    $user = quoteUser($this->tenant, ['quotes.quote.approve']);
    Sanctum::actingAs($user, ['*']);

    $quote = Quote::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'seller_id' => $user->id,
        'status' => 'sent',
    ]);

    $this->postJson("/api/v1/quotes/{$quote->id}/reject")->assertOk();
});

test('user WITHOUT quotes.quote.approve gets 403 on reject quote', function () {
    $user = quoteUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $quote = Quote::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'seller_id' => $user->id,
    ]);

    $this->postJson("/api/v1/quotes/{$quote->id}/reject")->assertForbidden();
});

// ============================================================
// Quotes - Convert
// ============================================================

test('user WITH quotes.quote.convert can convert quote to work order', function () {
    $user = quoteUser($this->tenant, ['quotes.quote.convert']);
    Sanctum::actingAs($user, ['*']);

    $quote = Quote::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'seller_id' => $user->id,
        'status' => 'approved',
    ]);

    $this->postJson("/api/v1/quotes/{$quote->id}/convert-to-os", [
        'approval_channel' => 'whatsapp',
        'terms_accepted' => true,
    ])->assertSuccessful();
});

test('user WITHOUT quotes.quote.convert gets 403 on convert quote', function () {
    $user = quoteUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $quote = Quote::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'seller_id' => $user->id,
    ]);

    $this->postJson("/api/v1/quotes/{$quote->id}/convert-to-os")->assertForbidden();
});

// ============================================================
// Quotes - Send
// ============================================================

test('user WITH quotes.quote.send can send quote', function () {
    $user = quoteUser($this->tenant, ['quotes.quote.send']);
    Sanctum::actingAs($user, ['*']);

    $equipment = Equipment::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
    ]);
    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

    $quote = Quote::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'seller_id' => $user->id,
        'status' => 'internally_approved',
    ]);

    // Add equipment with items so send validation passes
    $quoteEquip = $quote->equipments()->create([
        'tenant_id' => $this->tenant->id,
        'equipment_id' => $equipment->id,
    ]);
    $quoteEquip->items()->create([
        'tenant_id' => $this->tenant->id,
        'type' => 'product',
        'product_id' => $product->id,
        'quantity' => 1,
        'original_price' => 100,
        'unit_price' => 100,
        'subtotal' => 100,
    ]);

    $this->postJson("/api/v1/quotes/{$quote->id}/send")->assertSuccessful();
});

test('user WITHOUT quotes.quote.send gets 403 on send quote', function () {
    $user = quoteUser($this->tenant);
    Sanctum::actingAs($user, ['*']);

    $quote = Quote::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'seller_id' => $user->id,
    ]);

    $this->postJson("/api/v1/quotes/{$quote->id}/send")->assertForbidden();
});

// ============================================================
// Cross-permission checks
// ============================================================

test('user WITH only quotes.quote.view cannot create quote', function () {
    $user = quoteUser($this->tenant, ['quotes.quote.view']);
    Sanctum::actingAs($user, ['*']);

    $this->postJson('/api/v1/quotes', [
        'customer_id' => $this->customer->id,
    ])->assertForbidden();
});

test('user WITH only quotes.quote.view cannot delete quote', function () {
    $user = quoteUser($this->tenant, ['quotes.quote.view']);
    Sanctum::actingAs($user, ['*']);

    $quote = Quote::factory()->create([
        'tenant_id' => $this->tenant->id,
        'customer_id' => $this->customer->id,
        'seller_id' => $user->id,
    ]);

    $this->deleteJson("/api/v1/quotes/{$quote->id}")->assertForbidden();
});
