<?php

namespace Tests\Feature\Api\V1\Email;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Email;
use App\Models\EmailActivity;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailActivityControllerTest extends TestCase
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
        $this->user->tenants()->attach($this->tenant->id, ['is_default' => true]);

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    private function createEmail(?int $tenantId = null): Email
    {
        $email = new Email;
        $email->forceFill([
            'tenant_id' => $tenantId ?? $this->tenant->id,
            'email_account_id' => 1,
            'message_id' => 'msg-'.uniqid(),
            'folder' => 'INBOX',
            'from_address' => 'sender@example.com',
            'from_name' => 'Sender',
            'to_addresses' => json_encode(['dest@example.com']),
            'subject' => 'Subject',
            'body_text' => 'Body',
            'date' => now(),
        ])->save();

        return $email;
    }

    public function test_index_returns_activities_for_email(): void
    {
        $email = $this->createEmail();
        EmailActivity::create([
            'tenant_id' => $this->tenant->id,
            'email_id' => $email->id,
            'user_id' => $this->user->id,
            'type' => 'note',
            'details' => ['message' => 'Contato inicial'],
        ]);

        $response = $this->getJson("/api/v1/emails/{$email->id}/activities");

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'per_page', 'total']])
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.per_page', 25);
    }

    public function test_index_returns_empty_list_when_email_has_no_activities(): void
    {
        $email = $this->createEmail();

        $response = $this->getJson("/api/v1/emails/{$email->id}/activities");

        $response->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.total', 0);
    }

    public function test_index_does_not_include_activities_from_another_email(): void
    {
        $email = $this->createEmail();
        $otherEmail = $this->createEmail();

        EmailActivity::create([
            'tenant_id' => $this->tenant->id,
            'email_id' => $otherEmail->id,
            'user_id' => $this->user->id,
            'type' => 'note',
            'details' => ['message' => 'Outro email'],
        ]);

        $response = $this->getJson("/api/v1/emails/{$email->id}/activities");

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_index_includes_activity_user_relationship(): void
    {
        $email = $this->createEmail();
        EmailActivity::create([
            'tenant_id' => $this->tenant->id,
            'email_id' => $email->id,
            'user_id' => $this->user->id,
            'type' => 'note',
            'details' => ['message' => 'Com usuario'],
        ]);

        $response = $this->getJson("/api/v1/emails/{$email->id}/activities");

        $response->assertOk()
            ->assertJsonPath('data.0.user.id', $this->user->id)
            ->assertJsonPath('data.0.user.name', $this->user->name);
    }

    public function test_index_rejects_cross_tenant_email(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreign = $this->createEmail($otherTenant->id);

        $response = $this->getJson("/api/v1/emails/{$foreign->id}/activities");

        $response->assertNotFound();
    }

    public function test_index_caps_per_page(): void
    {
        $email = $this->createEmail();

        $response = $this->getJson("/api/v1/emails/{$email->id}/activities?per_page=500");

        $response->assertOk()
            ->assertJsonPath('meta.per_page', 100);
    }

    public function test_index_uses_descending_activity_order(): void
    {
        $email = $this->createEmail();
        $older = new EmailActivity;
        $older->forceFill([
            'tenant_id' => $this->tenant->id,
            'email_id' => $email->id,
            'user_id' => $this->user->id,
            'type' => 'note',
            'details' => ['message' => 'Mais antiga'],
            'created_at' => now()->subHour(),
            'updated_at' => now()->subHour(),
        ])->save();
        $newer = new EmailActivity;
        $newer->forceFill([
            'tenant_id' => $this->tenant->id,
            'email_id' => $email->id,
            'user_id' => $this->user->id,
            'type' => 'note',
            'details' => ['message' => 'Mais recente'],
            'created_at' => now(),
            'updated_at' => now(),
        ])->save();

        $response = $this->getJson("/api/v1/emails/{$email->id}/activities");

        $response->assertOk()
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id);
    }

    public function test_index_returns_not_found_for_missing_email(): void
    {
        $this->getJson('/api/v1/emails/999999/activities')
            ->assertNotFound();
    }
}
