<?php

namespace Tests\Feature\Api\V1\Email;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\EmailAccount;
use App\Models\EmailSignature;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailSignatureControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private EmailAccount $account;

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

        $this->account = EmailAccount::create([
            'tenant_id' => $this->tenant->id,
            'label' => 'Test Account',
            'email_address' => 'sig@example.com',
            'imap_host' => 'imap.example.com',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_username' => 'sig@example.com',
            'imap_password' => 'secret',
            'is_active' => true,
            'sync_status' => 'idle',
        ]);
    }

    private function createSignature(array $overrides = []): EmailSignature
    {
        return EmailSignature::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'email_account_id' => $this->account->id,
            'name' => 'Default Signature',
            'html_content' => '<p>Best regards,<br>Test User</p>',
            'is_default' => false,
        ], $overrides));
    }

    public function test_index_returns_user_signatures(): void
    {
        $this->createSignature(['name' => 'Sig 1']);
        $this->createSignature(['name' => 'Sig 2']);

        $response = $this->getJson('/api/v1/email-signatures');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_index_only_returns_current_user_signatures(): void
    {
        $otherUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        $this->createSignature(['name' => 'My Sig', 'user_id' => $this->user->id]);
        EmailSignature::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $otherUser->id,
            'name' => 'Other Sig',
            'html_content' => '<p>Other</p>',
            'is_default' => false,
        ]);

        $response = $this->getJson('/api/v1/email-signatures');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'My Sig');
    }

    public function test_store_creates_signature(): void
    {
        $response = $this->postJson('/api/v1/email-signatures', [
            'email_account_id' => $this->account->id,
            'name' => 'New Signature',
            'html_content' => '<p>Att, User</p>',
            'is_default' => false,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'New Signature')
            ->assertJsonPath('data.user_id', $this->user->id);

        $this->assertDatabaseHas('email_signatures', [
            'name' => 'New Signature',
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_store_default_signature_resets_others(): void
    {
        $existing = $this->createSignature(['is_default' => true, 'email_account_id' => $this->account->id]);

        $response = $this->postJson('/api/v1/email-signatures', [
            'email_account_id' => $this->account->id,
            'name' => 'New Default',
            'html_content' => '<p>New default</p>',
            'is_default' => true,
        ]);

        $response->assertStatus(201);
        $this->assertFalse($existing->fresh()->is_default);
    }

    public function test_store_validation_requires_name_and_html(): void
    {
        $response = $this->postJson('/api/v1/email-signatures', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'html_content']);
    }

    public function test_update_modifies_signature(): void
    {
        $sig = $this->createSignature();

        $response = $this->putJson("/api/v1/email-signatures/{$sig->id}", [
            'name' => 'Updated Signature',
            'html_content' => '<p>Updated content</p>',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Signature');

        $this->assertDatabaseHas('email_signatures', [
            'id' => $sig->id,
            'name' => 'Updated Signature',
        ]);
    }

    public function test_update_returns_403_for_other_user(): void
    {
        $otherUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $sig = EmailSignature::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $otherUser->id,
            'name' => 'Other Sig',
            'html_content' => '<p>Other</p>',
            'is_default' => false,
        ]);

        $response = $this->putJson("/api/v1/email-signatures/{$sig->id}", [
            'name' => 'Hacked',
        ]);

        $response->assertForbidden();
    }

    public function test_update_default_resets_other_defaults_same_account(): void
    {
        $existing = $this->createSignature([
            'is_default' => true,
            'email_account_id' => $this->account->id,
        ]);
        $sig = $this->createSignature([
            'is_default' => false,
            'email_account_id' => $this->account->id,
        ]);

        $response = $this->putJson("/api/v1/email-signatures/{$sig->id}", [
            'is_default' => true,
        ]);

        $response->assertOk();
        $this->assertFalse($existing->fresh()->is_default);
        $this->assertTrue($sig->fresh()->is_default);
    }

    public function test_destroy_deletes_signature(): void
    {
        $sig = $this->createSignature();

        $response = $this->deleteJson("/api/v1/email-signatures/{$sig->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('email_signatures', ['id' => $sig->id]);
    }

    public function test_destroy_returns_403_for_other_user(): void
    {
        $otherUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        $sig = EmailSignature::create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $otherUser->id,
            'name' => 'Other Sig',
            'html_content' => '<p>Other</p>',
            'is_default' => false,
        ]);

        $response = $this->deleteJson("/api/v1/email-signatures/{$sig->id}");

        $response->assertForbidden();
    }

    public function test_store_without_account_id_is_allowed(): void
    {
        $response = $this->postJson('/api/v1/email-signatures', [
            'name' => 'Generic Signature',
            'html_content' => '<p>Generic</p>',
            'is_default' => false,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Generic Signature');
    }

    public function test_unauthenticated_returns_401(): void
    {
        app('auth')->forgetGuards();
        $response = $this->getJson('/api/v1/email-signatures');

        $response->assertUnauthorized();
    }
}
