<?php

namespace Tests\Feature\Api\V1\Quality;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\SatisfactionSurvey;
use App\Models\Tenant;
use App\Models\User;
use App\Models\WorkOrder;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class SatisfactionSurveyFlowTest extends TestCase
{
    private Tenant $tenant;

    private Customer $customer;

    private WorkOrder $workOrder;

    protected function setUp(): void
    {
        parent::setUp();

        Gate::before(fn () => true);

        $this->tenant = Tenant::factory()->create();
        $this->customer = Customer::factory()->create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Cliente Pesquisa',
        ]);
        $this->workOrder = WorkOrder::factory()->create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'number' => 'OS-000123',
        ]);
    }

    public function test_public_satisfaction_survey_show_returns_metadata_with_valid_token(): void
    {
        $survey = SatisfactionSurvey::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'work_order_id' => $this->workOrder->id,
            'channel' => 'email',
        ]);

        $response = $this->getJson("/api/v1/portal/satisfaction-surveys/{$survey->id}?token=".urlencode(encrypt($survey->id)));

        $response->assertOk()
            ->assertJsonPath('data.id', $survey->id)
            ->assertJsonPath('data.customer.id', $this->customer->id)
            ->assertJsonPath('data.customer.name', 'Cliente Pesquisa')
            ->assertJsonPath('data.work_order.id', $this->workOrder->id)
            ->assertJsonPath('data.work_order.number', 'OS-000123')
            ->assertJsonPath('data.answered', false);
    }

    public function test_public_satisfaction_survey_answer_updates_existing_survey(): void
    {
        $survey = SatisfactionSurvey::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'work_order_id' => $this->workOrder->id,
            'channel' => 'whatsapp',
        ]);

        $response = $this->postJson("/api/v1/portal/satisfaction-surveys/{$survey->id}/answer", [
            'token' => encrypt($survey->id),
            'nps_score' => 10,
            'service_rating' => 5,
            'technician_rating' => 5,
            'timeliness_rating' => 4,
            'comment' => 'Atendimento excelente',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $survey->id)
            ->assertJsonPath('data.nps_score', 10)
            ->assertJsonPath('data.service_rating', 5)
            ->assertJsonPath('data.technician_rating', 5)
            ->assertJsonPath('data.timeliness_rating', 4)
            ->assertJsonPath('data.comment', 'Atendimento excelente');

        $this->assertDatabaseHas('satisfaction_surveys', [
            'id' => $survey->id,
            'tenant_id' => $this->tenant->id,
            'nps_score' => 10,
            'service_rating' => 5,
            'technician_rating' => 5,
            'timeliness_rating' => 4,
            'comment' => 'Atendimento excelente',
        ]);
    }

    public function test_quality_surveys_store_accepts_user_with_current_tenant_context(): void
    {
        $this->withoutMiddleware([EnsureTenantScope::class, CheckPermission::class]);

        $user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'current_tenant_id' => $this->tenant->id,
        ]);
        setPermissionsTeamId($this->tenant->id);
        Permission::findOrCreate('customer.satisfaction.manage', 'web');
        $user->givePermissionTo('customer.satisfaction.manage');

        Sanctum::actingAs($user, ['*']);

        $response = $this->postJson('/api/v1/quality/surveys', [
            'customer_id' => $this->customer->id,
            'work_order_id' => $this->workOrder->id,
            'nps_score' => 8,
            'comment' => 'Fluxo validado',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.customer_id', $this->customer->id)
            ->assertJsonPath('data.work_order_id', $this->workOrder->id)
            ->assertJsonPath('data.nps_score', 8);
    }
}
