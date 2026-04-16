<?php

namespace Tests\Feature;

use App\Enums\ServiceCallStatus;
use App\Http\Middleware\CheckPermission;
use App\Models\Camera;
use App\Models\Customer;
use App\Models\Lookups\TvCameraType;
use App\Models\ServiceCall;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class TvDashboardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Gate::before(fn () => true);
        $this->withoutMiddleware(CheckPermission::class);
    }

    protected function createUserWithTvDashboard(): User
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'current_tenant_id' => $tenant->id,
        ]);

        return $user;
    }

    public function test_tv_dashboard_returns_correct_structure(): void
    {
        $user = $this->createUserWithTvDashboard();
        $tenantId = $user->current_tenant_id;

        Camera::create([
            'tenant_id' => $tenantId,
            'name' => 'Portaria',
            'stream_url' => 'rtsp://admin:123456@192.168.1.10:554/cam/realmonitor?channel=1&subtype=0',
            'is_active' => true,
            'position' => 1,
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/tv/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [
                'cameras' => [
                    '*' => ['id', 'name', 'stream_url'],
                ],
                'operational' => [
                    'technicians' => [
                        '*' => ['id', 'name', 'status', 'location_lat', 'location_lng', 'location_updated_at'],
                    ],
                    'service_calls',
                    'work_orders',
                    'latest_work_orders',
                    'kpis' => [
                        'chamados_hoje',
                        'os_hoje',
                        'os_em_execucao',
                        'os_finalizadas',
                        'tecnicos_online',
                        'tecnicos_em_campo',
                        'tecnicos_total',
                    ],
                ],
            ]]);
    }

    public function test_tv_dashboard_requires_authentication(): void
    {
        $response = $this->getJson('/api/v1/tv/dashboard');

        $response->assertStatus(401);
    }

    public function test_tv_kpis_returns_structure(): void
    {
        $user = $this->createUserWithTvDashboard();

        $response = $this->actingAs($user)->getJson('/api/v1/tv/kpis');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [
                'chamados_hoje',
                'os_hoje',
                'os_em_execucao',
                'os_finalizadas',
                'tecnicos_online',
                'tecnicos_em_campo',
                'tecnicos_total',
            ]]);
    }

    public function test_tv_alerts_returns_array(): void
    {
        $user = $this->createUserWithTvDashboard();

        $response = $this->actingAs($user)->getJson('/api/v1/tv/alerts');

        $response->assertStatus(200)->assertJsonStructure(['data' => ['alerts']]);
    }

    public function test_tv_alerts_include_unattended_service_call_with_current_statuses(): void
    {
        $user = $this->createUserWithTvDashboard();
        $customer = Customer::factory()->create([
            'tenant_id' => $user->current_tenant_id,
            'name' => 'Cliente TV',
        ]);

        ServiceCall::factory()->create([
            'tenant_id' => $user->current_tenant_id,
            'customer_id' => $customer->id,
            'created_by' => $user->id,
            'status' => ServiceCallStatus::PENDING_SCHEDULING->value,
            'created_at' => now()->subMinutes(45),
            'updated_at' => now()->subMinutes(45),
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/tv/alerts');

        $response->assertOk();
        $alerts = collect($response->json('data.alerts'));

        $this->assertTrue(
            $alerts->contains(fn (array $alert) => $alert['type'] === 'unattended_call' && str_contains($alert['message'], 'Cliente TV'))
        );
    }

    public function test_tv_cameras_index_returns_cameras(): void
    {
        $user = $this->createUserWithTvDashboard();

        Camera::create([
            'tenant_id' => $user->current_tenant_id,
            'name' => 'Recepcao',
            'stream_url' => 'rtsp://192.168.1.1/stream',
            'is_active' => true,
            'position' => 0,
        ]);

        $response = $this->actingAs($user)->getJson('/api/v1/tv/cameras');

        $response->assertStatus(200)
            ->assertJsonStructure(['data' => [['id', 'name', 'stream_url', 'is_active', 'position']]])
            ->assertJsonPath('data.0.name', 'Recepcao');
    }

    public function test_tv_cameras_store_creates_camera(): void
    {
        $user = $this->createUserWithTvDashboard();

        $response = $this->actingAs($user)->postJson('/api/v1/tv/cameras', [
            'name' => 'Nova Camera',
            'stream_url' => 'rtsp://192.168.1.2/stream',
            'location' => 'Galpao A',
            'is_active' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['data' => ['id', 'name', 'stream_url', 'tenant_id']])
            ->assertJsonPath('data.name', 'Nova Camera');

        $this->assertDatabaseHas('cameras', [
            'name' => 'Nova Camera',
            'stream_url' => 'rtsp://192.168.1.2/stream',
        ]);
    }

    public function test_tv_cameras_store_accepts_lookup_type_slug(): void
    {
        $user = $this->createUserWithTvDashboard();

        TvCameraType::create([
            'tenant_id' => $user->current_tenant_id,
            'name' => 'Termica',
            'slug' => 'thermal',
            'is_active' => true,
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/tv/cameras', [
            'name' => 'Camera Termica',
            'stream_url' => 'rtsp://192.168.1.50/stream',
            'type' => 'thermal',
            'is_active' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.type', 'thermal');
    }

    public function test_tv_cameras_store_rejects_invalid_type(): void
    {
        $user = $this->createUserWithTvDashboard();

        $response = $this->actingAs($user)->postJson('/api/v1/tv/cameras', [
            'name' => 'Camera Invalida',
            'stream_url' => 'rtsp://192.168.1.51/stream',
            'type' => 'nao-permitido',
            'is_active' => true,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }
}
