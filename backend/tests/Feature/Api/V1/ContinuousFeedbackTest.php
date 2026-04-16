<?php

namespace Tests\Feature\Api\V1;

use App\Models\ContinuousFeedback;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ContinuousFeedbackTest extends TestCase
{
    use WithFaker;

    protected $tenant;

    protected $user;

    protected $otherUser;

    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);

        // Create Tenant
        $this->tenant = Tenant::factory()->create();

        // Create Users
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->otherUser = User::factory()->create(['tenant_id' => $this->tenant->id]);

        // Setup Permissions
        $role = Role::create(['name' => 'test_role', 'guard_name' => 'web']);
        // Reset permission cache
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
        $permissions = [
            'hr.feedback.view',
            'hr.feedback.create',
            'hr.feedback.view_all',
        ];

        try {
            setPermissionsTeamId($this->tenant->id);
            foreach ($permissions as $perm) {
                $p = Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
                $this->user->givePermissionTo($p);
            }
        } catch (\Throwable $e) {
            fwrite(STDERR, 'Setup Error: '.$e->getMessage()."\n");

            throw $e;
        }

        Sanctum::actingAs($this->user);
    }

    public function test_can_list_feedback()
    {
        ContinuousFeedback::create([
            'tenant_id' => $this->tenant->id,
            'from_user_id' => $this->user->id,
            'to_user_id' => $this->otherUser->id,
            'content' => 'Great job!',
            'type' => 'praise',
            'visibility' => 'public',
            'is_anonymous' => false,
        ]);

        $response = $this->getJson('/api/v1/hr/continuous-feedback');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id', 'from_user', 'to_user', 'content', 'type', 'created_at',
                    ],
                ],
            ]);
    }

    public function test_can_create_feedback()
    {
        $data = [
            'to_user_id' => $this->otherUser->id,
            'content' => 'You need to improve communication.',
            'type' => 'suggestion',
            'visibility' => 'private',
        ];

        $response = $this->postJson('/api/v1/hr/continuous-feedback', $data);

        $response->assertStatus(201)
            ->assertJsonFragment(['content' => 'You need to improve communication.']);

        $this->assertDatabaseHas('continuous_feedback', [
            'from_user_id' => $this->user->id,
            'to_user_id' => $this->otherUser->id,
            'content' => 'You need to improve communication.',
        ]);
    }

    public function test_validates_feedback_input()
    {
        $response = $this->postJson('/api/v1/hr/continuous-feedback', [
            'type' => 'invalid_type',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['to_user_id', 'content', 'type', 'visibility']);
    }

    public function test_can_create_feedback_with_attachment(): void
    {
        Storage::fake('public');

        $response = $this->post('/api/v1/hr/continuous-feedback', [
            'to_user_id' => $this->otherUser->id,
            'content' => 'Foto anexada para contexto.',
            'type' => 'concern',
            'visibility' => 'manager_only',
            'attachment' => UploadedFile::fake()->image('feedback.png'),
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.content', 'Foto anexada para contexto.');

        $storedPath = $response->json('data.attachment_path');
        $this->assertNotNull($storedPath);
        Storage::disk('public')->assertExists($storedPath);
        $this->assertDatabaseHas('continuous_feedback', [
            'from_user_id' => $this->user->id,
            'to_user_id' => $this->otherUser->id,
            'type' => 'concern',
        ]);
    }
}
