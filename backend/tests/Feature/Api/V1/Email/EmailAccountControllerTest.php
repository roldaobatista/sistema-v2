<?php

namespace Tests\Feature\Api\V1\Email;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Jobs\SyncEmailAccountJob;
use App\Models\Email;
use App\Models\EmailAccount;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailAccountControllerTest extends TestCase
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

    private function createAccount(array $overrides = []): EmailAccount
    {
        return EmailAccount::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'label' => 'Conta Teste',
            'email_address' => 'test@example.com',
            'imap_host' => 'imap.example.com',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_username' => 'test@example.com',
            'imap_password' => 'secret123',
            'smtp_host' => 'smtp.example.com',
            'smtp_port' => 465,
            'smtp_encryption' => 'ssl',
            'is_active' => true,
            'sync_status' => 'idle',
        ], $overrides));
    }

    public function test_index_returns_all_accounts_for_tenant(): void
    {
        $this->createAccount(['email_address' => 'a1@example.com', 'label' => 'Alpha']);
        $this->createAccount(['email_address' => 'a2@example.com', 'label' => 'Beta']);

        $response = $this->getJson('/api/v1/email-accounts');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.label', 'Alpha')
            ->assertJsonPath('data.1.label', 'Beta');
    }

    public function test_index_hides_imap_password(): void
    {
        $this->createAccount();

        $response = $this->getJson('/api/v1/email-accounts');

        $response->assertOk();
        $account = $response->json('data.0');
        $this->assertArrayNotHasKey('imap_password', $account);
    }

    public function test_index_does_not_return_other_tenant_accounts(): void
    {
        $otherTenant = Tenant::factory()->create();
        $this->createAccount(['email_address' => 'mine@example.com']);
        EmailAccount::create([
            'tenant_id' => $otherTenant->id,
            'label' => 'Other',
            'email_address' => 'other@example.com',
            'imap_host' => 'imap.other.com',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_username' => 'other@example.com',
            'imap_password' => 'pass',
            'is_active' => true,
            'sync_status' => 'idle',
        ]);

        $response = $this->getJson('/api/v1/email-accounts');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.email_address', 'mine@example.com');
    }

    public function test_show_returns_single_account(): void
    {
        $account = $this->createAccount();

        $response = $this->getJson("/api/v1/email-accounts/{$account->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $account->id)
            ->assertJsonPath('data.label', 'Conta Teste');

        $this->assertArrayNotHasKey('imap_password', $response->json('data'));
    }

    public function test_show_returns_404_for_other_tenant_via_scope(): void
    {
        $otherTenant = Tenant::factory()->create();
        $account = EmailAccount::create([
            'tenant_id' => $otherTenant->id,
            'label' => 'Other',
            'email_address' => 'other@example.com',
            'imap_host' => 'imap.other.com',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_username' => 'other@example.com',
            'imap_password' => 'pass',
            'is_active' => true,
            'sync_status' => 'idle',
        ]);

        $response = $this->getJson("/api/v1/email-accounts/{$account->id}");

        // BelongsToTenant global scope makes other tenant records invisible (404)
        $response->assertNotFound();
    }

    public function test_store_creates_account_with_valid_data(): void
    {
        $payload = [
            'label' => 'Nova Conta',
            'email_address' => 'nova@example.com',
            'imap_host' => 'imap.example.com',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_username' => 'nova@example.com',
            'imap_password' => 'secret',
        ];

        $response = $this->postJson('/api/v1/email-accounts', $payload);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id']]);

        $this->assertDatabaseHas('email_accounts', [
            'tenant_id' => $this->tenant->id,
            'label' => 'Nova Conta',
            'email_address' => 'nova@example.com',
            'imap_host' => 'imap.example.com',
        ]);
    }

    public function test_store_validation_requires_mandatory_fields(): void
    {
        $response = $this->postJson('/api/v1/email-accounts', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['label', 'email_address', 'imap_host', 'imap_port', 'imap_encryption', 'imap_username', 'imap_password']);
    }

    public function test_store_validation_rejects_invalid_email(): void
    {
        $response = $this->postJson('/api/v1/email-accounts', [
            'label' => 'Test',
            'email_address' => 'not-an-email',
            'imap_host' => 'imap.example.com',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_username' => 'test@example.com',
            'imap_password' => 'secret',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email_address']);
    }

    public function test_store_validation_rejects_invalid_encryption(): void
    {
        $response = $this->postJson('/api/v1/email-accounts', [
            'label' => 'Test',
            'email_address' => 'test@example.com',
            'imap_host' => 'imap.example.com',
            'imap_port' => 993,
            'imap_encryption' => 'invalid',
            'imap_username' => 'test@example.com',
            'imap_password' => 'secret',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['imap_encryption']);
    }

    public function test_store_validation_rejects_port_out_of_range(): void
    {
        $response = $this->postJson('/api/v1/email-accounts', [
            'label' => 'Test',
            'email_address' => 'test@example.com',
            'imap_host' => 'imap.example.com',
            'imap_port' => 70000,
            'imap_encryption' => 'ssl',
            'imap_username' => 'test@example.com',
            'imap_password' => 'secret',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['imap_port']);
    }

    public function test_update_modifies_account(): void
    {
        $account = $this->createAccount();

        $response = $this->putJson("/api/v1/email-accounts/{$account->id}", [
            'label' => 'Updated Label',
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('email_accounts', [
            'id' => $account->id,
            'label' => 'Updated Label',
        ]);
    }

    public function test_update_returns_404_for_other_tenant_via_scope(): void
    {
        $otherTenant = Tenant::factory()->create();
        $account = EmailAccount::create([
            'tenant_id' => $otherTenant->id,
            'label' => 'Other',
            'email_address' => 'other@example.com',
            'imap_host' => 'imap.other.com',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_username' => 'other@example.com',
            'imap_password' => 'pass',
            'is_active' => true,
            'sync_status' => 'idle',
        ]);

        $response = $this->putJson("/api/v1/email-accounts/{$account->id}", [
            'label' => 'Hacked',
        ]);

        // BelongsToTenant global scope makes other tenant records invisible (404)
        $response->assertNotFound();
    }

    public function test_destroy_deletes_account_without_emails(): void
    {
        $account = $this->createAccount();

        $response = $this->deleteJson("/api/v1/email-accounts/{$account->id}");

        $response->assertOk()
            ->assertJsonPath('message', 'Conta de email removida');

        $this->assertDatabaseMissing('email_accounts', ['id' => $account->id]);
    }

    public function test_destroy_returns_409_when_account_has_emails(): void
    {
        $account = $this->createAccount();
        Email::create([
            'tenant_id' => $this->tenant->id,
            'email_account_id' => $account->id,
            'message_id' => '<msg1@example.com>',
            'thread_id' => 'thread1',
            'folder' => 'INBOX',
            'from_address' => 'sender@example.com',
            'to_addresses' => [['email' => 'test@example.com']],
            'subject' => 'Test email',
            'date' => now(),
            'direction' => 'inbound',
            'status' => 'new',
        ]);

        $response = $this->deleteJson("/api/v1/email-accounts/{$account->id}");

        $response->assertStatus(409);
        $this->assertDatabaseHas('email_accounts', ['id' => $account->id]);
    }

    public function test_destroy_returns_404_for_other_tenant_via_scope(): void
    {
        $otherTenant = Tenant::factory()->create();
        $account = EmailAccount::create([
            'tenant_id' => $otherTenant->id,
            'label' => 'Other',
            'email_address' => 'other@example.com',
            'imap_host' => 'imap.other.com',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_username' => 'other@example.com',
            'imap_password' => 'pass',
            'is_active' => true,
            'sync_status' => 'idle',
        ]);

        $response = $this->deleteJson("/api/v1/email-accounts/{$account->id}");

        // BelongsToTenant global scope makes other tenant records invisible (404)
        $response->assertNotFound();
    }

    public function test_sync_now_dispatches_job(): void
    {
        Queue::fake();
        $account = $this->createAccount(['is_active' => true, 'sync_status' => 'idle']);

        $response = $this->postJson("/api/v1/email-accounts/{$account->id}/sync");

        $response->assertOk()
            ->assertJsonPath('message', 'Sincronização iniciada');

        Queue::assertPushed(SyncEmailAccountJob::class);
    }

    public function test_sync_now_rejects_inactive_account(): void
    {
        $account = $this->createAccount(['is_active' => false]);

        $response = $this->postJson("/api/v1/email-accounts/{$account->id}/sync");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Conta inativa');
    }

    public function test_sync_now_rejects_already_syncing(): void
    {
        $account = $this->createAccount(['is_active' => true, 'sync_status' => 'syncing']);

        $response = $this->postJson("/api/v1/email-accounts/{$account->id}/sync");

        $response->assertStatus(422)
            ->assertJsonPath('message', 'Sincronização já em andamento');
    }

    public function test_unauthenticated_returns_401(): void
    {
        app('auth')->forgetGuards();
        $response = $this->getJson('/api/v1/email-accounts');

        $response->assertUnauthorized();
    }
}
