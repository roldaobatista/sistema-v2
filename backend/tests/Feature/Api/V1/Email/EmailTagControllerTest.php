<?php

namespace Tests\Feature\Api\V1\Email;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Email;
use App\Models\EmailAccount;
use App\Models\EmailTag;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailTagControllerTest extends TestCase
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
            'label' => 'Tag Account',
            'email_address' => 'tag@example.com',
            'imap_host' => 'imap.example.com',
            'imap_port' => 993,
            'imap_encryption' => 'ssl',
            'imap_username' => 'tag@example.com',
            'imap_password' => 'secret',
            'is_active' => true,
            'sync_status' => 'idle',
        ]);
    }

    private function createTag(array $overrides = []): EmailTag
    {
        return EmailTag::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'name' => 'Urgente',
            'color' => '#EF4444',
        ], $overrides));
    }

    private function createEmail(array $overrides = []): Email
    {
        static $counter = 0;
        $counter++;

        return Email::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'email_account_id' => $this->account->id,
            'message_id' => "<tagmsg{$counter}@example.com>",
            'thread_id' => "tagthread{$counter}",
            'folder' => 'INBOX',
            'from_address' => 'sender@example.com',
            'to_addresses' => [['email' => 'tag@example.com']],
            'subject' => "Tag Test {$counter}",
            'date' => now(),
            'direction' => 'inbound',
            'status' => 'new',
        ], $overrides));
    }

    public function test_index_returns_all_tags_ordered_by_name(): void
    {
        $this->createTag(['name' => 'Zebra']);
        $this->createTag(['name' => 'Alpha']);

        $response = $this->getJson('/api/v1/email-tags');

        $response->assertOk()
            ->assertJsonCount(2, 'data');

        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertEquals(['Alpha', 'Zebra'], $names);
    }

    public function test_index_returns_tag_resource_format(): void
    {
        $tag = $this->createTag();

        $response = $this->getJson('/api/v1/email-tags');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    ['id', 'tenant_id', 'name', 'color', 'created_at', 'updated_at'],
                ],
            ]);
    }

    public function test_store_creates_tag(): void
    {
        $response = $this->postJson('/api/v1/email-tags', [
            'name' => 'Important',
            'color' => '#22C55E',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Important')
            ->assertJsonPath('data.color', '#22C55E');

        $this->assertDatabaseHas('email_tags', [
            'name' => 'Important',
            'color' => '#22C55E',
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_store_validation_requires_name_and_color(): void
    {
        $response = $this->postJson('/api/v1/email-tags', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'color']);
    }

    public function test_store_validation_name_max_length(): void
    {
        $response = $this->postJson('/api/v1/email-tags', [
            'name' => str_repeat('a', 51),
            'color' => '#EF4444',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_store_validation_color_max_length(): void
    {
        $response = $this->postJson('/api/v1/email-tags', [
            'name' => 'Test',
            'color' => str_repeat('#', 21),
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['color']);
    }

    public function test_update_modifies_tag(): void
    {
        $tag = $this->createTag();

        $response = $this->putJson("/api/v1/email-tags/{$tag->id}", [
            'name' => 'Updated Tag',
            'color' => '#3B82F6',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Tag')
            ->assertJsonPath('data.color', '#3B82F6');

        $this->assertDatabaseHas('email_tags', [
            'id' => $tag->id,
            'name' => 'Updated Tag',
        ]);
    }

    public function test_update_partial_update(): void
    {
        $tag = $this->createTag(['name' => 'Original', 'color' => '#EF4444']);

        $response = $this->putJson("/api/v1/email-tags/{$tag->id}", [
            'name' => 'Only Name Changed',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Only Name Changed')
            ->assertJsonPath('data.color', '#EF4444');
    }

    public function test_destroy_deletes_tag(): void
    {
        $tag = $this->createTag();

        $response = $this->deleteJson("/api/v1/email-tags/{$tag->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('email_tags', ['id' => $tag->id]);
    }

    public function test_toggle_tag_attaches_tag_to_email(): void
    {
        $email = $this->createEmail();
        $tag = $this->createTag();

        $response = $this->postJson("/api/v1/emails/{$email->id}/tags/{$tag->id}");

        $response->assertOk()
            ->assertJsonPath('data.attached', true);

        $this->assertDatabaseHas('email_email_tag', [
            'email_id' => $email->id,
            'email_tag_id' => $tag->id,
        ]);
    }

    public function test_toggle_tag_detaches_tag_from_email(): void
    {
        $email = $this->createEmail();
        $tag = $this->createTag();

        // Attach first
        $email->tags()->attach($tag->id, ['tenant_id' => $email->tenant_id]);

        // Toggle should detach
        $response = $this->postJson("/api/v1/emails/{$email->id}/tags/{$tag->id}");

        $response->assertOk()
            ->assertJsonPath('data.attached', false);

        $this->assertDatabaseMissing('email_email_tag', [
            'email_id' => $email->id,
            'email_tag_id' => $tag->id,
        ]);
    }

    public function test_toggle_tag_creates_activity_log(): void
    {
        $email = $this->createEmail();
        $tag = $this->createTag();

        $this->postJson("/api/v1/emails/{$email->id}/tags/{$tag->id}");

        $this->assertDatabaseHas('email_activities', [
            'email_id' => $email->id,
            'user_id' => $this->user->id,
            'type' => 'tag_added',
        ]);

        // Toggle again to remove
        $this->postJson("/api/v1/emails/{$email->id}/tags/{$tag->id}");

        $this->assertDatabaseHas('email_activities', [
            'email_id' => $email->id,
            'user_id' => $this->user->id,
            'type' => 'tag_removed',
        ]);
    }

    public function test_unauthenticated_returns_401(): void
    {
        app('auth')->forgetGuards();
        $response = $this->getJson('/api/v1/email-tags');

        $response->assertUnauthorized();
    }
}
