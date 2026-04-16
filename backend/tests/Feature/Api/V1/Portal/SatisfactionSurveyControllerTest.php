<?php

namespace Tests\Feature\Api\V1\Portal;

use App\Http\Middleware\CheckPermission;
use App\Http\Middleware\EnsureTenantScope;
use App\Models\Customer;
use App\Models\SatisfactionSurvey;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SatisfactionSurveyControllerTest extends TestCase
{
    private Tenant $tenant;

    private User $user;

    private Customer $customer;

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
        $this->customer = Customer::factory()->create(['tenant_id' => $this->tenant->id]);

        $this->setTenantContext($this->tenant->id);
        Sanctum::actingAs($this->user, ['*']);
    }

    private function createSurvey(): SatisfactionSurvey
    {
        return SatisfactionSurvey::create([
            'tenant_id' => $this->tenant->id,
            'customer_id' => $this->customer->id,
            'channel' => 'email',
        ]);
    }

    public function test_show_returns_404_without_valid_token(): void
    {
        $survey = $this->createSurvey();

        $response = $this->getJson("/api/v1/portal/satisfaction-surveys/{$survey->id}");

        $response->assertStatus(404);
    }

    public function test_show_returns_404_with_invalid_token(): void
    {
        $survey = $this->createSurvey();

        $response = $this->getJson("/api/v1/portal/satisfaction-surveys/{$survey->id}?token=token-invalido");

        $response->assertStatus(404);
    }

    public function test_show_returns_survey_with_valid_token(): void
    {
        $survey = $this->createSurvey();
        $token = Crypt::encrypt($survey->id);

        $response = $this->getJson("/api/v1/portal/satisfaction-surveys/{$survey->id}?token=".urlencode($token));

        $response->assertOk()->assertJsonPath('data.id', $survey->id);
        $this->assertFalse($response->json('data.answered'));
    }

    public function test_answer_validates_required_fields(): void
    {
        $survey = $this->createSurvey();

        $response = $this->postJson("/api/v1/portal/satisfaction-surveys/{$survey->id}/answer", []);

        $response->assertStatus(422);
    }

    public function test_answer_with_valid_token_stores_ratings(): void
    {
        $survey = $this->createSurvey();
        $token = Crypt::encrypt($survey->id);

        $response = $this->postJson("/api/v1/portal/satisfaction-surveys/{$survey->id}/answer", [
            'token' => $token,
            'nps_score' => 9,
            'service_rating' => 5,
            'technician_rating' => 5,
            'timeliness_rating' => 4,
            'comment' => 'Excelente atendimento',
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('satisfaction_surveys', [
            'id' => $survey->id,
            'nps_score' => 9,
            'service_rating' => 5,
        ]);
    }

    public function test_answer_rejects_already_answered_survey(): void
    {
        $survey = $this->createSurvey();
        $survey->update(['nps_score' => 10]);
        $token = Crypt::encrypt($survey->id);

        $response = $this->postJson("/api/v1/portal/satisfaction-surveys/{$survey->id}/answer", [
            'token' => $token,
            'nps_score' => 5,
            'service_rating' => 3,
        ]);

        $response->assertStatus(422);
    }
}
