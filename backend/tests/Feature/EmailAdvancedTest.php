<?php

namespace Tests\Feature;

use App\Models\Email;
use App\Models\EmailAccount;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class EmailAdvancedTest extends TestCase
{
    protected $user;

    protected $tenant;

    protected $account;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);

        // Setup tenant and user
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id, // Added
        ]);

        // Mock permissions
        $this->actingAs($this->user);

        // Create an email account
        $this->account = EmailAccount::create([
            'tenant_id' => $this->tenant->id,
            'label' => 'Test Account', // Added label
            'email_address' => 'test@example.com', // Changed from email
            'imap_host' => 'imap.example.com',
            'imap_port' => 993,
            'imap_encryption' => 'ssl', // Added missing
            'imap_username' => 'test@example.com', // Added
            'imap_password' => 'password', // Changed from password
            'smtp_host' => 'smtp.example.com',
            'smtp_port' => 587,
            'is_active' => true,
        ]);

        // Setup Permissions
        $permissions = [
            'email.template.view',
            'email.template.create',
            'email.signature.view',
            'email.signature.manage',
            'email.inbox.view',
            'email.inbox.manage',
            'email.tag.view',
            'email.tag.manage',
        ];

        foreach ($permissions as $perm) {
            Permission::create(['name' => $perm, 'guard_name' => 'web']);
        }

        setPermissionsTeamId($this->tenant->id);
        $this->user->givePermissionTo($permissions);
    }

    #[Test]
    public function can_create_and_list_email_templates()
    {
        $response = $this->postJson('/api/v1/email-templates', [
            'name' => 'Test Template',
            'subject' => 'Hello',
            'body' => '<p>World</p>',
            'is_shared' => false,
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'Test Template']);

        $this->assertDatabaseHas('email_templates', ['name' => 'Test Template']);

        $response = $this->getJson('/api/v1/email-templates');
        $response->assertStatus(200)
            ->assertJsonCount(1, 'data');
    }

    #[Test]
    public function can_create_and_list_email_signatures()
    {
        $response = $this->postJson('/api/v1/email-signatures', [
            'name' => 'My Sig',
            'html_content' => '<b>Best regards</b>',
            'is_default' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonFragment(['name' => 'My Sig']);

        $this->assertDatabaseHas('email_signatures', ['name' => 'My Sig']);
    }

    #[Test]
    public function can_add_note_to_email()
    {
        $email = Email::create([
            'tenant_id' => $this->tenant->id,
            'email_account_id' => $this->account->id,
            'message_id' => 'msg-123',
            'folder' => 'Inbox',
            'from_address' => 'sender@example.com',
            'from_name' => 'Sender',
            'to_addresses' => ['test@example.com'],
            'subject' => 'Test Subject',
            'body_text' => 'Body',
            'date' => now(),
        ]);

        $response = $this->postJson("/api/v1/emails/{$email->id}/notes", [
            'content' => 'This is a private note',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('email_notes', ['content' => 'This is a private note']);
    }

    #[Test]
    public function can_create_and_toggle_tags()
    {
        // Create Tag
        $response = $this->postJson('/api/v1/email-tags', [
            'name' => 'Urgent',
            'color' => '#FF0000',
        ]);
        $response->assertStatus(201);
        $tagId = $response->json('data.id');

        $email = Email::create([
            'tenant_id' => $this->tenant->id,
            'email_account_id' => $this->account->id,
            'message_id' => 'msg-456',
            'folder' => 'Inbox',
            'from_address' => 'sender2@example.com',
            'from_name' => 'Sender 2',
            'to_addresses' => ['test@example.com'],
            'subject' => 'Test Tags',
            'body_text' => 'Body',
            'date' => now(),
        ]);

        // Toggle Tag
        $response = $this->postJson("/api/v1/emails/{$email->id}/tags/{$tagId}");
        $response->assertStatus(200);

        $this->assertDatabaseHas('email_email_tag', [
            'email_id' => $email->id,
            'email_tag_id' => $tagId,
        ]);
    }

    #[Test]
    public function can_assign_email_to_user()
    {
        $email = Email::create([
            'tenant_id' => $this->tenant->id,
            'email_account_id' => $this->account->id,
            'message_id' => 'msg-789',
            'folder' => 'Inbox',
            'from_address' => 'sender3@example.com',
            'from_name' => 'Sender 3',
            'to_addresses' => ['test@example.com'],
            'subject' => 'Test Assign',
            'body_text' => 'Body',
            'date' => now(),
        ]);

        $otherUser = User::factory()->create(['tenant_id' => $this->tenant->id]);

        $response = $this->postJson("/api/v1/emails/{$email->id}/assign", [
            'user_id' => $otherUser->id,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('emails', [
            'id' => $email->id,
            'assigned_to_user_id' => $otherUser->id,
        ]);

        $this->assertDatabaseHas('email_activities', [
            'email_id' => $email->id,
            'type' => 'assigned',
        ]);
    }

    #[Test]
    public function can_snooze_email()
    {
        $email = Email::create([
            'tenant_id' => $this->tenant->id,
            'email_account_id' => $this->account->id,
            'message_id' => 'msg-101',
            'folder' => 'Inbox',
            'from_address' => 'sender4@example.com',
            'from_name' => 'Sender 4',
            'to_addresses' => ['test@example.com'],
            'subject' => 'Test Snooze',
            'body_text' => 'Body',
            'date' => now(),
        ]);

        $snoozeDate = now()->addDays(1)->toDateTimeString();

        $response = $this->postJson("/api/v1/emails/{$email->id}/snooze", [
            'snoozed_until' => $snoozeDate,
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('emails', [
            'id' => $email->id,
            'snoozed_until' => $snoozeDate,
        ]);
    }

    #[Test]
    public function tracking_pixel_returns_image()
    {
        $email = Email::create([
            'tenant_id' => $this->tenant->id,
            'email_account_id' => $this->account->id,
            'message_id' => 'msg-202',
            'folder' => 'Sent',
            'from_address' => 'me@example.com',
            'from_name' => 'Me',
            'to_addresses' => ['client@example.com'],
            'subject' => 'Track Me',
            'body_text' => 'Body',
            'date' => now(),
            'tracking_id' => 'track-123',
        ]);

        $response = $this->get('/api/v1/pixel/track-123');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'image/gif');

        $this->assertDatabaseHas('emails', [
            'id' => $email->id,
            'read_count' => 1,
        ]);
    }
}
