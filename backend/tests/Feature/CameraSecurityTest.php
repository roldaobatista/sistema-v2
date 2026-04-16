<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CameraSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
    }

    private function createUser(): User
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
        ]);

        app()->instance('current_tenant_id', $tenant->id);
        setPermissionsTeamId($tenant->id);
        Permission::findOrCreate('tv.camera.manage', 'web');
        $user->givePermissionTo('tv.camera.manage');

        return $user;
    }

    public function test_camera_store_rejects_invalid_schemes(): void
    {
        $user = $this->createUser();

        $badUrls = [
            'file:///etc/passwd',
            'javascript:alert(1)',
            'ftp://server/file',
            'gopher://evil.com',
        ];

        foreach ($badUrls as $url) {
            $response = $this->actingAs($user)->postJson('/api/v1/tv/cameras', [
                'name' => 'Malicious Camera',
                'stream_url' => $url,
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors('stream_url');
        }
    }

    public function test_camera_test_connection_rejects_invalid_schemes(): void
    {
        $user = $this->createUser();

        $badUrls = [
            'file:///etc/passwd',
            'javascript:alert(1)',
        ];

        foreach ($badUrls as $url) {
            $response = $this->actingAs($user)->postJson('/api/v1/tv/cameras/test-connection', [
                'stream_url' => $url,
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors('stream_url');
        }
    }

    public function test_camera_store_accepts_valid_stream_urls(): void
    {
        $user = $this->createUser();

        $validUrls = [
            'rtsp://192.168.1.50/stream',
            'http://10.0.0.1/video',
            'https://camera.example.com/live',
        ];

        foreach ($validUrls as $url) {
            $response = $this->actingAs($user)->postJson('/api/v1/tv/cameras', [
                'name' => 'Valid Camera',
                'stream_url' => $url,
            ]);

            $response->assertStatus(201);
        }
    }
}
