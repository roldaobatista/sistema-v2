<?php

namespace Tests\Feature\Api\V1\Quality;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentVersionControllerTest extends TestCase
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

    private function createDocumentVersion(array $overrides = []): int
    {
        return DB::table('document_versions')->insertGetId(array_merge([
            'tenant_id' => $this->tenant->id,
            'document_code' => 'DOC-001',
            'title' => 'Procedimento de Calibracao',
            'category' => 'procedure',
            'version' => '1',
            'description' => 'Desc padrao',
            'file_path' => null,
            'status' => 'draft',
            'created_by' => $this->user->id,
            'approved_by' => null,
            'effective_date' => now()->toDateString(),
            'review_date' => now()->addYear()->toDateString(),
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    // ─── INDEX ────────────────────────────────────────────────────────

    public function test_index_returns_paginated_document_versions(): void
    {
        $this->createDocumentVersion(['title' => 'Doc A', 'version' => '2']);
        $this->createDocumentVersion(['title' => 'Doc B', 'version' => '1']);

        $response = $this->getJson('/api/v1/document-versions');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta'])
            ->assertJsonCount(2, 'data');
    }

    public function test_index_filters_by_search(): void
    {
        $this->createDocumentVersion(['title' => 'Manual de Qualidade']);
        $this->createDocumentVersion(['title' => 'Instrucao de Trabalho']);

        $response = $this->getJson('/api/v1/document-versions?search=Qualidade');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('Manual de Qualidade', $data[0]['title']);
    }

    public function test_index_filters_by_status(): void
    {
        $this->createDocumentVersion(['status' => 'draft']);
        $this->createDocumentVersion(['status' => 'approved']);

        $response = $this->getJson('/api/v1/document-versions?status=approved');

        $response->assertOk();
        $data = $response->json('data');
        $this->assertCount(1, $data);
        $this->assertEquals('approved', $data[0]['status']);
    }

    public function test_index_respects_tenant_isolation(): void
    {
        $this->createDocumentVersion(['title' => 'Meu Doc']);

        $otherTenant = Tenant::factory()->create();
        DB::table('document_versions')->insert([
            'tenant_id' => $otherTenant->id,
            'document_code' => 'DOC-999',
            'title' => 'Doc de Outro Tenant',
            'category' => 'manual',
            'version' => '1',
            'status' => 'draft',
            'created_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson('/api/v1/document-versions');

        $response->assertOk();
        $titles = collect($response->json('data'))->pluck('title')->toArray();
        $this->assertContains('Meu Doc', $titles);
        $this->assertNotContains('Doc de Outro Tenant', $titles);
    }

    // ─── STORE ────────────────────────────────────────────────────────

    public function test_store_creates_document_version(): void
    {
        $response = $this->postJson('/api/v1/document-versions', [
            'title' => 'Novo Procedimento',
            'document_type' => 'procedure',
            'description' => 'Descricao do procedimento',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('document_versions', [
            'title' => 'Novo Procedimento',
            'tenant_id' => $this->tenant->id,
            'created_by' => $this->user->id,
            'status' => 'draft',
        ]);
    }

    public function test_store_auto_increments_version_number(): void
    {
        // Create two documents with the same title
        $this->createDocumentVersion(['title' => 'Repeated Doc', 'version' => '3']);

        $response = $this->postJson('/api/v1/document-versions', [
            'title' => 'Repeated Doc',
            'document_type' => 'instruction',
        ]);

        $response->assertStatus(201);
        // version should be max(existing) + 1 = 4
        $created = DB::table('document_versions')
            ->where('id', $response->json('data.id'))
            ->first();
        $this->assertNotNull($created);
        $this->assertEquals('4', $created->version);
    }

    public function test_store_validation_requires_title_and_document_type(): void
    {
        $response = $this->postJson('/api/v1/document-versions', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'document_type']);
    }

    public function test_store_validation_rejects_invalid_document_type(): void
    {
        $response = $this->postJson('/api/v1/document-versions', [
            'title' => 'Doc',
            'document_type' => 'invalid_type',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['document_type']);
    }

    // ─── SHOW ─────────────────────────────────────────────────────────

    public function test_show_returns_document(): void
    {
        $id = $this->createDocumentVersion(['title' => 'Show Me']);

        $response = $this->getJson("/api/v1/document-versions/{$id}");

        $response->assertOk()
            ->assertJsonPath('data.title', 'Show Me');
    }

    public function test_show_returns_404_for_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $id = DB::table('document_versions')->insertGetId([
            'tenant_id' => $otherTenant->id,
            'document_code' => 'DOC-OTHER',
            'title' => 'Alien Doc',
            'category' => 'form',
            'version' => '1',
            'status' => 'draft',
            'created_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->getJson("/api/v1/document-versions/{$id}");

        $response->assertNotFound();
    }

    public function test_show_returns_404_for_nonexistent_id(): void
    {
        $response = $this->getJson('/api/v1/document-versions/999999');

        $response->assertNotFound();
    }

    // ─── UPDATE ───────────────────────────────────────────────────────

    public function test_update_modifies_document(): void
    {
        $id = $this->createDocumentVersion(['title' => 'Old Title', 'status' => 'draft']);

        $response = $this->putJson("/api/v1/document-versions/{$id}", [
            'title' => 'New Title',
            'status' => 'approved',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.title', 'New Title')
            ->assertJsonPath('data.status', 'approved');
    }

    public function test_update_returns_404_for_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $id = DB::table('document_versions')->insertGetId([
            'tenant_id' => $otherTenant->id,
            'document_code' => 'DOC-X',
            'title' => 'X',
            'category' => 'form',
            'version' => '1',
            'status' => 'draft',
            'created_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->putJson("/api/v1/document-versions/{$id}", ['title' => 'Hacked']);

        $response->assertNotFound();
    }

    public function test_update_validates_status(): void
    {
        $id = $this->createDocumentVersion();

        $response = $this->putJson("/api/v1/document-versions/{$id}", [
            'status' => 'invalid_status',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['status']);
    }

    // ─── DESTROY ──────────────────────────────────────────────────────

    public function test_destroy_deletes_document(): void
    {
        $id = $this->createDocumentVersion(['title' => 'To Delete']);

        $response = $this->deleteJson("/api/v1/document-versions/{$id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('document_versions', ['id' => $id]);
    }

    public function test_destroy_returns_404_for_other_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $id = DB::table('document_versions')->insertGetId([
            'tenant_id' => $otherTenant->id,
            'document_code' => 'DOC-DEL',
            'title' => 'Del',
            'category' => 'form',
            'version' => '1',
            'status' => 'draft',
            'created_by' => $this->user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->deleteJson("/api/v1/document-versions/{$id}");

        $response->assertNotFound();
    }

    // ─── AUTH ─────────────────────────────────────────────────────────

    public function test_unauthenticated_user_gets_401(): void
    {
        // Reset acting-as
        Sanctum::actingAs(new User, []);
        $this->app['auth']->forgetGuards();

        $response = $this->getJson('/api/v1/document-versions');

        $response->assertUnauthorized();
    }
}
