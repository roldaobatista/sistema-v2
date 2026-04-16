<?php

namespace Tests\Feature\Api\V1\Email;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\EmailTemplate;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class EmailTemplateControllerTest extends TestCase
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

    private function createTemplate(array $overrides = []): EmailTemplate
    {
        return EmailTemplate::create(array_merge([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'name' => 'Template Teste',
            'subject' => 'Assunto Teste',
            'body' => '<p>Corpo do template</p>',
            'is_shared' => false,
        ], $overrides));
    }

    public function test_index_returns_user_templates(): void
    {
        $this->createTemplate(['name' => 'My Template']);
        $this->createTemplate(['name' => 'Shared Template', 'is_shared' => true, 'user_id' => null]);

        $response = $this->getJson('/api/v1/email-templates');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_index_returns_shared_templates_from_other_users(): void
    {
        $otherUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        $this->createTemplate(['name' => 'My Template', 'user_id' => $this->user->id, 'is_shared' => false]);
        $this->createTemplate(['name' => 'Other User Shared', 'user_id' => null, 'is_shared' => true]);
        // Other user's private template should NOT appear
        $this->createTemplate(['name' => 'Other Private', 'user_id' => $otherUser->id, 'is_shared' => false]);

        $response = $this->getJson('/api/v1/email-templates');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_index_ordered_by_name(): void
    {
        $this->createTemplate(['name' => 'Zebra']);
        $this->createTemplate(['name' => 'Alpha']);

        $response = $this->getJson('/api/v1/email-templates');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name')->toArray();
        $this->assertEquals(['Alpha', 'Zebra'], $names);
    }

    public function test_show_returns_single_template(): void
    {
        $template = $this->createTemplate();

        $response = $this->getJson("/api/v1/email-templates/{$template->id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $template->id)
            ->assertJsonPath('data.name', 'Template Teste');
    }

    public function test_store_creates_template(): void
    {
        $response = $this->postJson('/api/v1/email-templates', [
            'name' => 'Novo Template',
            'subject' => 'Assunto Novo',
            'body' => '<p>Corpo novo</p>',
            'is_shared' => false,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.name', 'Novo Template')
            ->assertJsonPath('data.user_id', $this->user->id);

        $this->assertDatabaseHas('email_templates', [
            'name' => 'Novo Template',
            'user_id' => $this->user->id,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_store_shared_template_sets_user_id_null(): void
    {
        $response = $this->postJson('/api/v1/email-templates', [
            'name' => 'Shared Template',
            'body' => '<p>Shared body</p>',
            'is_shared' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.is_shared', true);

        $this->assertDatabaseHas('email_templates', [
            'name' => 'Shared Template',
            'user_id' => null,
            'is_shared' => true,
        ]);
    }

    public function test_store_validation_requires_name_and_body(): void
    {
        $response = $this->postJson('/api/v1/email-templates', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'body']);
    }

    public function test_store_validation_name_max_length(): void
    {
        $response = $this->postJson('/api/v1/email-templates', [
            'name' => str_repeat('a', 256),
            'body' => '<p>Body</p>',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    }

    public function test_update_modifies_template(): void
    {
        $template = $this->createTemplate();

        $response = $this->putJson("/api/v1/email-templates/{$template->id}", [
            'name' => 'Updated Name',
            'body' => '<p>Updated body</p>',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.name', 'Updated Name');

        $this->assertDatabaseHas('email_templates', [
            'id' => $template->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_update_shared_flag_changes_user_id(): void
    {
        $template = $this->createTemplate(['is_shared' => false, 'user_id' => $this->user->id]);

        // Make shared
        $response = $this->putJson("/api/v1/email-templates/{$template->id}", [
            'is_shared' => true,
        ]);

        $response->assertOk();
        $this->assertNull($template->fresh()->user_id);

        // Make private again
        $response2 = $this->putJson("/api/v1/email-templates/{$template->id}", [
            'is_shared' => false,
        ]);

        $response2->assertOk();
        $this->assertEquals($this->user->id, $template->fresh()->user_id);
    }

    public function test_destroy_deletes_template(): void
    {
        $template = $this->createTemplate();

        $response = $this->deleteJson("/api/v1/email-templates/{$template->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('email_templates', ['id' => $template->id]);
    }

    public function test_unauthenticated_returns_401(): void
    {
        app('auth')->forgetGuards();
        $response = $this->getJson('/api/v1/email-templates');

        $response->assertUnauthorized();
    }
}
