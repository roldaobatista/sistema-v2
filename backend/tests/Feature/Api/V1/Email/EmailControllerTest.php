<?php

namespace Tests\Feature\Api\V1\Email;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Email;
use App\Models\EmailAccount;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailControllerTest extends TestCase
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
            'email_address' => 'inbox@example.com',
            'imap_host' => 'imap.example.com',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_username' => 'inbox@example.com',
            'imap_password' => 'secret',
            'is_active' => true,
            'sync_status' => 'idle',
        ]);
    }

    private function createEmail(array $overrides = []): Email
    {
        static $counter = 0;
        $counter++;

        return Email::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'email_account_id' => $this->account->id,
            'message_id' => "<msg{$counter}@example.com>",
            'thread_id' => "thread{$counter}",
            'folder' => 'INBOX',
            'from_address' => 'sender@example.com',
            'from_name' => 'Sender Name',
            'to_addresses' => [['email' => 'inbox@example.com']],
            'subject' => "Test Subject {$counter}",
            'body_text' => 'Test body text',
            'body_html' => '<p>Test body html</p>',
            'date' => now(),
            'is_read' => false,
            'is_starred' => false,
            'is_archived' => false,
            'direction' => 'inbound',
            'status' => 'new',
        ], $overrides));
    }

    // --- INDEX ---

    public function test_index_returns_inbox_emails_by_default(): void
    {
        $this->createEmail(['folder' => 'INBOX', 'is_archived' => false, 'direction' => 'inbound']);
        $this->createEmail(['folder' => 'INBOX', 'is_archived' => false, 'direction' => 'inbound']);
        $this->createEmail(['folder' => 'INBOX', 'is_archived' => true, 'direction' => 'inbound']); // archived, excluded

        $response = $this->getJson('/api/v1/emails');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_index_filters_by_folder_sent(): void
    {
        $this->createEmail(['direction' => 'inbound', 'folder' => 'INBOX']);
        $this->createEmail(['direction' => 'outbound', 'folder' => 'Sent']);

        $response = $this->getJson('/api/v1/emails?folder=sent');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_filters_by_starred(): void
    {
        $this->createEmail(['is_starred' => true]);
        $this->createEmail(['is_starred' => false]);

        $response = $this->getJson('/api/v1/emails?folder=starred');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_filters_by_is_read(): void
    {
        $this->createEmail(['is_read' => false, 'folder' => 'INBOX', 'is_archived' => false, 'direction' => 'inbound']);
        $this->createEmail(['is_read' => true, 'folder' => 'INBOX', 'is_archived' => false, 'direction' => 'inbound']);

        $response = $this->getJson('/api/v1/emails?is_read=0');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_filters_by_search(): void
    {
        $this->createEmail(['subject' => 'Urgent meeting', 'folder' => 'INBOX', 'is_archived' => false, 'direction' => 'inbound']);
        $this->createEmail(['subject' => 'Newsletter', 'folder' => 'INBOX', 'is_archived' => false, 'direction' => 'inbound']);

        $response = $this->getJson('/api/v1/emails?search=Urgent');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_filters_by_ai_category(): void
    {
        $this->createEmail(['ai_category' => 'support', 'folder' => 'INBOX', 'is_archived' => false, 'direction' => 'inbound']);
        $this->createEmail(['ai_category' => 'sales', 'folder' => 'INBOX', 'is_archived' => false, 'direction' => 'inbound']);

        $response = $this->getJson('/api/v1/emails?ai_category=support');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_does_not_return_other_tenant_emails(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherAccount = EmailAccount::create([
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

        $this->createEmail(['folder' => 'INBOX', 'is_archived' => false, 'direction' => 'inbound']);
        Email::create([
            'tenant_id' => $otherTenant->id,
            'email_account_id' => $otherAccount->id,
            'message_id' => '<other@example.com>',
            'thread_id' => 'otherthread',
            'folder' => 'INBOX',
            'from_address' => 'x@x.com',
            'to_addresses' => [['email' => 'other@example.com']],
            'subject' => 'Other tenant',
            'date' => now(),
            'direction' => 'inbound',
            'status' => 'new',
            'is_archived' => false,
        ]);

        $response = $this->getJson('/api/v1/emails');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_index_paginates(): void
    {
        for ($i = 0; $i < 30; $i++) {
            $this->createEmail(['folder' => 'INBOX', 'is_archived' => false, 'direction' => 'inbound']);
        }

        $response = $this->getJson('/api/v1/emails?per_page=10');

        $response->assertOk()
            ->assertJsonCount(10, 'data')
            ->assertJsonPath('meta.per_page', 10)
            ->assertJsonPath('meta.total', 30);
    }

    // --- SHOW ---

    public function test_show_returns_email_and_marks_as_read(): void
    {
        $email = $this->createEmail(['is_read' => false]);

        $response = $this->getJson("/api/v1/emails/{$email->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $email->id)
            ->assertJsonPath('data.subject', $email->subject);

        $this->assertTrue($email->fresh()->is_read);
    }

    public function test_show_returns_404_for_other_tenant_via_scope(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherAccount = EmailAccount::create([
            'tenant_id' => $otherTenant->id,
            'label' => 'Other',
            'email_address' => 'other@other.com',
            'imap_host' => 'imap.other.com',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_username' => 'other@other.com',
            'imap_password' => 'pass',
            'is_active' => true,
            'sync_status' => 'idle',
        ]);
        $email = Email::create([
            'tenant_id' => $otherTenant->id,
            'email_account_id' => $otherAccount->id,
            'message_id' => '<otheremail@example.com>',
            'thread_id' => 'otherthread',
            'folder' => 'INBOX',
            'from_address' => 'x@x.com',
            'to_addresses' => [['email' => 'other@other.com']],
            'subject' => 'Secret',
            'date' => now(),
            'direction' => 'inbound',
            'status' => 'new',
        ]);

        $response = $this->getJson("/api/v1/emails/{$email->id}");

        // BelongsToTenant global scope makes other tenant records invisible (404)
        $response->assertNotFound();
    }

    // --- TOGGLE STAR ---

    public function test_toggle_star_toggles_starred_status(): void
    {
        $email = $this->createEmail(['is_starred' => false]);

        $response = $this->postJson("/api/v1/emails/{$email->id}/toggle-star");

        $response->assertOk()
            ->assertJsonPath('data.is_starred', true);

        // Toggle again
        $response2 = $this->postJson("/api/v1/emails/{$email->id}/toggle-star");
        $response2->assertOk()
            ->assertJsonPath('data.is_starred', false);
    }

    // --- MARK READ / UNREAD ---

    public function test_mark_read_marks_email_as_read(): void
    {
        $email = $this->createEmail(['is_read' => false]);

        $response = $this->postJson("/api/v1/emails/{$email->id}/mark-read");

        $response->assertOk()
            ->assertJsonPath('message', 'Marcado como lido');

        $this->assertTrue($email->fresh()->is_read);
    }

    public function test_mark_unread_marks_email_as_unread(): void
    {
        $email = $this->createEmail(['is_read' => true]);

        $response = $this->postJson("/api/v1/emails/{$email->id}/mark-unread");

        $response->assertOk()
            ->assertJsonPath('message', 'Marcado como não lido');

        $this->assertFalse($email->fresh()->is_read);
    }

    // --- ARCHIVE ---

    public function test_archive_archives_email(): void
    {
        $email = $this->createEmail(['is_archived' => false]);

        $response = $this->postJson("/api/v1/emails/{$email->id}/archive");

        $response->assertOk()
            ->assertJsonPath('message', 'Email arquivado');

        $this->assertTrue($email->fresh()->is_archived);
    }

    // --- LINK ENTITY ---

    public function test_link_entity_links_email_to_entity(): void
    {
        $email = $this->createEmail();

        $response = $this->postJson("/api/v1/emails/{$email->id}/link-entity", [
            'linked_type' => 'App\\Models\\WorkOrder',
            'linked_id' => 999,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.linked_type', 'App\\Models\\WorkOrder')
            ->assertJsonPath('data.linked_id', 999);
    }

    public function test_link_entity_validation_requires_fields(): void
    {
        $email = $this->createEmail();

        $response = $this->postJson("/api/v1/emails/{$email->id}/link-entity", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['linked_type', 'linked_id']);
    }

    // --- STATS ---

    public function test_stats_returns_aggregated_data(): void
    {
        $this->createEmail([
            'is_read' => false, 'is_starred' => true,
            'ai_category' => 'support', 'ai_priority' => 'high', 'ai_sentiment' => 'negative',
            'date' => now(), 'folder' => 'INBOX', 'is_archived' => false, 'direction' => 'inbound',
        ]);
        $this->createEmail([
            'is_read' => true, 'is_starred' => false,
            'ai_category' => 'sales', 'ai_priority' => 'low', 'ai_sentiment' => 'positive',
            'date' => now(), 'folder' => 'INBOX', 'is_archived' => false, 'direction' => 'inbound',
        ]);

        $response = $this->getJson('/api/v1/emails/stats');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => ['total', 'unread', 'starred', 'today', 'by_category', 'by_priority', 'by_sentiment'],
            ]);

        $data = $response->json('data');
        $this->assertEquals(2, $data['total']);
        $this->assertEquals(1, $data['unread']);
        $this->assertEquals(1, $data['starred']);
        $this->assertEquals(2, $data['today']);
    }

    // --- BATCH ACTION ---

    public function test_batch_action_marks_multiple_as_read(): void
    {
        $e1 = $this->createEmail(['is_read' => false]);
        $e2 = $this->createEmail(['is_read' => false]);

        $response = $this->postJson('/api/v1/emails/batch-action', [
            'ids' => [$e1->id, $e2->id],
            'action' => 'mark_read',
        ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Ação aplicada com sucesso');

        $this->assertTrue($e1->fresh()->is_read);
        $this->assertTrue($e2->fresh()->is_read);
    }

    public function test_batch_action_archives_multiple(): void
    {
        $e1 = $this->createEmail(['is_archived' => false]);
        $e2 = $this->createEmail(['is_archived' => false]);

        $response = $this->postJson('/api/v1/emails/batch-action', [
            'ids' => [$e1->id, $e2->id],
            'action' => 'archive',
        ]);

        $response->assertOk();
        $this->assertTrue($e1->fresh()->is_archived);
        $this->assertTrue($e2->fresh()->is_archived);
    }

    public function test_batch_action_stars_and_unstars(): void
    {
        $e1 = $this->createEmail(['is_starred' => false]);

        $response = $this->postJson('/api/v1/emails/batch-action', [
            'ids' => [$e1->id],
            'action' => 'star',
        ]);

        $response->assertOk();
        $this->assertTrue($e1->fresh()->is_starred);

        $response2 = $this->postJson('/api/v1/emails/batch-action', [
            'ids' => [$e1->id],
            'action' => 'unstar',
        ]);

        $response2->assertOk();
        $this->assertFalse($e1->fresh()->is_starred);
    }

    public function test_batch_action_validation_requires_ids_and_action(): void
    {
        $response = $this->postJson('/api/v1/emails/batch-action', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['ids', 'action']);
    }

    public function test_batch_action_validation_rejects_invalid_action(): void
    {
        $e1 = $this->createEmail();

        $response = $this->postJson('/api/v1/emails/batch-action', [
            'ids' => [$e1->id],
            'action' => 'delete_all',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['action']);
    }

    // --- COMPOSE ---

    public function test_compose_validation_requires_fields(): void
    {
        $response = $this->postJson('/api/v1/emails/compose', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['account_id', 'to', 'subject', 'body']);
    }

    // --- REPLY ---

    public function test_reply_validation_requires_body(): void
    {
        $email = $this->createEmail();

        $response = $this->postJson("/api/v1/emails/{$email->id}/reply", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['body']);
    }

    // --- FORWARD ---

    public function test_forward_validation_requires_to(): void
    {
        $email = $this->createEmail();

        $response = $this->postJson("/api/v1/emails/{$email->id}/forward", []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['to']);
    }

    // --- TRACKING PIXEL ---

    public function test_track_returns_pixel_and_increments_count(): void
    {
        $email = $this->createEmail(['tracking_id' => 'abc123', 'read_count' => 0]);

        // Tracking pixel is exposed only through the versioned public API.
        $response = $this->get('/api/v1/pixel/abc123');

        $response->assertOk()
            ->assertHeader('Content-Type', 'image/gif');

        $this->assertEquals(1, $email->fresh()->read_count);
    }

    public function test_track_creates_activity_for_first_reads(): void
    {
        $email = $this->createEmail(['tracking_id' => 'track456', 'read_count' => 0]);

        $this->get('/api/v1/pixel/track456');

        $this->assertDatabaseHas('email_activities', [
            'email_id' => $email->id,
            'type' => 'read_tracked',
        ]);
    }

    public function test_track_returns_pixel_even_for_unknown_tracking_id(): void
    {
        $response = $this->get('/api/v1/pixel/nonexistent');

        $response->assertOk()
            ->assertHeader('Content-Type', 'image/gif');
    }

    // --- UNAUTHENTICATED ---

    public function test_unauthenticated_returns_401(): void
    {
        app('auth')->forgetGuards();
        $response = $this->getJson('/api/v1/emails');

        $response->assertUnauthorized();
    }
}
