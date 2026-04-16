<?php

namespace Tests\Feature\Api\V1;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\JourneyEntry;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AccountingReportControllerTest extends TestCase
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

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    public function test_index_returns_entries_only_for_current_tenant(): void
    {
        // Entries do tenant atual (datas distintas — UNIQUE(user_id, date))
        foreach (['2026-04-01', '2026-04-02', '2026-04-03'] as $date) {
            JourneyEntry::factory()->create([
                'tenant_id' => $this->tenant->id,
                'user_id' => $this->user->id,
                'date' => $date,
            ]);
        }

        // Entry de OUTRO tenant (nao pode vazar)
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'current_tenant_id' => $otherTenant->id,
        ]);
        JourneyEntry::factory()->create([
            'tenant_id' => $otherTenant->id,
            'user_id' => $otherUser->id,
            'date' => '2026-04-02',
        ]);

        $response = $this->getJson('/api/v1/hr/reports/accounting?'.http_build_query([
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
        ]));

        $response->assertOk()
            ->assertJsonStructure(['data']);

        $data = $response->json('data');
        // Deve conter exatamente as 3 entries do tenant atual
        $this->assertIsArray($data);
        $this->assertCount(3, $data, 'Relatorio deve isolar entries por tenant — vazamento detectado');
    }

    public function test_index_requires_start_date_and_end_date(): void
    {
        $response = $this->getJson('/api/v1/hr/reports/accounting');

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['start_date', 'end_date']);
    }

    public function test_index_rejects_end_date_before_start_date(): void
    {
        $response = $this->getJson('/api/v1/hr/reports/accounting?'.http_build_query([
            'start_date' => '2026-04-10',
            'end_date' => '2026-04-01',
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['end_date']);
    }

    public function test_index_filters_by_user_id(): void
    {
        $otherUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);

        // 2 entries do user alvo
        foreach (['2026-04-10', '2026-04-11'] as $date) {
            JourneyEntry::factory()->create([
                'tenant_id' => $this->tenant->id,
                'user_id' => $this->user->id,
                'date' => $date,
            ]);
        }

        // 3 entries de outro user (nao devem aparecer no filtro)
        foreach (['2026-04-10', '2026-04-11', '2026-04-12'] as $date) {
            JourneyEntry::factory()->create([
                'tenant_id' => $this->tenant->id,
                'user_id' => $otherUser->id,
                'date' => $date,
            ]);
        }

        $response = $this->getJson('/api/v1/hr/reports/accounting?'.http_build_query([
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'user_id' => $this->user->id,
        ]));

        $response->assertOk();
        $this->assertCount(2, $response->json('data'));
    }

    public function test_index_respects_date_range(): void
    {
        // Dentro do range
        JourneyEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'date' => '2026-04-05',
        ]);

        // Fora do range (anterior)
        JourneyEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'date' => '2026-03-15',
        ]);

        // Fora do range (posterior)
        JourneyEntry::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->user->id,
            'date' => '2026-05-20',
        ]);

        $response = $this->getJson('/api/v1/hr/reports/accounting?'.http_build_query([
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
        ]));

        $response->assertOk();
        $this->assertCount(1, $response->json('data'), 'Deve retornar apenas entries dentro do date range');
    }

    public function test_export_requires_format_parameter(): void
    {
        $response = $this->getJson('/api/v1/hr/reports/accounting/export?'.http_build_query([
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['format']);
    }

    public function test_export_rejects_invalid_format(): void
    {
        $response = $this->getJson('/api/v1/hr/reports/accounting/export?'.http_build_query([
            'start_date' => '2026-04-01',
            'end_date' => '2026-04-30',
            'format' => 'xml',
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['format']);
    }
}
