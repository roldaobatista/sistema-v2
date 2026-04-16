<?php

namespace Tests\Feature\Api\V1\Email;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Email;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailNoteControllerTest extends TestCase
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

    public function test_index_returns_notes_for_email(): void
    {
        $email = $this->createEmail();

        $response = $this->getJson("/api/v1/emails/{$email->id}/notes");

        $response->assertOk()->assertJsonStructure(['data']);
    }

    public function test_store_validates_required_fields(): void
    {
        $email = $this->createEmail();

        $response = $this->postJson("/api/v1/emails/{$email->id}/notes", []);

        $response->assertStatus(422);
    }

    public function test_index_rejects_cross_tenant_email(): void
    {
        $otherTenant = Tenant::factory()->create();
        $foreign = $this->createEmail($otherTenant->id);

        $response = $this->getJson("/api/v1/emails/{$foreign->id}/notes");

        $this->assertContains($response->status(), [403, 404]);
    }
}
